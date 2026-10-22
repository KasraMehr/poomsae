<?php

namespace Tests\Feature;

use App\Actions\CalculateScore;
use App\Actions\CompetitionConsole;
use App\Actions\CompetitionDisplay;
use App\Actions\CompetitionSetup;
use App\Actions\CompetitionTiming;
use App\Actions\CompetitionView;
use App\Actions\RunCompetition;
use App\Models\Bout;
use App\Models\Performance;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\CreatesCompetition;
use Tests\TestCase;

class CompetitionTimingTest extends TestCase
{
    use CreatesCompetition, RefreshDatabase;

    private function configure(array $fixture, array $overrides = []): void
    {
        $this->actingAs($fixture['admin'])->put(route('operations.category.timing', [$fixture['tournament'], $fixture['category']]), [
            'enabled' => true, 'minimum_duration_seconds' => 0, 'maximum_duration_seconds' => 90,
            'minimum_rest_seconds' => 30, 'time_penalty_hundredths' => 30, ...$overrides,
        ])->assertRedirect()->assertSessionHasNoErrors();
    }

    private function command(array $fixture, Performance $performance, string $command): void
    {
        app(RunCompetition::class)->command($fixture['admin'], $fixture['tournament'], $performance->fresh(), ['command' => $command, 'expected_version' => $performance->fresh()->version]);
    }

    #[TestWith([90, 0, 90, null, '8.500000'])]
    #[TestWith([91, 0, 90, 'too_long', '8.200000'])]
    #[TestWith([89, 90, 100, 'too_short', '8.200000'])]
    #[TestWith([90, 90, 100, null, '8.500000'])]
    #[TestWith([100, 90, 100, null, '8.500000'])]
    #[TestWith([101, 90, 100, 'too_long', '8.200000'])]
    public function test_duration_bounds_and_penalties_are_applied_to_preview_and_published_scores(int $seconds, int $minimum, int $maximum, ?string $violation, string $net): void
    {
        $this->freezeSecond();
        $fixture = $this->competition();
        $this->schedule($fixture);
        $this->configure($fixture, ['minimum_duration_seconds' => $minimum, 'maximum_duration_seconds' => $maximum]);
        $performance = Performance::orderBy('id')->firstOrFail();
        $this->command($fixture, $performance, 'start');
        $this->travel($seconds)->seconds();
        $this->command($fixture, $performance, 'finish');
        $this->scoreAll($fixture, $performance);
        $snapshot = app(CompetitionView::class)->snapshot($fixture['tournament']->fresh(), $fixture['admin'], true);
        $row = $snapshot['categories'][0]['rounds'][0]['bouts'][0]['performances'][0];
        $this->assertSame($net, $row['preview_result']);
        $this->assertSame($seconds, $row['timing']['duration_seconds']);
        $this->assertSame($violation, $row['timing']['violation']);
        $this->command($fixture, $performance, 'approve');
        $result = $performance->result()->firstOrFail();
        $this->assertSame($net, $result->score);
        $this->assertSame('8.500000', $result->calculation_snapshot['gross_score']);
        $this->assertSame('2.500000', $result->calculation_snapshot['components']['accuracy']['mean']);
        $this->assertSame($violation === null ? 0 : 30, $result->calculation_snapshot['timing']['penalty_hundredths']);
        $this->assertSame($violation === null ? 8500000 : 8200000, app(CalculateScore::class)->restoredMeanMicros($result->calculation_snapshot));
    }

    public function test_rest_is_enforced_by_server_and_expires_exactly_at_deadline(): void
    {
        $this->freezeSecond();
        $fixture = $this->competition(5, 2, 'round_robin');
        $this->schedule($fixture);
        $this->configure($fixture);
        $performance = Performance::orderBy('id')->firstOrFail();
        $this->startScoring($fixture, $performance);
        $endedAt = $performance->fresh()->ended_at;
        $this->travel(10)->seconds();
        $this->scoreAll($fixture, $performance);
        $this->command($fixture, $performance, 'approve');
        $next = Performance::where('entry_id', $performance->entry_id)->where('form_number', 2)->firstOrFail();
        $console = app(CompetitionConsole::class)->snapshot($fixture['tournament']->fresh(), $fixture['admin']);
        $this->assertSame($next->id, $console['courts'][0]['queue'][0]['id']);
        $this->assertSame($endedAt->copy()->addSeconds(30)->toISOString(), $console['courts'][0]['queue'][0]['rest_ready_at']);
        $this->travel(19)->seconds();
        $url = route('operations.command', [$fixture['tournament'], $next]);
        $this->post($url, ['command' => 'start', 'expected_version' => 1, 'rest_ready_at' => '2000-01-01'])->assertSessionHasErrors('operation');
        $this->assertSame('pending', $next->fresh()->status);
        $this->travel(1)->seconds();
        $this->post($url, ['command' => 'start', 'expected_version' => 1])->assertSessionHasNoErrors();
        $this->assertSame('running', $next->fresh()->status);
    }

