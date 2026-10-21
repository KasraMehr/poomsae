<?php

namespace Tests\Feature;

use App\Actions\CompetitionSetup;
use App\Models\Athlete;
use App\Models\Category;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class AgeCategoryTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, mixed> */
    private function categoryData(string $ageCategory, string $discipline = 'recognized', string $entryType = 'individual'): array
    {
        return [
            'name' => 'ردهٔ جدول', 'age_category' => $ageCategory, 'discipline' => $discipline, 'entry_type' => $entryType,
            'gender' => 'male', 'format' => 'knockout', 'judge_count' => 5, 'discard_each_end' => 1,
            'rules_acknowledged' => true, 'planned_round_count' => 1,
        ];
    }

    /** @return array{admin: User, tournament: Tournament, category: Category} */
    private function competitionForAgeCategory(string $ageCategory, string $discipline = 'recognized', string $entryType = 'individual'): array
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $tournament = Tournament::factory()->create(['created_by' => $admin->id, 'starts_on' => '2026-10-06']);
        $category = app(CompetitionSetup::class)->category($admin, $tournament, $this->categoryData($ageCategory, $discipline, $entryType));

        return compact('admin', 'tournament', 'category');
    }

    /** @return array<string, array{string, string, string, array<int, int>, ?string, ?string}> */
    public static function ageCategories(): array
    {
        return [
            'recognized_individual_under_12' => ['recognized_individual_under_12', 'recognized', 'individual', [2, 3, 4, 5, 6, 7, 8, 9], '2015-01-01', null],
            'recognized_individual_12_14' => ['recognized_individual_12_14', 'recognized', 'individual', [4, 5, 6, 7, 8, 9, 10, 11], '2012-01-01', '2014-12-31'],
            'recognized_individual_15_17' => ['recognized_individual_15_17', 'recognized', 'individual', [5, 6, 7, 8, 9, 10, 11, 12], '2008-12-31', '2011-12-31'],
            'recognized_individual_18_30' => ['recognized_individual_18_30', 'recognized', 'individual', [7, 8, 9, 10, 11, 12, 13, 14], '1996-01-01', '2008-12-30'],
            'recognized_individual_31_40' => ['recognized_individual_31_40', 'recognized', 'individual', [7, 8, 9, 10, 11, 12, 13, 14], '1986-01-01', '1995-12-31'],
            'recognized_individual_41_50' => ['recognized_individual_41_50', 'recognized', 'individual', [8, 9, 10, 11, 12, 13, 14, 15], '1976-01-01', '1985-12-31'],
            'recognized_individual_51_60' => ['recognized_individual_51_60', 'recognized', 'individual', [9, 10, 11, 12, 13, 14, 15, 16], '1966-01-01', '1975-12-31'],
            'recognized_individual_61_65' => ['recognized_individual_61_65', 'recognized', 'individual', [9, 10, 11, 12, 13, 14, 15, 16], '1961-01-01', '1965-12-31'],
            'recognized_individual_over_65' => ['recognized_individual_over_65', 'recognized', 'individual', [9, 10, 11, 12, 13, 14, 15, 16], null, '1960-12-31'],
            'recognized_team_under_14' => ['recognized_team_under_14', 'recognized', 'team', [4, 5, 6, 7, 8, 9, 10, 11], '2012-01-01', null],
            'recognized_team_15_17' => ['recognized_team_15_17', 'recognized', 'team', [4, 5, 6, 7, 8, 9, 10, 11], '2008-12-31', '2011-12-31'],
            'recognized_team_18_30' => ['recognized_team_18_30', 'recognized', 'team', [6, 7, 8, 9, 10, 11, 12, 13], '1996-01-01', '2008-12-30'],
            'recognized_team_31_50' => ['recognized_team_31_50', 'recognized', 'team', [8, 9, 10, 11, 12, 13, 14, 15], '1976-01-01', '1995-12-31'],
            'recognized_team_over_50' => ['recognized_team_over_50', 'recognized', 'team', [9, 10, 11, 12, 13, 14, 15, 16], null, '1975-12-31'],
            'freestyle_individual_under_14' => ['freestyle_individual_under_14', 'freestyle', 'individual', [], '2012-01-01', null],
            'freestyle_individual_15_17' => ['freestyle_individual_15_17', 'freestyle', 'individual', [], '2008-12-31', '2011-12-31'],
            'freestyle_individual_over_18' => ['freestyle_individual_over_18', 'freestyle', 'individual', [], null, '2008-12-30'],
            'freestyle_pair_under_18' => ['freestyle_pair_under_18', 'freestyle', 'pair', [], '2008-12-31', null],
            'freestyle_pair_over_18' => ['freestyle_pair_over_18', 'freestyle', 'pair', [], null, '2008-12-30'],
        ];
    }

    #[DataProvider('ageCategories')]
    public function test_category_creation_uses_the_table_forms_and_birth_dates(string $id, string $discipline, string $entryType, array $forms, ?string $minimumBirthDate, ?string $maximumBirthDate): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $tournament = Tournament::factory()->create(['created_by' => $admin->id]);

        $this->actingAs($admin)->post('/tournaments/'.$tournament->id.'/categories', [
            ...$this->categoryData($id, $discipline, $entryType),
            'minimum_age' => 99, 'maximum_age' => 100, 'form_names' => ['فرم خارج از جدول'],
        ])->assertSessionHasNoErrors()->assertRedirect();

        $category = $tournament->categories()->firstOrFail();
        $this->assertSame(array_map(fn (int $number): string => 'فرم '.$number, $forms), $category->forms()->orderBy('poomsae_forms.id')->pluck('name')->all());
        $this->assertSame($minimumBirthDate, $category->scoringRuleSet->definition['age_category']['birth_date_min']);
        $this->assertSame($maximumBirthDate, $category->scoringRuleSet->definition['age_category']['birth_date_max']);
        $this->assertSame('birth_date_range', $category->scoringRuleSet->definition['age_basis']);
        $this->assertSame(1, $category->rounds()->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'category.saved', 'subject_id' => $category->id]);
    }

    public function test_category_creation_allows_a_custom_subset_of_the_age_table_forms(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $tournament = Tournament::factory()->create(['created_by' => $admin->id]);

        $this->actingAs($admin)->post('/tournaments/'.$tournament->id.'/categories', [
            ...$this->categoryData('recognized_individual_12_14'), 'selected_form_numbers' => [4, 7, 11],
        ])->assertSessionHasNoErrors();

        $category = $tournament->categories()->firstOrFail();
        $this->assertSame(['فرم 4', 'فرم 7', 'فرم 11'], $category->forms()->orderBy('poomsae_forms.id')->pluck('name')->all());
    }

    public function test_category_creation_rejects_a_form_outside_the_age_table(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $tournament = Tournament::factory()->create(['created_by' => $admin->id]);

        $this->actingAs($admin)->post('/tournaments/'.$tournament->id.'/categories', [
            ...$this->categoryData('recognized_individual_12_14'), 'selected_form_numbers' => [3, 4],
        ])->assertSessionHasErrors(['operation' => 'فرم انتخابی در فهرست مجاز این رده نیست.']);

        $this->assertDatabaseCount('categories', 0);
    }

    public function test_category_creation_requires_two_selected_forms(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $tournament = Tournament::factory()->create(['created_by' => $admin->id]);

        $this->actingAs($admin)->post('/tournaments/'.$tournament->id.'/categories', [
            ...$this->categoryData('recognized_individual_12_14'), 'selected_form_numbers' => [4],
        ])->assertSessionHasErrors('selected_form_numbers');

        $this->assertDatabaseCount('categories', 0);
    }

    #[TestWith(['recognized_individual_12_14', '2012-01-01'])]
    #[TestWith(['recognized_individual_12_14', '2014-12-31'])]
    #[TestWith(['recognized_individual_15_17', '2008-12-31'])]
    #[TestWith(['recognized_individual_15_17', '2011-12-31'])]
    #[TestWith(['recognized_individual_under_12', '2020-01-01'])]
    #[TestWith(['recognized_individual_under_12', '2015-01-01'])]
    #[TestWith(['recognized_individual_over_65', '1900-01-01'])]
    #[TestWith(['recognized_individual_over_65', '1960-12-31'])]
    public function test_registration_accepts_inclusive_birth_date_limits_and_open_ended_ranges(string $id, string $birthDate): void
    {
        $fixture = $this->competitionForAgeCategory($id);

        $this->actingAs($fixture['admin'])->post('/tournaments/'.$fixture['tournament']->id.'/categories/'.$fixture['category']->id.'/entries', [
            'first_name' => 'ورزشکار', 'last_name' => 'آزمایشی', 'birth_date' => $birthDate, 'gender' => 'male',
        ])->assertSessionHasNoErrors();

        $this->assertSame($birthDate, Athlete::sole()->birth_date->toDateString());
        $this->assertDatabaseHas('entries', ['category_id' => $fixture['category']->id]);
    }

    #[TestWith(['2011-12-31'])]
    #[TestWith(['2015-01-01'])]
    public function test_registration_rejects_birth_dates_one_day_outside_the_category(string $birthDate): void
    {
        $fixture = $this->competitionForAgeCategory('recognized_individual_12_14');

        $this->actingAs($fixture['admin'])->post('/tournaments/'.$fixture['tournament']->id.'/categories/'.$fixture['category']->id.'/entries', [
            'first_name' => 'ورزشکار', 'last_name' => 'آزمایشی', 'birth_date' => $birthDate, 'gender' => 'male',
        ])->assertSessionHasErrors(['operation' => 'تاریخ تولد ورزشکار با ردهٔ سنی سازگار نیست: متولدین ۱۳۹۰/۱۰/۱۱ الی ۱۳۹۳/۱۰/۱۰']);

        $this->assertDatabaseCount('entries', 0);
        $this->assertDatabaseCount('athletes', 0);
    }

    public function test_team_registration_checks_every_member_and_rolls_back_an_invalid_team(): void
    {
        $fixture = $this->competitionForAgeCategory('recognized_team_15_17', 'recognized', 'team');
        $members = array_map(fn (int $number): array => ['first_name' => 'عضو'.$number, 'last_name' => 'آزمایشی', 'birth_date' => $number === 3 ? '2012-01-01' : '2010-01-01', 'gender' => 'male'], [1, 2, 3]);

        $this->actingAs($fixture['admin'])->post('/tournaments/'.$fixture['tournament']->id.'/categories/'.$fixture['category']->id.'/entries', ['members' => $members])
            ->assertSessionHasErrors('operation');

        $this->assertDatabaseCount('entries', 0);
        $this->assertDatabaseCount('athletes', 0);
    }

    #[TestWith(['recognized_team_under_14', 'recognized', 'individual'])]
    #[TestWith(['recognized_individual_under_12', 'freestyle', 'individual'])]
    public function test_category_rejects_an_age_group_from_another_discipline_or_entry_type(string $id, string $discipline, string $entryType): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $tournament = Tournament::factory()->create(['created_by' => $admin->id]);

        $this->actingAs($admin)->post('/tournaments/'.$tournament->id.'/categories', $this->categoryData($id, $discipline, $entryType))
            ->assertSessionHasErrors(['operation' => 'ردهٔ سنی با سبک و نوع شرکت سازگار نیست.']);

        $this->assertDatabaseCount('categories', 0);
        $this->assertDatabaseCount('scoring_rule_sets', 0);
    }

    public function test_setup_exposes_all_nineteen_age_categories_from_the_reference_table(): void
    {
        $fixture = $this->competitionForAgeCategory('recognized_individual_12_14');

        $this->actingAs($fixture['admin'])->get('/tournaments/'.$fixture['tournament']->id.'/setup')
            ->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->component('Tournaments/Show')
            ->where('ageTableYear', 1405)->has('ageCategories', 19)
            ->where('ageCategories.0.label', 'زیر ۱۲ سال')
            ->where('ageCategories.18.label', 'تیمی ابداعی دو نفره بالای ۱۸ سال')
            ->where('tournament.categories.0.rules.age_category.birth_date_label', 'متولدین ۱۳۹۰/۱۰/۱۱ الی ۱۳۹۳/۱۰/۱۰'));
    }

    public function test_category_rejects_an_unknown_age_group(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $tournament = Tournament::factory()->create(['created_by' => $admin->id]);

        $this->actingAs($admin)->post('/tournaments/'.$tournament->id.'/categories', $this->categoryData('unknown'))
            ->assertSessionHasErrors('age_category');

        $this->assertDatabaseCount('categories', 0);
    }

    #[TestWith(['minimum_age', 13, 'بازهٔ سن ردهٔ جدول قابل تغییر نیست.'])]
    #[TestWith(['maximum_age', 15, 'بازهٔ سن ردهٔ جدول قابل تغییر نیست.'])]
    #[TestWith(['form_names', ['فرم 1', 'فرم 2', 'فرم 3', 'فرم 4', 'فرم 5', 'فرم 6', 'فرم 7', 'فرم 8'], 'فهرست فرم‌ها باید مطابق جدول ردهٔ سنی باشد.'])]
    public function test_management_import_returns_422_when_overriding_the_age_table(string $field, mixed $value, string $message): void
    {
        $fixture = $this->competitionForAgeCategory('recognized_individual_12_14');
        Sanctum::actingAs($fixture['admin'], ['competition:manage']);

        $this->putJson('/api/v1/tournaments/'.$fixture['tournament']->id.'/categories/'.$fixture['category']->id.'/management-snapshot', [
            'source' => 'management', 'version' => 1, 'expected_version' => 0, 'draw_method' => 'random',
            'category' => [$field => $value],
            'entries' => array_map(fn (int $number): array => [
                'external_id' => 'athlete-'.$number, 'status' => 'registered',
                'members' => [['first_name' => 'ورزشکار'.$number, 'last_name' => 'آزمایشی', 'birth_date' => '2013-01-01', 'gender' => 'male']],
            ], [1, 2]),
            'stages' => [['sequence' => 1, 'form_ids' => [], 'court_id' => 1, 'judge_ids' => [1, 2, 3, 4, 5], 'bouts' => []]],
        ])->assertUnprocessable()->assertJsonValidationErrors(['operation' => $message]);

        $this->assertDatabaseCount('entries', 0);
        $this->assertSame(0, $fixture['category']->fresh()->management_version);
        $this->assertSame(['فرم 4', 'فرم 5', 'فرم 6', 'فرم 7', 'فرم 8', 'فرم 9', 'فرم 10', 'فرم 11'], $fixture['category']->forms()->orderBy('poomsae_forms.id')->pluck('name')->all());
    }
}
