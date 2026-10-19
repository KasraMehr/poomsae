<?php

namespace Tests\Feature;

use App\Actions\CompetitionReports;
use App\Actions\CompetitionSetup;
use App\Actions\CompetitionView;
use App\Actions\ExportCompetitionResults;
use App\Actions\RunCompetition;
use App\Events\CompetitionDomainUpdated;
use App\Models\CompetitionAppeal;
use App\Models\Performance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\CreatesCompetition;
use Tests\TestCase;

class CompetitionAppealsTest extends TestCase
{
    use CreatesCompetition, RefreshDatabase;

    private function publishedFixture(int $entries = 2, bool $penalty = false): array
    {
        $this->freezeSecond();
        $fixture = $this->competition(3, $entries, 'knockout', 0);
        if ($penalty) {
            $fixture['category']->update(['maximum_duration_seconds' => 90]);
        }
        $this->schedule($fixture);
        $performance = Performance::whereHas('bout', fn ($query) => $query->where('category_id', $fixture['category']->id))->orderBy('id')->firstOrFail();
        $run = app(RunCompetition::class);
        $run->command($fixture['admin'], $fixture['tournament'], $performance, ['command' => 'start', 'expected_version' => 1]);
        if ($penalty) {
            $this->travel(91)->seconds();
        }
        $run->command($fixture['admin'], $fixture['tournament'], $performance, ['command' => 'finish', 'expected_version' => $performance->fresh()->version]);
        $this->scoreAll($fixture, $performance);
        $run->command($fixture['admin'], $fixture['tournament'], $performance, ['command' => 'approve', 'expected_version' => $performance->fresh()->version]);
        $operator = User::factory()->create();
        $fixture['tournament']->users()->attach($operator, ['role' => 'operator']);

        return [...$fixture, ...compact('performance', 'operator')];
    }

    private function fileAppeal(array $fixture): CompetitionAppeal
    {
        $this->actingAs($fixture['operator'])->post(route('appeals.store', $fixture['tournament']), [
            'performance_id' => $fixture['performance']->id, 'expected_version' => $fixture['performance']->fresh()->version,
            'representative_name' => 'نمایندهٔ تیم نمونه', 'reason' => 'اعتراض به اشتباه در ثبت نمرهٔ داور',
            'evidence' => 'ویدئوی اجرا و برگهٔ داوری موجود است.',
            'status' => 'applied', 'reviewed_by' => $fixture['operator']->id,
        ])->assertRedirect()->assertSessionHasNoErrors();

        return CompetitionAppeal::where('performance_id', $fixture['performance']->id)->firstOrFail();
    }

    private function review(array $fixture, CompetitionAppeal $appeal): void
    {
        $this->actingAs($fixture['operator'])->post(route('appeals.review', [$fixture['tournament'], $appeal]), ['expected_version' => $appeal->fresh()->version])->assertSessionHasNoErrors();
    }

    private function acceptedPayload(array $fixture, CompetitionAppeal $appeal): array
    {
        return [
            'expected_version' => $appeal->fresh()->version, 'decision' => 'accepted', 'decision_reason' => 'ثبت اشتباه نمره پس از بررسی برگه‌های داوری تأیید شد.',
            'corrections' => $fixture['performance']->scoreSheets()->orderBy('id')->get()->map(fn ($sheet): array => [
                'judge_assignment_id' => $sheet->judge_assignment_id, 'expected_revision' => $sheet->revision, ...$this->detailedScoreInput('0.00', '6.00'),
            ])->all(),
        ];
    }

