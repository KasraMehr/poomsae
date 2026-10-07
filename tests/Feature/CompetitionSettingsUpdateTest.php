<?php

namespace Tests\Feature;

use App\Actions\CompetitionSetup;
use App\Actions\CompetitionView;
use App\Actions\RunCompetition;
use App\Events\CompetitionDomainUpdated;
use App\Events\CompetitionUpdated;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Entry;
use App\Models\Performance;
use App\Models\Result;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\CreatesCompetition;
use Tests\TestCase;

class CompetitionSettingsUpdateTest extends TestCase
{
    use CreatesCompetition, DatabaseMigrations;

    public function test_manager_can_update_tournament_with_audit_and_events_without_changing_status(): void
    {
        $fixture = $this->competition();
        $manager = User::factory()->create();
        $fixture['tournament']->users()->attach($manager, ['role' => 'manager']);
        $beforeName = $fixture['tournament']->name;
        Event::fake([CompetitionUpdated::class, CompetitionDomainUpdated::class]);

        $this->actingAs($manager)->put(route('tournaments.update', $fixture['tournament']), [...$this->tournamentInput($fixture['tournament']), 'name' => 'مسابقهٔ جدید', 'venue' => 'سالن جدید', 'status' => 'completed'])
            ->assertSessionHasNoErrors()->assertRedirect();

        $this->assertDatabaseHas('tournaments', ['id' => $fixture['tournament']->id, 'name' => 'مسابقهٔ جدید', 'venue' => 'سالن جدید', 'status' => 'draft']);
        $audit = AuditLog::where('action', 'tournament.updated')->firstOrFail();
        $this->assertSame($beforeName, $audit->before['name']);
        $this->assertSame('مسابقهٔ جدید', $audit->after['name']);
        $this->assertSame($manager->id, $audit->user_id);
        Event::assertDispatched(CompetitionDomainUpdated::class, fn ($event) => $event->domain === 'scoreboard' && $event->change['action'] === 'tournament.updated');
        Event::assertDispatchedTimes(CompetitionUpdated::class, 1);
    }

    public function test_valid_dates_can_change_before_draw_and_ineligible_dates_roll_back(): void
    {
        $fixture = $this->competition();
        $url = route('tournaments.update', $fixture['tournament']);
        $this->actingAs($fixture['admin'])->put($url, [...$this->tournamentInput($fixture['tournament']), 'starts_on' => '2026-10-02', 'ends_on' => '2026-10-03'])->assertSessionHasNoErrors();
        $this->assertSame('2026-10-02', $fixture['tournament']->fresh()->starts_on->format('Y-m-d'));
        $beforeName = $fixture['tournament']->name;
        Event::fake([CompetitionUpdated::class, CompetitionDomainUpdated::class]);

        $this->put($url, [...$this->tournamentInput($fixture['tournament']->fresh()), 'starts_on' => '2080-10-01', 'ends_on' => null, 'name' => 'نام نامعتبر'])
            ->assertSessionHasErrors(['operation' => 'سن ورزشکار در روز شروع مسابقه با رده سازگار نیست.']);

        $this->assertSame($beforeName, $fixture['tournament']->fresh()->name);
        $this->assertSame('2026-10-02', $fixture['tournament']->fresh()->starts_on->format('Y-m-d'));
        Event::assertNotDispatched(CompetitionUpdated::class);
        Event::assertNotDispatched(CompetitionDomainUpdated::class);
    }

    public function test_after_draw_dates_are_locked_but_name_and_venue_can_change(): void
    {
        $fixture = $this->competition();
        $this->schedule($fixture);
        $url = route('tournaments.update', $fixture['tournament']);
        $this->actingAs($fixture['admin'])->put($url, [...$this->tournamentInput($fixture['tournament']), 'starts_on' => '2026-10-02'])
            ->assertSessionHasErrors(['operation' => 'بعد از قرعه، تغییر تاریخ یا منطقهٔ زمانی مسابقه مجاز نیست.']);

        $this->put($url, [...$this->tournamentInput($fixture['tournament']), 'name' => 'عنوان اصلاح‌شده', 'venue' => 'سالن اصلاح‌شده'])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('tournaments', ['id' => $fixture['tournament']->id, 'name' => 'عنوان اصلاح‌شده', 'venue' => 'سالن اصلاح‌شده']);
    }