    public function test_another_athlete_can_run_during_the_first_athletes_rest(): void
    {
        $this->freezeSecond();
        $fixture = $this->competition();
        $this->schedule($fixture);
        $this->configure($fixture);
        $performances = Performance::orderBy('form_number')->orderBy('id')->get();
        $this->startScoring($fixture, $performances[0]);
        $this->scoreAll($fixture, $performances[0]);
        $this->command($fixture, $performances[0], 'approve');
        $this->command($fixture, $performances[1], 'start');
        $this->assertSame('running', $performances[1]->fresh()->status);
    }

    public function test_simultaneous_start_is_atomic_when_one_opponent_still_needs_rest(): void
    {
        $this->freezeSecond();
        $fixture = $this->competition();
        $fixture['category']->update(['execution_mode' => 'simultaneous']);
        $this->schedule($fixture);
        $this->configure($fixture);
        $firstForms = Performance::where('form_number', 1)->orderBy('id')->get();
        $this->startScoring($fixture, $firstForms[0]);
        foreach ($firstForms as $performance) {
            $this->scoreAll($fixture, $performance);
            $this->command($fixture, $performance, 'approve');
        }
        $firstForms[0]->update(['ended_at' => now()->subMinute(), 'timing_snapshot' => [...$firstForms[0]->fresh()->timing_snapshot, 'ended_at_milliseconds' => now()->subMinute()->getTimestampMs()]]);
        $secondForms = Performance::where('form_number', 2)->orderBy('id')->get();
        $this->post(route('operations.command', [$fixture['tournament'], $secondForms[0]]), ['command' => 'start', 'expected_version' => 1])->assertSessionHasErrors('operation');
        foreach ($secondForms as $performance) {
            $this->assertSame('pending', $performance->fresh()->status);
            $this->assertNull($performance->fresh()->started_at);
        }
        $console = app(CompetitionConsole::class)->snapshot($fixture['tournament']->fresh(), $fixture['admin']);
        $this->assertSame(now()->addSeconds(30)->toISOString(), $console['courts'][0]['queue'][0]['rest_ready_at']);
        $this->travel(30)->seconds();
        $this->command($fixture, $secondForms[0], 'start');
        $this->assertEquals($secondForms[0]->fresh()->started_at, $secondForms[1]->fresh()->started_at);
    }

    public function test_a_score_correction_keeps_the_same_time_penalty(): void
    {
        $this->freezeSecond();
        $fixture = $this->competition(3, 2, 'knockout', 0);
        $this->schedule($fixture);
        $this->configure($fixture);
        $performance = Performance::orderBy('id')->firstOrFail();
        $this->command($fixture, $performance, 'start');
        $this->travel(91)->seconds();
        $this->command($fixture, $performance, 'finish');
        $this->scoreAll($fixture, $performance);
        $this->command($fixture, $performance, 'approve');
        $sheet = $performance->scoreSheets()->whereHas('judgeAssignment', fn ($q) => $q->where('user_id', $fixture['judges'][0]->id))->firstOrFail();
        $this->actingAs($fixture['judges'][0])->post(route('judging.store', [$fixture['tournament'], $performance]), [
            'request_id' => (string) Str::uuid(), 'expected_version' => $performance->fresh()->version, 'expected_revision' => 1,
            ...$this->detailedScoreInput('3.00', '6.00'), 'reason' => 'اصلاح نمرهٔ ثبت‌شده',
        ])->assertSessionHasNoErrors();
        $this->assertSame(2, $sheet->fresh()->revision);
        $this->assertSame('8.366667', $performance->result()->firstOrFail()->score);
        $this->assertSame(30, $performance->result()->firstOrFail()->calculation_snapshot['timing']['penalty_hundredths']);
    }