    public function test_operator_files_an_appeal_with_immutable_result_snapshot_and_no_result_change(): void
    {
        $fixture = $this->publishedFixture();
        $appeal = $this->fileAppeal($fixture);
        $this->assertSame('submitted', $appeal->status);
        $this->assertNull($appeal->reviewed_by);
        $this->assertSame('8.500000', $appeal->original_snapshot['result']);
        $this->assertCount(3, $appeal->original_snapshot['scores']);
        $this->assertSame('8.500000', $fixture['performance']->result()->firstOrFail()->score);
        $this->assertDatabaseHas('audit_logs', ['action' => 'appeal.filed', 'subject_id' => $appeal->id]);
        $snapshot = app(CompetitionView::class)->snapshot($fixture['tournament']->fresh(), $fixture['operator'], true);
        $this->assertTrue($snapshot['categories'][0]['rounds'][0]['bouts'][0]['performances'][0]['appeal_pending']);
        $this->assertSame('تحت بررسی اعتراض', app(ExportCompetitionResults::class)->table($snapshot)['rows'][0][14]);
        $this->get(route('appeals.index', $fixture['tournament']))->assertInertia(fn (AssertableInertia $page) => $page->component('Competition/Appeals')->has('appeals.data', 1)->where('appeals.data.0.status', 'submitted')->where('canFile', true));
    }

    public function test_operator_reviews_and_accepts_corrections_and_applies_them(): void
    {
        $fixture = $this->publishedFixture(penalty: true);
        $appeal = $this->fileAppeal($fixture);
        $this->review($fixture, $appeal);
        $payload = $this->acceptedPayload($fixture, $appeal);
        $this->post(route('appeals.decide', [$fixture['tournament'], $appeal]), $payload)->assertSessionHasNoErrors();
        $this->assertSame('accepted', $appeal->fresh()->status);
        $this->assertSame('5.700000', $appeal->fresh()->decision_snapshot['proposed_result']['score']);
        $this->assertSame('8.200000', $fixture['performance']->result()->firstOrFail()->score);
        $url = route('appeals.apply', [$fixture['tournament'], $appeal]);
        $this->actingAs($fixture['operator'])->post($url, ['expected_version' => 3, 'accuracy' => '4.00', 'corrections' => []])->assertSessionHasNoErrors();
        $this->assertSame('applied', $appeal->fresh()->status);
        $this->assertSame('5.700000', $fixture['performance']->result()->firstOrFail()->score);
        $this->assertSame('8.200000', $appeal->fresh()->original_snapshot['result']);
        $this->assertSame(30, $fixture['performance']->result()->firstOrFail()->calculation_snapshot['timing']['penalty_hundredths']);
        $this->assertSame([2, 2, 2], $fixture['performance']->scoreSheets()->pluck('revision')->all());
        $this->assertDatabaseHas('audit_logs', ['action' => 'appeal.applied', 'user_id' => $fixture['operator']->id]);
        $this->post($url, ['expected_version' => 3])->assertSessionHasNoErrors();
        $this->assertSame([2, 2, 2], $fixture['performance']->scoreSheets()->pluck('revision')->all());
        $this->assertSame(4, $appeal->fresh()->version);
    }

    public function test_rejection_preserves_result_and_allows_normal_correction_again(): void
    {
        $fixture = $this->publishedFixture();
        $appeal = $this->fileAppeal($fixture);
        $this->review($fixture, $appeal);
        $this->post(route('appeals.decide', [$fixture['tournament'], $appeal]), ['expected_version' => 2, 'decision' => 'rejected', 'decision_reason' => 'پس از بررسی، نمره‌های داوران صحیح بوده است.'])->assertSessionHasNoErrors();
        $this->assertSame('rejected', $appeal->fresh()->status);
        $this->assertSame('8.500000', $fixture['performance']->result()->firstOrFail()->score);
        $sheet = $fixture['performance']->scoreSheets()->firstOrFail();
        $this->actingAs($fixture['operator'])->post(route('operations.proxy-score', [$fixture['tournament'], $fixture['performance']]), [
            'request_id' => (string) Str::uuid(), 'judge_assignment_id' => $sheet->judge_assignment_id, 'expected_version' => $fixture['performance']->fresh()->version,
            'expected_revision' => 1, ...$this->detailedScoreInput('2.00', '6.00'), 'reason' => 'اصلاح اشتباه مستقل در ثبت نمره',
        ])->assertSessionHasNoErrors();
        $this->assertSame(2, $sheet->fresh()->revision);
    }