    public function test_court_rename_keeps_running_performance_and_notifies_displays(): void
    {
        $fixture = $this->competition();
        $this->schedule($fixture);
        $performance = Performance::firstOrFail();
        app(RunCompetition::class)->command($fixture['admin'], $fixture['tournament'], $performance, ['command' => 'start', 'expected_version' => 1]);
        $court = $fixture['tournament']->courts()->firstOrFail();
        Event::fake([CompetitionUpdated::class, CompetitionDomainUpdated::class]);

        $this->actingAs($fixture['admin'])->put(route('operations.court.update', [$fixture['tournament'], $court]), ['name' => 'زمین فینال'])->assertSessionHasNoErrors();

        $this->assertSame('زمین فینال', $court->fresh()->name);
        $this->assertSame('running', $performance->fresh()->status);
        $this->assertSame($court->id, $performance->bout->court_id);
        $this->assertDatabaseHas('audit_logs', ['action' => 'court.updated', 'subject_id' => $court->id]);
        Event::assertDispatched(CompetitionDomainUpdated::class, fn ($event) => $event->domain === 'courts' && $event->change['context']['court_ids'] === [$court->id]);
        Event::assertDispatched(CompetitionDomainUpdated::class, fn ($event) => $event->domain === 'scoreboard' && $event->change['action'] === 'court.updated');
    }

    public function test_court_name_must_be_unique_and_can_remain_unchanged(): void
    {
        $fixture = $this->competition();
        app(CompetitionSetup::class)->court($fixture['admin'], $fixture['tournament'], ['name' => 'زمین دوم']);
        $court = $fixture['tournament']->courts()->where('name', 'زمین یک')->firstOrFail();
        $url = route('operations.court.update', [$fixture['tournament'], $court]);

        $this->actingAs($fixture['admin'])->put($url, ['name' => 'زمین دوم'])->assertSessionHasErrors('name');
        $this->assertSame('زمین یک', $court->fresh()->name);
        $this->put($url, ['name' => 'زمین یک'])->assertSessionHasNoErrors();
    }

    public function test_athlete_edit_updates_display_name_with_audit_and_private_notification(): void
    {
        $fixture = $this->competition();
        $entry = $fixture['category']->entries()->firstOrFail();
        $athlete = $entry->athletes()->firstOrFail();
        Event::fake([CompetitionUpdated::class, CompetitionDomainUpdated::class]);

        $this->actingAs($fixture['admin'])->put(route('operations.entry.update', [$fixture['tournament'], $entry]), $this->entryInput($entry, ['first_name' => 'نام اصلاح‌شده', 'birth_date' => '2006-01-01', 'club' => 'باشگاه جدید']))->assertSessionHasNoErrors();

        $this->assertSame('نام اصلاح‌شده آزمایشی', $entry->fresh()->display_name);
        $this->assertSame('2006-01-01', $athlete->fresh()->birth_date->format('Y-m-d'));
        $this->assertSame('باشگاه جدید', $athlete->fresh()->club);
        $audit = AuditLog::where('action', 'entry.updated')->firstOrFail();
        $this->assertSame('ورزشکار0', $audit->before['members'][0]['first_name']);
        $this->assertSame('نام اصلاح‌شده', $audit->after['members'][0]['first_name']);
        Event::assertDispatched(CompetitionDomainUpdated::class, fn ($event) => $event->domain === 'scoreboard' && $event->change['action'] === 'entry.updated' && $event->change['context']['entry_id'] === $entry->id);
        $this->assertStringNotContainsString('2006-01-01', json_encode(Event::dispatched(CompetitionDomainUpdated::class)->all()));
    }

    public function test_name_correction_after_publication_preserves_scores_results_and_ids(): void
    {
        $fixture = $this->competition();
        $this->schedule($fixture);
        $performance = Performance::firstOrFail();
        $this->startScoring($fixture, $performance);
        $this->scoreAll($fixture, $performance);
        app(RunCompetition::class)->command($fixture['admin'], $fixture['tournament'], $performance, ['command' => 'approve', 'expected_version' => $performance->fresh()->version]);
        $result = Result::firstOrFail();
        $entry = Entry::findOrFail($performance->entry_id);
        $firstName = $entry->athletes()->firstOrFail()->first_name;
        $originalResult = $result->toArray();

        $this->actingAs($fixture['admin'])->put(route('operations.entry.update', [$fixture['tournament'], $entry]), $this->entryInput($entry, ['last_name' => 'نام صحیح', 'club' => 'باشگاه صحیح']))->assertSessionHasNoErrors();

        $this->assertSame($originalResult, $result->fresh()->toArray());
        $this->assertSame($entry->id, $performance->fresh()->entry_id);
        $this->assertDatabaseCount('score_sheets', 5);
        $this->assertSame($firstName.' نام صحیح', $entry->fresh()->display_name);
    }