    public function test_time_penalty_never_makes_a_score_negative(): void
    {
        $this->freezeSecond();
        $fixture = $this->competition();
        $this->schedule($fixture);
        $this->configure($fixture);
        $performance = Performance::orderBy('id')->firstOrFail();
        $this->command($fixture, $performance, 'start');
        $this->travel(91)->seconds();
        $this->command($fixture, $performance, 'finish');
        $this->scoreAll($fixture, $performance, '0.00', '0.00');
        $this->command($fixture, $performance, 'approve');
        $this->assertSame('0.000000', $performance->result()->firstOrFail()->score);
    }

    public function test_fractional_overtime_is_not_lost_when_database_timestamps_have_second_precision(): void
    {
        $this->freezeSecond();
        $fixture = $this->competition();
        $this->schedule($fixture);
        $this->configure($fixture);
        $performance = Performance::orderBy('id')->firstOrFail();
        $this->command($fixture, $performance, 'start');
        $this->travel(90001)->milliseconds();
        $this->command($fixture, $performance, 'finish');
        $this->assertSame(90001, $performance->fresh()->timing_snapshot['duration_milliseconds']);
        $this->assertSame('too_long', $performance->fresh()->timing_snapshot['violation']);
        $this->assertSame(now()->addSeconds(30)->toISOString(), app(CompetitionTiming::class)->readyAt($performance->fresh(), $fixture['category']->fresh())->toISOString());
    }

    public function test_settings_require_manager_permission_and_lock_after_start(): void
    {
        $fixture = $this->competition();
        $this->schedule($fixture);
        $url = route('operations.category.timing', [$fixture['tournament'], $fixture['category']]);
        $data = ['enabled' => true, 'minimum_duration_seconds' => 0, 'maximum_duration_seconds' => 90, 'minimum_rest_seconds' => 30, 'time_penalty_hundredths' => 30];
        $operator = User::factory()->create();
        $fixture['tournament']->users()->attach($operator, ['role' => 'operator']);
        $this->actingAs($operator)->put($url, $data)->assertForbidden();
        $this->actingAs($fixture['admin'])->put($url, [...$data, 'maximum_duration_seconds' => -1])->assertSessionHasErrors('maximum_duration_seconds');
        $this->put($url, [...$data, 'minimum_duration_seconds' => 100])->assertSessionHasErrors('maximum_duration_seconds');
        $this->put($url, [...$data, 'minimum_rest_seconds' => -1])->assertSessionHasErrors('minimum_rest_seconds');
        $this->configure($fixture);
        $this->assertDatabaseHas('audit_logs', ['action' => 'category.timing_updated', 'subject_id' => $fixture['category']->id]);
        $performance = Performance::orderBy('id')->firstOrFail();
        $this->command($fixture, $performance, 'start');
        $this->put($url, [...$data, 'maximum_duration_seconds' => 100])->assertSessionHasErrors('operation');
        $this->assertSame(90, $performance->fresh()->timing_snapshot['maximum_duration_seconds']);
        $this->assertSame(90, $fixture['category']->fresh()->maximum_duration_seconds);
    }

    public function test_disabled_limits_and_zero_rest_preserve_existing_flow(): void
    {
        $fixture = $this->competition(5, 2, 'round_robin');
        $this->schedule($fixture);
        $this->configure($fixture, ['enabled' => false, 'minimum_rest_seconds' => 0]);
        $performance = Performance::orderBy('id')->firstOrFail();
        $this->startScoring($fixture, $performance);
        $this->scoreAll($fixture, $performance);
        $this->command($fixture, $performance, 'approve');
        $this->assertNull($performance->fresh()->timing_snapshot['violation']);
        $this->assertSame('8.500000', $performance->result()->firstOrFail()->score);
        $next = Performance::where('entry_id', $performance->entry_id)->where('form_number', 2)->firstOrFail();
        $this->command($fixture, $next, 'start');
        $this->assertSame('running', $next->fresh()->status);
    }