    public function test_open_appeal_blocks_both_judge_and_operator_correction(): void
    {
        $fixture = $this->publishedFixture();
        $this->fileAppeal($fixture);
        $sheet = $fixture['performance']->scoreSheets()->firstOrFail();
        $data = ['request_id' => (string) Str::uuid(), 'judge_assignment_id' => $sheet->judge_assignment_id, 'expected_version' => $fixture['performance']->fresh()->version,
            'expected_revision' => 1, ...$this->detailedScoreInput('0.00', '6.00'), 'reason' => 'درخواست اصلاح نمره با وجود اعتراض باز'];
        $this->actingAs($fixture['operator'])->post(route('operations.proxy-score', [$fixture['tournament'], $fixture['performance']]), $data)->assertSessionHasErrors('operation');
        unset($data['judge_assignment_id']);
        $this->actingAs($fixture['judges'][0])->post(route('judging.store', [$fixture['tournament'], $fixture['performance']]), $data)->assertSessionHasErrors('operation');
        $this->assertSame(1, $sheet->fresh()->revision);
    }

    public function test_judge_cannot_review_or_decide(): void
    {
        $role = 'judge';
        $fixture = $this->publishedFixture();
        $appeal = $this->fileAppeal($fixture);
        $user = User::factory()->create();
        $fixture['tournament']->users()->attach($user, ['role' => $role]);
        $this->actingAs($user)->post(route('appeals.review', [$fixture['tournament'], $appeal]), ['expected_version' => 1])->assertForbidden();
        $this->post(route('appeals.decide', [$fixture['tournament'], $appeal]), ['expected_version' => 1, 'decision' => 'rejected', 'decision_reason' => 'رأی بدون دسترسی مجاز نیست'])->assertForbidden();
    }

    public function test_independent_operator_can_review_and_decide(): void
    {
        $fixture = $this->publishedFixture();
        $appeal = $this->fileAppeal($fixture);
        $reviewOperator = User::factory()->create();
        $fixture['tournament']->users()->attach($reviewOperator, ['role' => 'operator']);
        $this->actingAs($reviewOperator)->post(route('appeals.review', [$fixture['tournament'], $appeal]), ['expected_version' => 1])->assertSessionHasNoErrors();
        $this->assertSame('reviewing', $appeal->fresh()->status);
        $this->assertSame($reviewOperator->id, $appeal->fresh()->reviewed_by);
    }

    public function test_filing_operator_can_review_and_a_judge_on_the_bout_cannot_review(): void
    {
        $fixture = $this->publishedFixture();
        $appeal = $this->fileAppeal($fixture);

        // Submitting operator can review
        $this->actingAs($fixture['operator'])->post(route('appeals.review', [$fixture['tournament'], $appeal]), ['expected_version' => 1])->assertSessionHasNoErrors();
        $this->assertSame('reviewing', $appeal->fresh()->status);
        $this->assertSame($fixture['operator']->id, $appeal->fresh()->reviewed_by);

        // Judge on the bout cannot review
        $fixture['tournament']->users()->attach($fixture['judges'][0], ['role' => 'operator']);
        $this->actingAs($fixture['judges'][0])->post(route('appeals.review', [$fixture['tournament'], $appeal]), ['expected_version' => 2])->assertForbidden();
    }

    public function test_duplicate_filing_stale_version_and_invalid_review_transitions_are_rejected(): void
    {
        $fixture = $this->publishedFixture();
        $appeal = $this->fileAppeal($fixture);
        $this->post(route('appeals.store', $fixture['tournament']), ['performance_id' => $fixture['performance']->id, 'expected_version' => $fixture['performance']->fresh()->version, 'representative_name' => 'نماینده', 'reason' => 'اعتراض تکراری به همین نتیجه'])->assertSessionHasErrors('operation');
        $this->actingAs($fixture['operator'])->post(route('appeals.review', [$fixture['tournament'], $appeal]), ['expected_version' => 8])->assertStatus(409);
        $this->post(route('appeals.decide', [$fixture['tournament'], $appeal]), ['expected_version' => 1, 'decision' => 'rejected', 'decision_reason' => 'بررسی هنوز شروع نشده است'])->assertSessionHasErrors('operation');
        $this->review($fixture, $appeal);
        $this->post(route('appeals.review', [$fixture['tournament'], $appeal]), ['expected_version' => 2])->assertSessionHasErrors('operation');
        $this->post(route('appeals.decide', [$fixture['tournament'], $appeal]), ['expected_version' => 2, 'decision' => 'accepted', 'decision_reason' => 'پذیرش بدون اصلاح مشخص نمره'])->assertSessionHasErrors('corrections');
    }

