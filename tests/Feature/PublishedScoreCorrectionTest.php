<?php

namespace Tests\Feature;

use App\Actions\CompetitionView;
use App\Actions\RunCompetition;
use App\Models\Bout;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\CreatesCompetition;
use Tests\TestCase;

class PublishedScoreCorrectionTest extends TestCase
{
    use CreatesCompetition, RefreshDatabase;

    private function publishedCompetition(int $entryCount = 2, string $format = 'knockout'): array
    {
        $fixture = $this->competition(3, $entryCount, $format, 0);
        $this->schedule($fixture);
        $run = app(RunCompetition::class);
        foreach (Bout::orderBy('id')->get() as $bout) {
            $winner = $bout->entries()->orderBy('entries.id')->firstOrFail()->id;
            foreach ($bout->performances()->orderBy('form_number')->orderBy('id')->get() as $performance) {
                $this->startScoring($fixture, $performance);
                $this->scoreAll($fixture, $performance, $performance->entry_id === $winner ? '4.00' : '3.90', '6.00');
                $run->command($fixture['admin'], $fixture['tournament'], $performance, ['command' => 'approve', 'expected_version' => $performance->fresh()->version]);
            }
        }
        $bout = Bout::orderBy('id')->firstOrFail();
        $performance = $bout->performances()->where('entry_id', $bout->winner_entry_id ?? $bout->entries()->firstOrFail()->id)->orderBy('form_number')->firstOrFail();
        $sheet = $performance->scoreSheets()->whereHas('judgeAssignment', fn ($query) => $query->where('user_id', $fixture['judges'][0]->id))->firstOrFail();
        $operator = User::factory()->create();
        $fixture['tournament']->users()->attach($operator, ['role' => 'operator']);

        return $fixture + compact('bout', 'performance', 'sheet', 'operator');
    }

    private function correction(array $fixture): array
    {
        return [
            'request_id' => (string) Str::uuid(), 'judge_assignment_id' => $fixture['sheet']->judge_assignment_id,
            'expected_version' => $fixture['performance']->fresh()->version, 'expected_revision' => $fixture['sheet']->fresh()->revision,
            ...$this->detailedScoreInput('1.00', '6.00'), 'reason' => 'اصلاح اشتباه ثبت نمرهٔ نهایی',
        ];
    }