    public function test_timing_cannot_be_changed_through_another_tournaments_url(): void
    {
        $first = $this->competition();
        $second = $this->competition();
        $this->actingAs($first['admin'])->put(route('operations.category.timing', [$first['tournament'], $second['category']]), [
            'enabled' => true, 'minimum_duration_seconds' => 0, 'maximum_duration_seconds' => 120, 'minimum_rest_seconds' => 45, 'time_penalty_hundredths' => 30,
        ])->assertNotFound();
        $this->assertSame(90, $second['category']->fresh()->maximum_duration_seconds);
        $this->assertSame(30, $second['category']->fresh()->minimum_rest_seconds);
    }

    public function test_live_timing_provides_countdown_remaining_time_and_elapsed_display(): void
    {
        $this->freezeSecond();
        $fixture = $this->competition();
        $this->schedule($fixture);
        $performance = Performance::orderBy('id')->firstOrFail();

        $this->command($fixture, $performance, 'start');
        $this->travel(1)->seconds();

        $snapshot = app(CompetitionView::class)->snapshot($fixture['tournament']->fresh(), $fixture['admin'], true);
        $row = $snapshot['categories'][0]['rounds'][0]['bouts'][0]['performances'][0];

        $liveTiming = $row['live_timing'];
        $this->assertNotNull($liveTiming);
        $this->assertSame('running', $liveTiming['status']);
        $this->assertSame(90, $liveTiming['duration_seconds']);
        $this->assertSame(1, $liveTiming['elapsed_seconds']);
        $this->assertSame('00:01', $liveTiming['elapsed_formatted']);
        $this->assertSame(89, $liveTiming['remaining_seconds']);
        $this->assertSame('01:29', $liveTiming['remaining_formatted']);
        $this->assertFalse($liveTiming['is_overtime']);
        $this->assertSame(1, $row['form_badge']);

        $display = app(CompetitionDisplay::class)->snapshot($fixture['tournament']->fresh(), $fixture['admin']);
        $court = $display['courts'][0];
        $this->assertSame('running', $court['current']['status']);
        $this->assertSame('01:29', $court['current']['live_timing']['remaining_formatted']);
        $this->assertSame(89, $court['current']['live_timing']['remaining_seconds']);
        $this->assertSame(1, $court['current']['form_badge']);
    }

    public function test_late_arrival_after_rest_does_not_deduct_points_from_athlete_score(): void
    {
        $this->freezeSecond();
        $fixture = $this->competition(5, 2, 'knockout');
        $this->schedule($fixture);
        $this->configure($fixture, [
            'minimum_rest_seconds' => 30,
            'minimum_duration_seconds' => 0,
            'maximum_duration_seconds' => 90,
            'time_penalty_hundredths' => 30,
        ]);

        $bout = Bout::with(['performances', 'entries'])->firstOrFail();
        $firstForms = $bout->performances()->where('form_number', 1)->orderBy('id')->get();
        foreach ($firstForms as $form) {
            $this->startScoring($fixture, $form);
            $this->scoreAll($fixture, $form);
            $this->command($fixture, $form, 'approve');
        }

        // Rest period expires (30 seconds), and athlete arrives 10 minutes late (600 seconds later)
        $this->travel(630)->seconds();

        $secondForms = $bout->performances()->where('form_number', 2)->orderBy('id')->get();
        $secondFormAthlete1 = $secondForms[0];
        $this->assertSame('pending', $secondFormAthlete1->status);

        // Operator starts form 2
        $this->command($fixture, $secondFormAthlete1, 'start');
        $this->assertSame('running', $secondFormAthlete1->fresh()->status);

        // Athlete executes form 2 in 60 seconds (within 0 - 90s range)
        $this->travel(60)->seconds();
        $this->command($fixture, $secondFormAthlete1, 'finish');
        $this->assertSame('scoring', $secondFormAthlete1->fresh()->status);
        $this->assertNull($secondFormAthlete1->fresh()->timing_snapshot['violation']);
        $this->assertSame(0, $secondFormAthlete1->fresh()->timing_snapshot['penalty_hundredths']);

        // Score is submitted and approved
        $this->scoreAll($fixture, $secondFormAthlete1);
        $this->command($fixture, $secondFormAthlete1, 'approve');

        $result = $secondFormAthlete1->fresh()->result;
        $this->assertNotNull($result);
        $this->assertSame(0, $result->calculation_snapshot['timing']['penalty_hundredths'] ?? 0);
        $this->assertSame($result->calculation_snapshot['gross_score'], $result->score);
    }

