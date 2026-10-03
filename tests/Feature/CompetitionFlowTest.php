<?php

namespace Tests\Feature;

use App\Actions\CompetitionSetup;
use App\Actions\RunCompetition;
use App\Actions\ScheduleRound;
use App\Actions\SubmitScore;
use App\Models\Bout;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Tests\CreatesCompetition;
use Tests\TestCase;

class CompetitionFlowTest extends TestCase
{
    use CreatesCompetition, RefreshDatabase;

    public function test_four_athlete_knockout_runs_from_draw_through_final_and_completion(): void
    {
        $fixture = $this->competition(5, 4);
        $this->schedule($fixture);
        $firstRound = $fixture['category']->rounds()->firstOrFail();
        $this->assertSame('ready', $fixture['tournament']->fresh()->status);
        $this->assertSame(2, $firstRound->bouts()->count());

        $this->actingAs($fixture['admin'])->post('/tournaments/'.$fixture['tournament']->id.'/complete')
            ->assertSessionHasErrors('operation');
        $this->post('/tournaments/'.$fixture['tournament']->id.'/categories/'.$fixture['category']->id.'/rounds', [
            'court_id' => $fixture['tournament']->courts()->firstOrFail()->id,
            'judge_ids' => $fixture['judges']->pluck('id')->all(),
        ])->assertSessionHasErrors('operation');

        $winners = [];
        foreach ($firstRound->bouts()->orderBy('sequence')->get() as $bout) {
            $winnerEntryId = $bout->entries()->firstOrFail()->id;
            $this->approveBout($fixture, $bout, $winnerEntryId);
            $winners[] = $winnerEntryId;
            $this->assertSame($winnerEntryId, $bout->fresh()->winner_entry_id);
        }

        $this->assertSame('completed', $firstRound->fresh()->status);
        $this->post('/tournaments/'.$fixture['tournament']->id.'/complete')->assertSessionHasErrors('operation');

        $this->schedule($fixture);
        $final = $fixture['category']->rounds()->orderByDesc('sequence')->firstOrFail();
        $finalBout = $final->bouts()->firstOrFail();
        $this->assertSame(2, $final->sequence);
        $this->assertEqualsCanonicalizing($winners, $finalBout->entries()->pluck('entries.id')->all());

        $this->approveBout($fixture, $finalBout, $winners[0]);
        $this->assertSame('completed', $final->fresh()->status);
        $this->post('/tournaments/'.$fixture['tournament']->id.'/complete')->assertSessionHasNoErrors();

        $this->assertSame('completed', $fixture['tournament']->fresh()->status);
        $this->assertSame($winners[0], $finalBout->fresh()->winner_entry_id);
        $this->assertDatabaseCount('results', 12);
        $this->assertDatabaseCount('score_revisions', 60);
        $this->assertDatabaseHas('audit_logs', ['action' => 'tournament.completed', 'subject_id' => $fixture['tournament']->id]);
        $this->post('/tournaments/'.$fixture['tournament']->id.'/complete')->assertSessionHasErrors('operation');
    }

