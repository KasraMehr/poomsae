<?php

namespace Tests\Feature;

use App\Actions\CompetitionView;
use App\Actions\DrawRoundForms;
use App\Actions\RunCompetition;
use App\Actions\ScheduleRound;
use App\Models\AuditLog;
use App\Models\Bout;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\CreatesCompetition;
use Tests\TestCase;

class PublishedScoreCorrectionTest extends TestCase
{
    use CreatesCompetition, RefreshDatabase;

    private function publishedCompetition(int $entryCount = 2, string $format = 'knockout', ?int $plannedRoundCount = null): array
    {
        $fixture = $this->competition(3, $entryCount, $format, 0, $plannedRoundCount);
        if ($plannedRoundCount !== null) {
            app(DrawRoundForms::class)->handle($fixture['admin'], $fixture['tournament'], $fixture['category']);
        }
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
        $snapshot = app(CompetitionView::class)->snapshot($fixture['tournament']->fresh(), $fixture['operator']);
        $this->assertTrue($snapshot['categories'][0]['rounds'][0]['score_correction_open']);
    }

    public function test_correction_is_rejected_after_the_next_round_starts_without_changing_either_round(): void
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

        $this->actingAs($fixture['operator'])->post(route('operations.proxy-score', [$fixture['tournament'], $fixture['performance']]), $this->correction($fixture))
            ->assertSessionHasErrors(['operation' => 'با شروع مرحلهٔ بعد این رده، اصلاح نمرهٔ مرحلهٔ قبل مجاز نیست.']);