    public function test_athlete_who_does_not_show_up_after_rest_results_in_opponent_walkover_win(): void
    {
        $this->freezeSecond();
        $fixture = $this->competition(5, 2, 'knockout');
        $this->schedule($fixture);
        $this->configure($fixture, [
            'minimum_rest_seconds' => 30,
        ]);

        $bout = Bout::with(['performances', 'entries'])->firstOrFail();
        $entries = $bout->entries;
        $presentAthlete = $entries[0];
        $absentAthlete = $entries[1];

        // Athletes complete form 1
        $firstForms = $bout->performances()->where('form_number', 1)->orderBy('id')->get();
        foreach ($firstForms as $form) {
            $this->startScoring($fixture, $form);
            $this->scoreAll($fixture, $form);
            $this->command($fixture, $form, 'approve');
        }

        // Rest time elapses, but absent athlete never shows up for form 2
        $this->travel(120)->seconds();

        // Operator declares opponent win via walkover
        app(RunCompetition::class)->resolve($fixture['admin'], $fixture['tournament'], $bout, [
            'decision_type' => 'walkover',
            'winner_entry_id' => $presentAthlete->id,
            'reason' => 'عدم حضور ورزشکار در زمان مقرر پس از استراحت',
            'confirmed' => true,
        ]);

        $bout->refresh();
        $this->assertSame('completed', $bout->status);
        $this->assertSame($presentAthlete->id, $bout->winner_entry_id);
        $this->assertStringContainsString('عدم حضور', $bout->resolution_reason);

        // Verify remaining pending performances for form 2 are cancelled
        $secondForms = $bout->performances()->where('form_number', 2)->get();
        foreach ($secondForms as $form) {
            $this->assertSame('cancelled', $form->status);
        }
    }

    public function test_category_defaults_to_standard_and_freestyle_timing_rules(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $tournament = Tournament::factory()->create(['created_by' => $admin->id]);
        $setup = app(CompetitionSetup::class);

        // Standard Recognized Poomsae: max 90s, rest 30s
        $recognized = $setup->category($admin, $tournament, [
            'name' => 'استاندارد', 'discipline' => 'recognized', 'gender' => 'male',
            'minimum_age' => 10, 'maximum_age' => 30, 'format' => 'knockout', 'judge_count' => 5,
            'discard_each_end' => 1, 'rules_acknowledged' => true, 'form_names' => ['Taegeuk 1', 'Taegeuk 2'],
        ]);
        $this->assertNull($recognized->minimum_duration_seconds);
        $this->assertSame(90, $recognized->maximum_duration_seconds);
        $this->assertSame(30, $recognized->minimum_rest_seconds);
        $this->assertSame(30, $recognized->time_penalty_hundredths);

        // Freestyle Poomsae: 90s to 100s, rest 30s
        $freestyle = $setup->category($admin, $tournament, [
            'name' => 'فری‌استایل', 'discipline' => 'freestyle', 'gender' => 'male',
            'minimum_age' => 10, 'maximum_age' => 30, 'format' => 'knockout', 'judge_count' => 5,
            'discard_each_end' => 1, 'rules_acknowledged' => true, 'planned_round_count' => 1,
        ]);
        $this->assertSame(90, $freestyle->minimum_duration_seconds);
        $this->assertSame(100, $freestyle->maximum_duration_seconds);
        $this->assertSame(30, $freestyle->minimum_rest_seconds);
        $this->assertSame(30, $freestyle->time_penalty_hundredths);
    }

    public function test_manager_can_customize_timing_when_creating_or_updating_category(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $tournament = Tournament::factory()->create(['created_by' => $admin->id]);
        $setup = app(CompetitionSetup::class);

        // Manager overrides defaults at creation
        $category = $setup->category($admin, $tournament, [
            'name' => 'سفارشی', 'discipline' => 'recognized', 'gender' => 'male',
            'minimum_age' => 10, 'maximum_age' => 30, 'format' => 'knockout', 'judge_count' => 5,
            'discard_each_end' => 1, 'rules_acknowledged' => true, 'form_names' => ['Taegeuk 1', 'Taegeuk 2'],
            'minimum_duration_seconds' => 30, 'maximum_duration_seconds' => 75,
            'minimum_rest_seconds' => 45, 'time_penalty_hundredths' => 50,
        ]);
        $this->assertSame(30, $category->minimum_duration_seconds);
        $this->assertSame(75, $category->maximum_duration_seconds);
        $this->assertSame(45, $category->minimum_rest_seconds);
        $this->assertSame(50, $category->time_penalty_hundredths);
    }