    public function test_another_operator_cannot_take_over_an_active_review(): void
    {
        $fixture = $this->publishedFixture();
        $appeal = $this->fileAppeal($fixture);
        $this->review($fixture, $appeal);
        $other = User::factory()->create();
        $fixture['tournament']->users()->attach($other, ['role' => 'operator']);
        $this->actingAs($other)->post(route('appeals.decide', [$fixture['tournament'], $appeal]), ['expected_version' => 2, 'decision' => 'rejected', 'decision_reason' => 'بررسی به اپراتور دیگری سپرده شده'])->assertSessionHasErrors('operation');
        $this->get(route('tournaments.show', $fixture['tournament']))->assertOk();
    }

    public function test_foreign_tournament_performance_and_appeal_cannot_be_modified(): void
    {
        $fixture = $this->publishedFixture();
        $other = $this->publishedFixture();
        $appeal = $this->fileAppeal($fixture);
        $this->actingAs($other['operator'])->post(route('appeals.store', $other['tournament']), ['performance_id' => $fixture['performance']->id, 'expected_version' => $fixture['performance']->fresh()->version, 'representative_name' => 'نماینده', 'reason' => 'اعتراض به نتیجهٔ مسابقهٔ دیگر'])->assertNotFound();
        $this->actingAs($other['operator'])->post(route('appeals.review', [$other['tournament'], $appeal]), ['expected_version' => 1])->assertNotFound();
        $this->actingAs($other['operator'])->post(route('appeals.apply', [$other['tournament'], $appeal]), ['expected_version' => 1])->assertNotFound();
        $this->assertSame('submitted', $appeal->fresh()->status);
    }

    private function completeFirstRound(array $fixture): void
    {
        foreach ($fixture['category']->rounds()->firstOrFail()->bouts()->orderBy('sequence')->get() as $bout) {
            $winner = $bout->id === $fixture['performance']->bout_id ? $fixture['performance']->entry_id : $bout->entries()->orderBy('entries.id')->firstOrFail()->id;
            foreach ($bout->performances()->orderBy('form_number')->orderBy('id')->get() as $performance) {
                if ($performance->status === 'approved') {
                    continue;
                }
                $this->startScoring($fixture, $performance);
                $this->scoreAll($fixture, $performance, $performance->entry_id === $winner ? '2.50' : '2.00');
                app(RunCompetition::class)->command($fixture['admin'], $fixture['tournament'], $performance, ['command' => 'approve', 'expected_version' => $performance->fresh()->version]);
            }
        }
    }

    public function test_open_appeal_blocks_scheduling_and_starting_the_next_round_then_updates_advancement_when_applied(): void
    {
        $fixture = $this->publishedFixture(4);
        $this->completeFirstRound($fixture);
        $this->schedule($fixture);
        $final = $fixture['category']->rounds()->orderByDesc('sequence')->firstOrFail();
        $next = $final->bouts()->firstOrFail()->performances()->orderBy('id')->firstOrFail();
        $appeal = $this->fileAppeal($fixture);
        $this->actingAs($fixture['admin'])->post(route('operations.command', [$fixture['tournament'], $next]), ['command' => 'start', 'expected_version' => 1])->assertSessionHasErrors('operation');
        $this->post(route('operations.schedule', [$fixture['tournament'], $fixture['category']]), ['court_id' => $fixture['tournament']->courts()->firstOrFail()->id, 'judge_ids' => $fixture['judges']->pluck('id')->all()])->assertSessionHasErrors('operation');
        $oldWinner = $fixture['performance']->bout()->firstOrFail()->winner_entry_id;
        $this->review($fixture, $appeal);
        $this->post(route('appeals.decide', [$fixture['tournament'], $appeal]), $this->acceptedPayload($fixture, $appeal))->assertSessionHasNoErrors();
        $this->actingAs($fixture['operator'])->post(route('appeals.apply', [$fixture['tournament'], $appeal]), ['expected_version' => 3])->assertSessionHasNoErrors();
        $newWinner = $fixture['performance']->bout()->firstOrFail()->winner_entry_id;
        $this->assertNotSame($oldWinner, $newWinner);
        $this->assertTrue($final->bouts()->firstOrFail()->entries()->whereKey($newWinner)->exists());
        $this->assertFalse($final->bouts()->firstOrFail()->entries()->whereKey($oldWinner)->exists());
    }