    public function test_invalid_transitions_stale_versions_and_duplicate_scores_cannot_change_a_result(): void
    {
        $fixture = $this->competition(5, 4);
        $this->schedule($fixture);
        $bouts = Bout::orderBy('sequence')->get();
        $performance = $bouts[0]->performances()->orderBy('id')->firstOrFail();
        $otherPerformance = $bouts[1]->performances()->orderBy('id')->firstOrFail();
        $secondForm = $bouts[0]->performances()->where('entry_id', $performance->entry_id)->where('form_number', 2)->firstOrFail();
        $commandUrl = '/tournaments/'.$fixture['tournament']->id.'/performances/'.$performance->id.'/command';
        $this->actingAs($fixture['admin']);

        foreach (['finish', 'approve'] as $invalidCommand) {
            $this->post($commandUrl, ['command' => $invalidCommand, 'expected_version' => $performance->version])
                ->assertSessionHasErrors('operation');
        }

        $scoreUrl = '/tournaments/'.$fixture['tournament']->id.'/performances/'.$performance->id.'/scores';
        $score = [
            'request_id' => (string) Str::uuid(), 'expected_version' => $performance->version,
            'expected_revision' => 0, ...$this->detailedScoreInput('2.50', '6.00'),
        ];
        $this->actingAs($fixture['judges'][0])->post($scoreUrl, $score)->assertSessionHasErrors('operation');
        $this->assertDatabaseCount('score_sheets', 0);

        $run = app(RunCompetition::class);
        $run->command($fixture['admin'], $fixture['tournament'], $performance, ['command' => 'start', 'expected_version' => 1]);
        $this->assertSame('running', $fixture['tournament']->fresh()->status);
        $this->assertNotNull($performance->fresh()->started_at);
        $this->actingAs($fixture['admin'])->post($commandUrl, ['command' => 'finish', 'expected_version' => 1])->assertStatus(409);
        $this->post($commandUrl, ['command' => 'start', 'expected_version' => $performance->fresh()->version])->assertSessionHasErrors('operation');
        $this->post('/tournaments/'.$fixture['tournament']->id.'/performances/'.$otherPerformance->id.'/command', [
            'command' => 'start', 'expected_version' => $otherPerformance->version,
        ])->assertSessionHasErrors('operation');
        $this->post('/tournaments/'.$fixture['tournament']->id.'/performances/'.$secondForm->id.'/command', [
            'command' => 'start', 'expected_version' => $secondForm->version,
        ])->assertSessionHasErrors('operation');

        $run->command($fixture['admin'], $fixture['tournament'], $performance, [
            'command' => 'finish', 'expected_version' => $performance->fresh()->version,
        ]);
        $this->assertSame('scoring', $performance->fresh()->status);
        $this->assertNotNull($performance->fresh()->ended_at);
        $this->post($commandUrl, ['command' => 'approve', 'expected_version' => $performance->fresh()->version])
            ->assertSessionHasErrors('operation');

        $score['expected_version'] = $performance->fresh()->version;
        $submit = app(SubmitScore::class);
        $firstSubmission = $submit->handle($fixture['judges'][0], $fixture['tournament'], $performance, $score);
        $this->assertSame($firstSubmission, $submit->handle($fixture['judges'][0], $fixture['tournament'], $performance, $score));
        $this->actingAs($fixture['judges'][0])->postJson($scoreUrl, [
            ...$score, 'request_id' => (string) Str::uuid(), 'reason' => 'اصلاح نمره',
        ])->assertStatus(409);

        foreach ($fixture['judges']->skip(1) as $judge) {
            $submit->handle($judge, $fixture['tournament'], $performance, [
                ...$score, 'request_id' => (string) Str::uuid(),
            ]);
        }

        $run->command($fixture['admin'], $fixture['tournament'], $performance, [
            'command' => 'approve', 'expected_version' => $performance->fresh()->version,
        ]);
        $this->assertSame('approved', $performance->fresh()->status);
        $this->assertDatabaseHas('results', ['performance_id' => $performance->id, 'score' => '8.500000']);
        $this->actingAs($fixture['admin'])->post($commandUrl, [
            'command' => 'approve', 'expected_version' => $performance->fresh()->version,
        ])->assertSessionHasErrors('operation');
        $this->assertDatabaseCount('results', 1);

        $this->post('/tournaments/'.$fixture['tournament']->id.'/performances/'.$secondForm->id.'/command', [
            'command' => 'start', 'expected_version' => $secondForm->fresh()->version,
        ])->assertSessionHasErrors('operation');
        $opponentFirstForm = $bouts[0]->performances()->where('entry_id', '!=', $performance->entry_id)->where('form_number', 1)->firstOrFail();
        $run->command($fixture['admin'], $fixture['tournament'], $opponentFirstForm, [
            'command' => 'start', 'expected_version' => $opponentFirstForm->fresh()->version,
        ]);
        $this->assertSame('running', $opponentFirstForm->fresh()->status);
    }