    public function test_operator_corrects_a_published_score_and_recalculates_the_winner_with_history(): void
    {
        $fixture = $this->publishedCompetition();
        $oldWinner = $fixture['bout']->winner_entry_id;
        $oldVersion = $fixture['performance']->version;
        $payload = $this->correction($fixture);
        $endpoint = route('operations.proxy-score', [$fixture['tournament'], $fixture['performance']]);

        $this->actingAs($fixture['operator'])->post($endpoint, $payload)->assertSessionHasNoErrors();

        $this->assertSame('9.000000', $fixture['performance']->result()->firstOrFail()->score);
        $this->assertSame('approved', $fixture['performance']->fresh()->status);
        $this->assertSame($oldVersion + 1, $fixture['performance']->fresh()->version);
        $this->assertNotSame($oldWinner, $fixture['bout']->fresh()->winner_entry_id);
        $this->assertSame($fixture['operator']->id, $fixture['sheet']->fresh()->confirmed_by);
        $this->assertDatabaseHas('score_revisions', ['score_sheet_id' => $fixture['sheet']->id, 'revision' => 2, 'changed_by' => $fixture['operator']->id, 'reason' => $payload['reason']]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'result.corrected', 'user_id' => $fixture['operator']->id]);
        $this->post($endpoint, $payload)->assertSessionHasNoErrors();
        $this->assertSame(2, $fixture['sheet']->fresh()->revision);
        $this->assertSame($oldVersion + 1, $fixture['performance']->fresh()->version);
    }

    public function test_assigned_judge_corrects_their_published_score_with_reason(): void
    {
        $fixture = $this->publishedCompetition();
        $payload = $this->correction($fixture);
        unset($payload['judge_assignment_id']);

        $this->actingAs($fixture['judges'][0])->post(route('judging.store', [$fixture['tournament'], $fixture['performance']]), $payload)->assertSessionHasNoErrors();

        $this->assertSame('9.000000', $fixture['performance']->result()->firstOrFail()->score);
        $this->assertSame(2, $fixture['sheet']->fresh()->revision);
        $this->assertDatabaseHas('score_revisions', ['score_sheet_id' => $fixture['sheet']->id, 'revision' => 2, 'changed_by' => $fixture['judges'][0]->id, 'reason' => $payload['reason']]);
    }

    public function test_correction_remains_available_after_tournament_completion(): void
    {
        $fixture = $this->publishedCompetition();
        app(RunCompetition::class)->complete($fixture['admin'], $fixture['tournament']);

        $this->actingAs($fixture['operator'])->post(route('operations.proxy-score', [$fixture['tournament'], $fixture['performance']]), $this->correction($fixture))->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('completed', $fixture['tournament']->fresh()->status);
        $this->assertSame('9.000000', $fixture['performance']->result()->firstOrFail()->score);
    }

    public function test_correction_that_creates_a_tie_reopens_the_bout_for_a_recorded_decision(): void
    {
        $fixture = $this->publishedCompetition();
        app(RunCompetition::class)->complete($fixture['admin'], $fixture['tournament']);
        $payload = [...$this->correction($fixture), ...$this->detailedScoreInput('3.40', '6.00')];

        $this->actingAs($fixture['operator'])->post(route('operations.proxy-score', [$fixture['tournament'], $fixture['performance']]), $payload)->assertSessionHasNoErrors();

        $this->assertNull($fixture['bout']->fresh()->winner_entry_id);
        $this->assertSame('running', $fixture['bout']->fresh()->status);
        $this->assertSame('running', $fixture['tournament']->fresh()->status);
        $this->post(route('operations.resolve', [$fixture['tournament'], $fixture['bout']]), [
            'winner_entry_id' => $fixture['performance']->entry_id, 'reason' => 'قرار ثبت‌شدهٔ اپراتور برای رفع تساوی',
        ])->assertSessionHasNoErrors();
        $this->assertSame('completed', $fixture['bout']->fresh()->status);
    }

    public function test_changed_winner_replaces_the_entrant_in_an_unstarted_next_round(): void
    {
        $fixture = $this->publishedCompetition(4);
        $this->schedule($fixture);
        $nextRound = $fixture['category']->rounds()->where('sequence', 2)->firstOrFail();
        $oldWinner = $fixture['bout']->winner_entry_id;
        $this->assertSame('10.000000', $fixture['performance']->result()->firstOrFail()->score);
        $this->assertSame($oldWinner, $fixture['performance']->entry_id);

        $this->actingAs($fixture['operator'])->post(route('operations.proxy-score', [$fixture['tournament'], $fixture['performance']]), $this->correction($fixture))->assertRedirect()->assertSessionHasNoErrors();

        $nextBout = $nextRound->bouts()->firstOrFail();
        $newWinner = $fixture['bout']->fresh()->winner_entry_id;
        $this->assertSame('9.000000', $fixture['performance']->result()->firstOrFail()->score);
        $this->assertSame(2, $fixture['sheet']->fresh()->revision);
        $this->assertNotSame($oldWinner, $newWinner);
        $this->assertFalse($nextBout->entries()->whereKey($oldWinner)->exists());
        $this->assertTrue($nextBout->entries()->whereKey($newWinner)->exists());
        $this->assertSame(2, $nextBout->performances()->where('entry_id', $newWinner)->count());
        $this->assertSame(0, $nextBout->performances()->where('entry_id', $oldWinner)->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'bout.advancement_corrected', 'subject_id' => $nextBout->id]);
    }

    public function test_changed_winner_preserves_a_started_next_round_and_flags_the_difference(): void
    {
        $fixture = $this->publishedCompetition(4);
        $this->schedule($fixture);
        $nextRound = $fixture['category']->rounds()->where('sequence', 2)->firstOrFail();
        $nextBout = $nextRound->bouts()->firstOrFail();
        $nextPerformance = $nextBout->performances()->orderBy('form_number')->orderBy('id')->firstOrFail();
        $this->startScoring($fixture, $nextPerformance);
        $this->scoreAll($fixture, $nextPerformance);
        app(RunCompetition::class)->command($fixture['admin'], $fixture['tournament'], $nextPerformance, ['command' => 'approve', 'expected_version' => $nextPerformance->fresh()->version]);
        $entries = $nextBout->entries()->pluck('entries.id')->all();
        $score = $nextPerformance->result()->firstOrFail()->score;

        $this->actingAs($fixture['operator'])->post(route('operations.proxy-score', [$fixture['tournament'], $fixture['performance']]), $this->correction($fixture))->assertSessionHasNoErrors();

        $this->assertSame($entries, $nextBout->entries()->pluck('entries.id')->all());
        $this->assertSame($score, $nextPerformance->result()->firstOrFail()->score);
        $snapshot = app(CompetitionView::class)->snapshot($fixture['tournament'], $fixture['operator']);
        $this->assertTrue($snapshot['categories'][0]['rounds'][1]['advancement_review_required']);
        $remaining = $nextBout->performances()->where('status', 'pending')->orderBy('form_number')->orderBy('id')->firstOrFail();
        $this->startScoring($fixture, $remaining);
        $this->assertSame('scoring', $remaining->fresh()->status);
    }

    public function test_correcting_an_earlier_round_preserves_the_completed_final(): void
    {
        $fixture = $this->publishedCompetition(4);
        $this->schedule($fixture);
        $final = $fixture['category']->rounds()->where('sequence', 2)->firstOrFail()->bouts()->firstOrFail();
        $winner = $final->entries()->firstOrFail()->id;
        $run = app(RunCompetition::class);
        foreach ($final->performances()->orderBy('form_number')->orderBy('id')->get() as $performance) {
            $this->startScoring($fixture, $performance);
            $this->scoreAll($fixture, $performance, $performance->entry_id === $winner ? '4.00' : '3.90', '6.00');
            $run->command($fixture['admin'], $fixture['tournament'], $performance, ['command' => 'approve', 'expected_version' => $performance->fresh()->version]);
        }
        $run->complete($fixture['admin'], $fixture['tournament']);
        $results = $final->performances()->with('result')->get()->pluck('result.calculation_snapshot', 'id')->all();

        $this->actingAs($fixture['operator'])->post(route('operations.proxy-score', [$fixture['tournament'], $fixture['performance']]), $this->correction($fixture))->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame($winner, $final->fresh()->winner_entry_id);
        $this->assertSame($results, $final->performances()->with('result')->get()->pluck('result.calculation_snapshot', 'id')->all());
        $this->assertSame('completed', $fixture['tournament']->fresh()->status);
        $snapshot = app(CompetitionView::class)->snapshot($fixture['tournament'], $fixture['operator']);
        $this->assertTrue($snapshot['categories'][0]['rounds'][1]['advancement_review_required']);
    }

    public function test_round_robin_correction_updates_published_standings(): void
    {
        $fixture = $this->publishedCompetition(2, 'round_robin');
        $entryId = $fixture['performance']->entry_id;

        $this->actingAs($fixture['operator'])->post(route('operations.proxy-score', [$fixture['tournament'], $fixture['performance']]), $this->correction($fixture))->assertRedirect()->assertSessionHasNoErrors();

        $snapshot = app(CompetitionView::class)->snapshot($fixture['tournament'], $fixture['operator']);
        $standing = collect($snapshot['categories'][0]['standings'])->firstWhere('id', $entryId);
        $this->assertSame('19.000000', $standing['score']);
        $this->assertSame(2, $standing['rank']);
        $this->assertSame('completed', $fixture['bout']->fresh()->status);
    }

    #[TestWith([''])]
    #[TestWith(['کوتاه'])]
    public function test_published_correction_rejects_a_missing_or_short_reason(string $reason): void
    {
        $fixture = $this->publishedCompetition();

        $this->actingAs($fixture['operator'])->post(route('operations.proxy-score', [$fixture['tournament'], $fixture['performance']]), [...$this->correction($fixture), 'reason' => $reason])->assertSessionHasErrors('reason');

        $this->assertSame(1, $fixture['sheet']->fresh()->revision);
        $this->assertSame('10.000000', $fixture['performance']->result()->firstOrFail()->score);
    }

    public function test_published_correction_rejects_stale_revisions_and_unauthorized_users(): void
    {
        $fixture = $this->publishedCompetition();
        $endpoint = route('operations.proxy-score', [$fixture['tournament'], $fixture['performance']]);
        $payload = $this->correction($fixture);
        $outsider = User::factory()->create();

        $this->actingAs($outsider)->post($endpoint, $payload)->assertForbidden();
        $this->actingAs($fixture['judges'][0])->post($endpoint, $payload)->assertForbidden();
        $this->actingAs($fixture['operator'])->post($endpoint, [...$payload, 'expected_revision' => 0])->assertConflict();
        $this->post($endpoint, [...$payload, 'expected_version' => 1])->assertConflict();

        $this->assertSame(1, $fixture['sheet']->fresh()->revision);
        $this->assertSame('10.000000', $fixture['performance']->result()->firstOrFail()->score);
    }
}