    public function test_open_appeal_prevents_completion_and_medals_and_prevents_archiving_completed_tournament(): void
    {
        $fixture = $this->publishedFixture();
        $this->completeFirstRound($fixture);
        $this->fileAppeal($fixture);
        $this->actingAs($fixture['admin'])->post(route('operations.complete', $fixture['tournament']))->assertSessionHasErrors('operation');
        $medals = app(CompetitionReports::class)->medals($fixture['tournament']->fresh(), $fixture['admin'], []);
        $this->assertEmpty($medals['rows']);
        $this->assertSame('running', $fixture['tournament']->fresh()->status);
        $fixture['tournament']->update(['status' => 'completed']);
        $this->post(route('tournaments.archive', $fixture['tournament']), ['expected_status' => 'completed', 'reason' => 'آرشیو در حالی که اعتراض باز است'])->assertSessionHasErrors('operation');
        $this->assertSame('completed', $fixture['tournament']->fresh()->status);
    }

    public function test_filing_is_closed_after_next_round_start_and_for_unpublished_or_archived_results(): void
    {
        $fixture = $this->publishedFixture(4);
        $data = ['performance_id' => $fixture['performance']->id, 'expected_version' => $fixture['performance']->fresh()->version, 'representative_name' => 'نماینده', 'reason' => 'درخواست بررسی نتیجهٔ این مسابقه'];
        $pending = Performance::where('status', 'pending')->firstOrFail();
        $this->actingAs($fixture['operator'])->post(route('appeals.store', $fixture['tournament']), [...$data, 'performance_id' => $pending->id, 'expected_version' => 1])->assertSessionHasErrors('operation');
        $this->completeFirstRound($fixture);
        $this->schedule($fixture);
        $next = $fixture['category']->rounds()->orderByDesc('sequence')->firstOrFail()->bouts()->firstOrFail()->performances()->orderBy('id')->firstOrFail();
        app(RunCompetition::class)->command($fixture['admin'], $fixture['tournament'], $next, ['command' => 'start', 'expected_version' => 1]);
        $this->post(route('appeals.store', $fixture['tournament']), $data)->assertSessionHasErrors('operation');
        $fixture['tournament']->update(['status' => 'archived']);
        $this->post(route('appeals.store', $fixture['tournament']), $data)->assertSessionHasErrors('operation');
        $this->assertDatabaseCount('competition_appeals', 0);
    }

    public function test_decision_rejects_foreign_seats_stale_revisions_and_invalid_component_scores(): void
    {
        $fixture = $this->publishedFixture(4);
        $appeal = $this->fileAppeal($fixture);
        $this->review($fixture, $appeal);
        $url = route('appeals.decide', [$fixture['tournament'], $appeal]);
        $payload = $this->acceptedPayload($fixture, $appeal);
        $invalid = $payload;
        $invalid['corrections'][0]['expected_revision'] = 9;
        $this->post($url, $invalid)->assertStatus(409);
        $invalid = $payload;
        $invalid['corrections'][0]['presentation'] = '6.10';
        $invalid['corrections'][0]['presentation_components'] = ['2.00', '2.00', '2.10'];
        $this->post($url, $invalid)->assertSessionHasErrors('operation');
        $invalid = $payload;
        $otherBout = $fixture['category']->rounds()->firstOrFail()->bouts()->where('id', '!=', $fixture['performance']->bout_id)->firstOrFail();
        $invalid['corrections'][0]['judge_assignment_id'] = $otherBout->judges()->firstOrFail()->id;
        $this->post($url, $invalid)->assertSessionHasErrors('operation');
        $this->assertSame('reviewing', $appeal->fresh()->status);
        $this->assertSame('8.500000', $fixture['performance']->result()->firstOrFail()->score);
    }