        $this->assertSame(1, $fixture['sheet']->fresh()->revision);
        $this->assertSame('10.000000', $fixture['performance']->result()->firstOrFail()->score);
        $this->assertSame($entries, $nextBout->entries()->pluck('entries.id')->all());
        $this->assertSame($score, $nextPerformance->result()->firstOrFail()->score);
        $snapshot = app(CompetitionView::class)->snapshot($fixture['tournament']->fresh(), $fixture['operator']);
        $this->assertFalse($snapshot['categories'][0]['rounds'][0]['score_correction_open']);
        $this->assertTrue($snapshot['categories'][0]['rounds'][1]['score_correction_open']);
        $this->assertFalse($snapshot['categories'][0]['rounds'][1]['advancement_review_required']);
        $remaining = $nextBout->performances()->where('status', 'pending')->orderBy('form_number')->orderBy('id')->firstOrFail();
        $this->startScoring($fixture, $remaining);
        $this->assertSame('scoring', $remaining->fresh()->status);
    }

    public function test_correcting_an_earlier_round_is_rejected_after_the_final_is_completed(): void
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

        $this->actingAs($fixture['operator'])->post(route('operations.proxy-score', [$fixture['tournament'], $fixture['performance']]), $this->correction($fixture))->assertRedirect()->assertSessionHasErrors('operation');

        $this->assertSame(1, $fixture['sheet']->fresh()->revision);
        $this->assertSame('10.000000', $fixture['performance']->result()->firstOrFail()->score);
        $this->assertSame($winner, $final->fresh()->winner_entry_id);
        $this->assertSame($results, $final->performances()->with('result')->get()->pluck('result.calculation_snapshot', 'id')->all());
        $this->assertSame('completed', $fixture['tournament']->fresh()->status);
        $snapshot = app(CompetitionView::class)->snapshot($fixture['tournament']->fresh(), $fixture['operator']);
        $this->assertFalse($snapshot['categories'][0]['rounds'][0]['score_correction_open']);
        $this->assertFalse($snapshot['categories'][0]['rounds'][1]['advancement_review_required']);
    }

    public function test_round_robin_correction_updates_published_standings(): void
    {
        $fixture = $this->publishedCompetition(2, 'round_robin');
        $entryId = $fixture['performance']->entry_id;

        $this->actingAs($fixture['operator'])->post(route('operations.proxy-score', [$fixture['tournament'], $fixture['performance']]), $this->correction($fixture))->assertRedirect()->assertSessionHasNoErrors();

        $snapshot = app(CompetitionView::class)->snapshot($fixture['tournament']->fresh(), $fixture['operator']);
        $standing = collect($snapshot['categories'][0]['standings'])->firstWhere('id', $entryId);
        $this->assertSame('19.000000', $standing['score']);
        $this->assertSame(2, $standing['rank']);
        $this->assertSame('completed', $fixture['bout']->fresh()->status);
    }

    #[TestWith(['1.00'])]
    #[TestWith(['3.90'])]
    public function test_judge_api_returns_422_after_next_round_start_even_if_the_winner_would_stay_the_same(string $accuracy): void
    {
        $fixture = $this->publishedCompetition(4);
        $this->schedule($fixture);
        $nextPerformance = $fixture['category']->rounds()->where('sequence', 2)->firstOrFail()->bouts()->firstOrFail()->performances()->orderBy('form_number')->orderBy('id')->firstOrFail();
        app(RunCompetition::class)->command($fixture['admin'], $fixture['tournament'], $nextPerformance, ['command' => 'start', 'expected_version' => $nextPerformance->version]);
        $auditCount = AuditLog::count();
        $revisionCount = DB::table('score_revisions')->count();
        Sanctum::actingAs($fixture['judges'][0], ['scores:write']);

        $this->postJson('/api/v1/tournaments/'.$fixture['tournament']->id.'/performances/'.$fixture['performance']->id.'/scores', [
            ...$this->correction($fixture), ...$this->detailedScoreInput($accuracy, '6.00'),
        ])->assertUnprocessable()->assertJsonValidationErrors('operation');

        $this->assertSame(1, $fixture['sheet']->fresh()->revision);
        $this->assertSame('10.000000', $fixture['performance']->result()->firstOrFail()->score);
        $this->assertSame($fixture['performance']->version, $fixture['performance']->fresh()->version);
        $this->assertDatabaseCount('audit_logs', $auditCount);
        $this->assertDatabaseCount('score_revisions', $revisionCount);
        $snapshot = app(CompetitionView::class)->snapshot($fixture['tournament']->fresh(), $fixture['judges'][0]);
        $this->assertFalse($snapshot['categories'][0]['rounds'][0]['score_correction_open']);
    }

    public function test_starting_the_next_round_on_the_fourth_court_locks_all_previous_round_scores(): void
    {
        $fixture = $this->publishedCompetition(16);
        $panels = [['court_id' => $fixture['tournament']->courts()->firstOrFail()->id, 'judge_ids' => $fixture['judges']->pluck('id')->all()]];
        for ($number = 2; $number <= 4; $number++) {
            $court = $fixture['tournament']->courts()->create(['name' => 'زمین '.$number]);
            $judges = User::factory()->count(3)->create();
            foreach ($judges as $judge) {
                $fixture['tournament']->users()->attach($judge->id, ['role' => 'judge']);
            }
            $panels[] = ['court_id' => $court->id, 'judge_ids' => $judges->pluck('id')->all()];
        }
        app(ScheduleRound::class)->handle($fixture['admin'], $fixture['tournament'], $fixture['category'], ['courts' => $panels]);
        $nextRound = $fixture['category']->rounds()->where('sequence', 2)->firstOrFail();
        $nextPerformance = $nextRound->bouts()->where('court_id', $panels[3]['court_id'])->firstOrFail()->performances()->orderBy('form_number')->orderBy('id')->firstOrFail();
        app(RunCompetition::class)->command($fixture['admin'], $fixture['tournament'], $nextPerformance, ['command' => 'start', 'expected_version' => $nextPerformance->version]);

        $this->actingAs($fixture['operator'])->post(route('operations.proxy-score', [$fixture['tournament'], $fixture['performance']]), $this->correction($fixture))->assertSessionHasErrors('operation');

        $this->assertSame(3, $nextRound->bouts()->where('status', 'pending')->count());
        $this->assertSame(1, $fixture['sheet']->fresh()->revision);
        $this->assertSame('10.000000', $fixture['performance']->result()->firstOrFail()->score);
    }

    public function test_a_later_round_in_another_category_does_not_close_score_correction(): void
    {
        $fixture = $this->publishedCompetition();
        $otherCategory = $fixture['category']->replicate();
        $otherCategory->name = 'ردهٔ دیگر';
        $otherCategory->save();
        $otherCategory->rounds()->create(['name' => 'مرحلهٔ دوم', 'sequence' => 2, 'status' => 'running', 'started_at' => now()]);

        $this->actingAs($fixture['operator'])->post(route('operations.proxy-score', [$fixture['tournament'], $fixture['performance']]), $this->correction($fixture))->assertSessionHasNoErrors();

        $this->assertSame(2, $fixture['sheet']->fresh()->revision);
        $this->assertSame('9.000000', $fixture['performance']->result()->firstOrFail()->score);
        $snapshot = app(CompetitionView::class)->snapshot($fixture['tournament']->fresh(), $fixture['operator']);
        $this->assertTrue($snapshot['categories'][0]['rounds'][0]['score_correction_open']);
    }

    public function test_score_based_correction_closes_when_the_next_planned_stage_starts(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 6)->setTime(12, 0));
        $fixture = $this->publishedCompetition(2, 'round_robin', 2);
        $this->schedule($fixture);
        $nextRound = $fixture['category']->rounds()->where('sequence', 2)->firstOrFail();
        $nextPerformance = $nextRound->bouts()->firstOrFail()->performances()->orderBy('form_number')->orderBy('id')->firstOrFail();
        app(RunCompetition::class)->command($fixture['admin'], $fixture['tournament'], $nextPerformance, ['command' => 'start', 'expected_version' => $nextPerformance->version]);

        $this->actingAs($fixture['judges'][0])->post(route('judging.store', [$fixture['tournament'], $fixture['performance']]), $this->correction($fixture))->assertSessionHasErrors('operation');

        $this->assertSame(1, $fixture['sheet']->fresh()->revision);
        $this->assertSame('10.000000', $fixture['performance']->result()->firstOrFail()->score);
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