    public function test_a_judge_cannot_be_used_on_two_courts_at_once(): void
    {
        $fixture = $this->competition(5, 4);
        app(CompetitionSetup::class)->court($fixture['admin'], $fixture['tournament'], ['name' => 'زمین دو']);
        $this->schedule($fixture);
        $bouts = Bout::orderBy('sequence')->get();
        $bouts[1]->update(['court_id' => $fixture['tournament']->courts()->where('name', 'زمین دو')->firstOrFail()->id]);
        $firstPerformance = $bouts[0]->performances()->orderBy('id')->firstOrFail();
        $secondPerformance = $bouts[1]->performances()->orderBy('id')->firstOrFail();
        $run = app(RunCompetition::class);

        $run->command($fixture['admin'], $fixture['tournament'], $firstPerformance, [
            'command' => 'start', 'expected_version' => $firstPerformance->version,
        ]);
        $this->actingAs($fixture['admin'])->post('/tournaments/'.$fixture['tournament']->id.'/performances/'.$secondPerformance->id.'/command', [
            'command' => 'start', 'expected_version' => $secondPerformance->version,
        ])->assertSessionHasErrors('operation');

        $this->assertSame('running', $firstPerformance->fresh()->status);
        $this->assertSame('pending', $secondPerformance->fresh()->status);
        $this->assertDatabaseCount('results', 0);
    }

    public function test_a_shared_judge_cannot_start_performances_in_two_tournaments(): void
    {
        $first = $this->competition();
        $second = $this->competition();
        $this->schedule($first);
        $sharedJudge = $first['judges'][0];
        $second['tournament']->users()->attach($sharedJudge->id, ['role' => 'judge']);
        app(ScheduleRound::class)->handle($second['admin'], $second['tournament'], $second['category'], [
            'court_id' => $second['tournament']->courts()->firstOrFail()->id,
            'judge_ids' => [$sharedJudge->id, ...$second['judges']->skip(1)->pluck('id')->all()],
        ]);

        $firstPerformance = $first['category']->rounds()->firstOrFail()->bouts()->firstOrFail()->performances()->orderBy('id')->firstOrFail();
        $secondPerformance = $second['category']->rounds()->firstOrFail()->bouts()->firstOrFail()->performances()->orderBy('id')->firstOrFail();
        app(RunCompetition::class)->command($first['admin'], $first['tournament'], $firstPerformance, [
            'command' => 'start', 'expected_version' => $firstPerformance->version,
        ]);

        $this->actingAs($second['admin'])->post('/tournaments/'.$second['tournament']->id.'/performances/'.$secondPerformance->id.'/command', [
            'command' => 'start', 'expected_version' => $secondPerformance->version,
        ])->assertSessionHasErrors('operation');

        $this->assertSame('pending', $secondPerformance->fresh()->status);
    }

    /** @param array{admin: User, tournament: Tournament, judges: Collection<int, User>} $fixture */
    private function approveBout(array $fixture, Bout $bout, int $winnerEntryId): void
    {
        $run = app(RunCompetition::class);

        foreach ($bout->performances()->orderBy('form_number')->orderBy('id')->get() as $performance) {
            $this->startScoring($fixture, $performance);
            $presentation = $performance->entry_id === $winnerEntryId ? '6.00' : '5.00';
            $this->scoreAll($fixture, $performance, '2.50', $presentation);
            $run->command($fixture['admin'], $fixture['tournament'], $performance, [
                'command' => 'approve', 'expected_version' => $performance->fresh()->version,
            ]);
            $this->assertSame('approved', $performance->fresh()->status);
            $this->assertNotNull($performance->result()->firstOrFail()->published_at);
        }
    }
}