    public function test_apply_rechecks_all_revisions_before_changing_any_score(): void
    {
        $fixture = $this->publishedFixture();
        $appeal = $this->fileAppeal($fixture);
        $this->review($fixture, $appeal);
        $this->post(route('appeals.decide', [$fixture['tournament'], $appeal]), $this->acceptedPayload($fixture, $appeal))->assertSessionHasNoErrors();
        $sheets = $fixture['performance']->scoreSheets()->orderBy('id')->get();
        $sheets->last()->update(['revision' => 2]);
        $this->actingAs($fixture['operator'])->post(route('appeals.apply', [$fixture['tournament'], $appeal]), ['expected_version' => 3])->assertStatus(409);
        $this->assertSame(1, $sheets->first()->fresh()->revision);
        $this->assertSame('accepted', $appeal->fresh()->status);
        $this->assertSame('8.500000', $fixture['performance']->result()->firstOrFail()->score);
    }

    public function test_judge_role_cannot_file_or_view_appeals(): void
    {
        $role = 'judge';
        $fixture = $this->publishedFixture();
        $user = User::factory()->create();
        $fixture['tournament']->users()->attach($user, ['role' => $role]);
        $this->actingAs($user)->post(route('appeals.store', $fixture['tournament']), ['performance_id' => $fixture['performance']->id, 'expected_version' => $fixture['performance']->fresh()->version, 'representative_name' => 'نماینده', 'reason' => 'ثبت اعتراض نیاز به دسترسی اپراتور دارد'])->assertForbidden();
        $this->get(route('appeals.index', $fixture['tournament']))->assertForbidden();
    }

    public function test_supervisor_role_is_not_assignable_and_operator_has_appeal_permissions(): void
    {
        $fixture = $this->publishedFixture();
        $user = User::factory()->create();
        $this->actingAs($fixture['admin'])->post(route('operations.member', $fixture['tournament']), ['name' => $user->name, 'email' => $user->email, 'role' => 'supervisor'])->assertSessionHasErrors('role');
        $this->assertTrue($fixture['operator']->can('viewAppeals', $fixture['tournament']));
        $this->assertTrue($fixture['operator']->can('reviewAppeals', $fixture['tournament']));
        $this->assertTrue($fixture['operator']->can('operate', $fixture['tournament']));
    }

    public function test_appeal_events_update_affected_views_without_broadcasting_private_reasons(): void
    {
        $fixture = $this->publishedFixture();
        Event::fake([CompetitionDomainUpdated::class]);
        $appeal = $this->fileAppeal($fixture);
        foreach (['appeals', 'console', 'judging', 'scoreboard', 'results', 'draw'] as $domain) {
            Event::assertDispatched(CompetitionDomainUpdated::class, fn ($event) => $event->domain === $domain && $event->change['action'] === 'appeal.filed' && $event->change['context']['appeal_id'] === $appeal->id && ! array_key_exists('reason', $event->change));
        }
    }

    public function test_unrelated_categories_are_not_blocked_by_an_open_appeal(): void
    {
        $fixture = $this->publishedFixture();
        $this->completeFirstRound($fixture);
        $this->fileAppeal($fixture);
        $otherCategory = app(CompetitionSetup::class)->category($fixture['admin'], $fixture['tournament'], [
            'name' => 'ردهٔ مستقل', 'gender' => 'male', 'format' => 'knockout', 'judge_count' => 3,
            'discard_each_end' => 0, 'form_names' => ['فرم اول', 'فرم دوم'],
        ]);
        foreach (range(1, 4) as $number) {
            $entry = app(CompetitionSetup::class)->entry($fixture['admin'], $fixture['tournament'], $otherCategory, ['first_name' => 'ورزشکار'.$number, 'last_name' => 'ردهٔ مستقل', 'birth_date' => '2005-01-01', 'gender' => 'male']);
            app(CompetitionSetup::class)->entryStatus($fixture['admin'], $fixture['tournament'], $entry, 'checked_in');
        }
        $otherFixture = [...$fixture, 'category' => $otherCategory];
        $this->schedule($otherFixture);
        $this->completeFirstRound($otherFixture);
        $this->schedule($otherFixture);
        $this->assertSame(2, $otherCategory->rounds()->count());
        $this->assertSame('submitted', CompetitionAppeal::firstOrFail()->status);
    }
}