    #[TestWith(['birth_date', '2006-01-01'])]
    #[TestWith(['gender', 'female'])]
    public function test_after_draw_eligibility_fields_cannot_change(string $field, string $value): void
    {
        $fixture = $this->competition();
        $this->schedule($fixture);
        $entry = $fixture['category']->entries()->firstOrFail();

        $this->actingAs($fixture['admin'])->put(route('operations.entry.update', [$fixture['tournament'], $entry]), $this->entryInput($entry, [$field => $value, 'first_name' => 'تغییر نام']))
            ->assertSessionHasErrors(['operation' => 'بعد از قرعه فقط نام و باشگاه ورزشکار قابل اصلاح است.']);

        $this->assertSame('ورزشکار0', $entry->athletes()->firstOrFail()->first_name);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'entry.updated']);
    }

    public function test_an_athlete_cannot_be_replaced_by_another_entry_member(): void
    {
        $fixture = $this->competition();
        $entries = $fixture['category']->entries()->get();
        $otherAthlete = $entries[1]->athletes()->firstOrFail();

        $this->actingAs($fixture['admin'])->put(route('operations.entry.update', [$fixture['tournament'], $entries[0]]), $this->entryInput($entries[0], ['id' => $otherAthlete->id]))
            ->assertSessionHasErrors(['operation' => 'اعضای ورودی و ترتیب آن‌ها قابل جایگزینی نیستند.']);

        $this->assertSame('ورزشکار1', $otherAthlete->fresh()->first_name);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'entry.updated']);
    }

    #[TestWith(['birth_date', '2020-01-01'])]
    #[TestWith(['gender', 'female'])]
    #[TestWith(['first_name', 'ورزشکار1'])]
    public function test_before_draw_edits_must_match_eligibility_and_not_duplicate_entries(string $field, string $value): void
    {
        $fixture = $this->competition();
        $entry = $fixture['category']->entries()->firstOrFail();

        $this->actingAs($fixture['admin'])->put(route('operations.entry.update', [$fixture['tournament'], $entry]), $this->entryInput($entry, [$field => $value]))->assertSessionHasErrors('operation');

        $this->assertSame('ورزشکار0', $entry->athletes()->firstOrFail()->first_name);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'entry.updated']);
    }

    public function test_team_member_corrections_keep_original_positions(): void
    {
        $fixture = $this->competition(5, 0);
        $setup = app(CompetitionSetup::class);
        $category = $setup->category($fixture['admin'], $fixture['tournament'], $this->categoryInput($fixture['category'], ['entry_type' => 'team']), $fixture['category']);
        $entry = $setup->entry($fixture['admin'], $fixture['tournament'], $category, ['members' => array_map(fn ($index): array => ['first_name' => 'عضو'.$index, 'last_name' => 'تیم', 'birth_date' => '2005-01-01', 'gender' => 'male', 'club' => 'باشگاه'], [1, 2, 3])]);
        $athleteIds = $entry->athletes()->orderByPivot('position')->pluck('athletes.id')->all();
        $input = $this->entryInput($entry);
        $input['members'][1]['last_name'] = 'اصلاح‌شده';

        $this->actingAs($fixture['admin'])->put(route('operations.entry.update', [$fixture['tournament'], $entry]), $input)->assertSessionHasNoErrors();

        $this->assertSame($athleteIds, $entry->athletes()->orderByPivot('position')->pluck('athletes.id')->all());
        $this->assertSame('عضو1 تیم / عضو2 اصلاح‌شده / عضو3 تیم', $entry->fresh()->display_name);
    }

    public function test_rules_change_with_existing_athletes_creates_new_version_and_keeps_previous_rules(): void
    {
        $fixture = $this->competition();
        $oldRule = $fixture['category']->scoringRuleSet;
        $entryIds = $fixture['category']->entries()->pluck('id')->all();
        Event::fake([CompetitionUpdated::class, CompetitionDomainUpdated::class]);

        $this->actingAs($fixture['admin'])->put(route('operations.category.update', [$fixture['tournament'], $fixture['category']]), $this->categoryInput($fixture['category'], ['judge_count' => 7, 'discard_each_end' => 2, 'name' => 'رده اصلاح‌شده', 'algorithm' => 'arbitrary']))->assertSessionHasNoErrors();

        $category = $fixture['category']->fresh();
        $this->assertSame(7, $category->judge_count);
        $this->assertSame(2, $category->scoringRuleSet->definition['discard_each_end']);
        $this->assertSame(2, $category->scoringRuleSet->version);
        $this->assertSame('component_trimmed_mean_v1', $category->scoringRuleSet->definition['algorithm']);
        $this->assertSame(400, $category->scoringRuleSet->definition['accuracy_max']);
        $this->assertNotSame($oldRule->id, $category->scoring_rule_set_id);
        $this->assertSame(1, $oldRule->fresh()->definition['discard_each_end']);
        $this->assertSame($entryIds, $category->entries()->pluck('id')->all());
        Event::assertDispatched(CompetitionDomainUpdated::class, fn ($event) => $event->domain === 'judging' && $event->change['action'] === 'category.saved');
    }

    #[TestWith(['maximum_age', 15])]
    #[TestWith(['entry_type', 'team'])]
    public function test_category_changes_cannot_invalidate_registered_athletes(string $field, int|string $value): void
    {
        $fixture = $this->competition();
        $oldRuleId = $fixture['category']->scoring_rule_set_id;
        $oldName = $fixture['category']->name;

        $this->actingAs($fixture['admin'])->put(route('operations.category.update', [$fixture['tournament'], $fixture['category']]), $this->categoryInput($fixture['category'], [$field => $value, 'name' => 'نام جدید']))->assertSessionHasErrors('operation');

        $this->assertSame($oldRuleId, $fixture['category']->fresh()->scoring_rule_set_id);
        $this->assertSame($oldName, $fixture['category']->fresh()->name);
        $this->assertDatabaseCount('scoring_rule_sets', 1);
    }

    public function test_rules_cannot_change_after_table_draw(): void
    {
        $fixture = $this->competition();
        $this->schedule($fixture);
        $oldRuleId = $fixture['category']->scoring_rule_set_id;

        $this->actingAs($fixture['admin'])->put(route('operations.category.update', [$fixture['tournament'], $fixture['category']]), $this->categoryInput($fixture['category'], ['judge_count' => 7]))->assertSessionHasErrors('operation');

        $this->assertSame($oldRuleId, $fixture['category']->fresh()->scoring_rule_set_id);
        $this->assertDatabaseCount('scoring_rule_sets', 1);
    }

    public function test_rules_cannot_change_after_form_draw_without_bouts(): void
    {
        $fixture = $this->competition(5, 2, 'knockout', 1, 1);
        $fixture['category']->rounds()->firstOrFail()->update(['forms_drawn_at' => now()]);

        $this->actingAs($fixture['admin'])->put(route('operations.category.update', [$fixture['tournament'], $fixture['category']]), $this->categoryInput($fixture['category'], ['judge_count' => 7]))->assertSessionHasErrors('operation');

        $this->assertDatabaseCount('scoring_rule_sets', 1);
    }

    #[TestWith(['court'])]
    #[TestWith(['entry'])]
    public function test_cross_tournament_record_edit_returns_404(string $kind): void
    {
        $fixture = $this->competition();
        $other = $this->competition();
        $record = $kind === 'court' ? $other['tournament']->courts()->firstOrFail() : $other['category']->entries()->firstOrFail();
        $input = $kind === 'court' ? ['name' => 'نام جدید'] : $this->entryInput($record);

        $this->actingAs($fixture['admin'])->put(route('operations.'.$kind.'.update', [$fixture['tournament'], $record]), $input)->assertNotFound();

        $this->assertDatabaseMissing('audit_logs', ['action' => $kind.'.updated']);
    }

    #[TestWith(['tournament', 'operator'])]
    #[TestWith(['court', 'judge'])]
    #[TestWith(['entry', 'display'])]
    public function test_editing_requires_manager_role(string $kind, string $role): void
    {
        $fixture = $this->competition();
        $user = User::factory()->create();
        $fixture['tournament']->users()->attach($user, ['role' => $role]);
        [$url, $input] = $this->editRequest($fixture, $kind);

        $this->actingAs($user)->put($url, $input)->assertForbidden();

        $this->assertDatabaseMissing('audit_logs', ['action' => $kind.'.updated']);
    }

    #[TestWith(['tournament'])]
    #[TestWith(['court'])]
    #[TestWith(['entry'])]
    public function test_guest_edit_redirects_to_login(string $kind): void
    {
        $fixture = $this->competition();
        [$url, $input] = $this->editRequest($fixture, $kind);

        $this->put($url, $input)->assertRedirect(route('login'));

        $this->assertDatabaseMissing('audit_logs', ['action' => $kind.'.updated']);
    }

    #[TestWith(['tournament'])]
    #[TestWith(['court'])]
    #[TestWith(['entry'])]
    public function test_completed_tournament_rejects_metadata_edits(string $kind): void
    {
        $fixture = $this->competition();
        $fixture['tournament']->update(['status' => 'completed']);
        [$url, $input] = $this->editRequest($fixture, $kind);

        $this->actingAs($fixture['admin'])->put($url, $input)->assertSessionHasErrors(['operation' => 'مسابقه بسته شده است.']);

        $this->assertDatabaseMissing('audit_logs', ['action' => $kind.'.updated']);
    }

    public function test_imported_entry_rejects_local_edits(): void
    {
        $fixture = $this->competition();
        $fixture['category']->update(['management_source' => 'external']);
        $entry = $fixture['category']->entries()->firstOrFail();

        $this->actingAs($fixture['admin'])->put(route('operations.entry.update', [$fixture['tournament'], $entry]), $this->entryInput($entry))
            ->assertSessionHasErrors(['operation' => 'اطلاعات این ورودی از سیستم مدیریت مسابقات دریافت می‌شود.']);

        $this->assertDatabaseMissing('audit_logs', ['action' => 'entry.updated']);
    }

    public function test_editable_private_member_details_are_only_exposed_to_managers(): void
    {
        $fixture = $this->competition();
        $view = app(CompetitionView::class);
        $managerView = $view->snapshot($fixture['tournament'], $fixture['admin']);
        $judgeView = $view->snapshot($fixture['tournament'], $fixture['judges'][0]);
        $displayView = $view->snapshot($fixture['tournament'], $fixture['admin'], true);

        $this->assertSame('2005-01-01', $managerView['categories'][0]['entries'][0]['members'][0]['birth_date']);
        $this->assertArrayNotHasKey('birth_date', $judgeView['categories'][0]['entries'][0]['members'][0]);
        $this->assertArrayNotHasKey('birth_date', $displayView['categories'][0]['entries'][0]['members'][0]);
        $this->assertTrue($managerView['categories'][0]['settings_editable']);
        $this->assertFalse($judgeView['categories'][0]['settings_editable']);
    }

    private function tournamentInput(Tournament $tournament): array
    {
        return ['name' => $tournament->name, 'venue' => $tournament->venue, 'starts_on' => $tournament->starts_on->format('Y-m-d'), 'ends_on' => $tournament->ends_on?->format('Y-m-d'), 'timezone' => $tournament->timezone];
    }

    private function entryInput(Entry $entry, array $firstMemberChanges = []): array
    {
        $members = $entry->athletes()->orderByPivot('position')->get()->map(fn ($athlete): array => [...$athlete->only(['id', 'first_name', 'last_name', 'gender', 'club']), 'birth_date' => $athlete->birth_date->format('Y-m-d')])->all();
        $members[0] = [...$members[0], ...$firstMemberChanges];

        return ['members' => $members];
    }

    private function categoryInput(Category $category, array $changes = []): array
    {
        $input = [...$category->only(['name', 'gender', 'minimum_age', 'maximum_age', 'discipline', 'entry_type', 'format', 'execution_mode', 'performance_order', 'judge_count', 'allow_form_repetition', 'form_draw_timing']), 'form_draw_time' => '08:00', 'accuracy_max' => 400, 'discard_each_end' => 1, 'rules_acknowledged' => true, 'form_names' => $category->forms()->pluck('name')->all()];
        if ($category->planned_round_count !== null) {
            $input['planned_round_count'] = $category->planned_round_count;
        }

        return [...$input, ...$changes];
    }

    private function editRequest(array $fixture, string $kind): array
    {
        return match ($kind) {
            'tournament' => [route('tournaments.update', $fixture['tournament']), $this->tournamentInput($fixture['tournament'])],
            'court' => [route('operations.court.update', [$fixture['tournament'], $fixture['tournament']->courts()->firstOrFail()]), ['name' => 'نام جدید']],
            'entry' => [route('operations.entry.update', [$fixture['tournament'], $entry = $fixture['category']->entries()->firstOrFail()]), $this->entryInput($entry)],
        };
    }
}