    public function test_referee_or_operator_can_waive_timing_penalty_restoring_deducted_score(): void
    {
        $this->freezeSecond();
        $fixture = $this->competition();
        $this->schedule($fixture);
        $this->configure($fixture, ['minimum_duration_seconds' => 0, 'maximum_duration_seconds' => 90]);
        $performance = Performance::orderBy('id')->firstOrFail();

        // Run 95 seconds (overtime penalty of 0.30 applied)
        $this->command($fixture, $performance, 'start');
        $this->travel(95)->seconds();
        $this->command($fixture, $performance, 'finish');
        $this->scoreAll($fixture, $performance);

        $this->assertSame(30, $performance->fresh()->timing_snapshot['penalty_hundredths']);
        $this->assertSame('too_long', $performance->fresh()->timing_snapshot['violation']);

        // Waive penalty before approval (setting penalty to 0)
        $url = route('operations.performance.timing-penalty', [$fixture['tournament'], $performance]);
        $response = $this->actingAs($fixture['admin'])->post($url, [
            'penalty_hundredths' => 0,
            'violation' => 'waived',
            'reason' => 'داور تشخیص داد که تاخیر به دلیل مشکل فنی سیستم بوده است.',
            'expected_version' => $performance->fresh()->version,
        ]);
        $response->assertRedirect()->assertSessionHasNoErrors();

        $snapshot = $performance->fresh()->timing_snapshot;
        $this->assertSame(0, $snapshot['penalty_hundredths']);
        $this->assertSame('waived', $snapshot['violation']);
        $this->assertSame(30, $snapshot['original_penalty_hundredths']);

        // Now approve performance and verify restored score (8.500000 instead of 8.200000)
        $this->command($fixture, $performance, 'approve');
        $result = $performance->result()->firstOrFail();
        $this->assertSame('8.500000', $result->score);
        $this->assertSame(0, $result->calculation_snapshot['timing']['penalty_hundredths']);
    }

    public function test_referee_can_adjust_timing_penalty_after_approval_recalculating_result(): void
    {
        $this->freezeSecond();
        $fixture = $this->competition();
        $this->schedule($fixture);
        $this->configure($fixture, ['minimum_duration_seconds' => 0, 'maximum_duration_seconds' => 90]);
        $performance = Performance::orderBy('id')->firstOrFail();

        // 95 seconds -> 30 hundredths penalty
        $this->command($fixture, $performance, 'start');
        $this->travel(95)->seconds();
        $this->command($fixture, $performance, 'finish');
        $this->scoreAll($fixture, $performance);
        $this->command($fixture, $performance, 'approve');

        $result = $performance->result()->firstOrFail();
        $this->assertSame('8.200000', $result->score);

        // Operator waives penalty on approved performance -> score increases back to 8.500000
        $url = route('operations.performance.timing-penalty', [$fixture['tournament'], $performance]);
        $response = $this->actingAs($fixture['admin'])->post($url, [
            'penalty_hundredths' => 0,
            'violation' => 'waived',
            'reason' => 'بازگشت نمره کسر شده پس از بازبینی فیلم توسط سرداور',
            'expected_version' => $performance->fresh()->version,
        ]);
        $response->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('8.500000', $performance->result()->firstOrFail()->score);

        // Operator increases penalty (e.g. 50 hundredths = 0.50) -> score decreases to 8.000000
        $response = $this->actingAs($fixture['admin'])->post($url, [
            'penalty_hundredths' => 50,
            'violation' => 'too_long',
            'reason' => 'اعمال جریمه تاخیر مضاعف پس از بررسی هیئت ژوری',
            'expected_version' => $performance->fresh()->version,
        ]);
        $response->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('8.000000', $performance->result()->firstOrFail()->score);
        $this->assertSame(50, $performance->result()->firstOrFail()->calculation_snapshot['timing']['penalty_hundredths']);
    }
}
