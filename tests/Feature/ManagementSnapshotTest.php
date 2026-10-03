<?php

namespace Tests\Feature;

use App\Models\Performance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\CreatesCompetition;
use Tests\TestCase;

class ManagementSnapshotTest extends TestCase
{
    use CreatesCompetition, RefreshDatabase;

    private function payload(array $fixture): array
    {
        return [
            'source' => 'management', 'version' => 1, 'expected_version' => 0, 'draw_method' => 'random',
            'category' => ['name' => 'ردهٔ دریافت‌شده', 'minimum_age' => 18, 'maximum_age' => 30],
            'entries' => array_map(fn ($number) => [
                'external_id' => 'athlete-'.$number, 'status' => 'checked_in',
                'members' => [['first_name' => 'بازیکن'.$number, 'last_name' => 'دریافتی', 'birth_date' => '2005-01-01', 'gender' => 'male', 'club' => 'باشگاه']],
            ], [1, 2]),
            'stages' => [[
                'sequence' => 1, 'name' => 'فینال', 'form_ids' => $fixture['category']->forms()->orderBy('id')->limit(2)->pluck('poomsae_forms.id')->all(),
                'court_id' => $fixture['tournament']->courts()->firstOrFail()->id,
                'judge_ids' => $fixture['judges']->pluck('id')->all(), 'bouts' => [['athlete-1', 'athlete-2']],
            ]],
        ];
    }

    private function endpoint(array $fixture): string
    {
        return '/api/v1/tournaments/'.$fixture['tournament']->id.'/categories/'.$fixture['category']->id.'/management-snapshot';
    }

    public function test_import_stores_names_age_group_bracket_forms_and_version_locally(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 1)->setTime(6, 0));
        $fixture = $this->competition(5, 0, 'knockout', 1, 1);
        $payload = $this->payload($fixture);
        Sanctum::actingAs($fixture['admin'], ['competition:manage']);
        $this->putJson($this->endpoint($fixture), $payload)->assertOk()->assertJsonPath('data.version', 1)->assertJsonPath('data.replayed', false);
        $category = $fixture['category']->fresh();
        $this->assertSame('management', $category->management_source);
        $this->assertSame('ردهٔ دریافت‌شده', $category->name);
        $this->assertEquals(18, $category->minimum_age);
        $this->assertSame($payload['entries'], $category->management_snapshot['entries']);
        $this->assertDatabaseCount('performances', 4);
        $this->assertDatabaseCount('draws', 2);
        $round = $category->rounds()->firstOrFail();
        $this->assertSame($payload['stages'][0]['form_ids'], $round->form_sequence);
        $this->assertSame(1, $round->source_version);

        $this->putJson($this->endpoint($fixture), $payload)->assertOk()->assertJsonPath('data.replayed', true);
        $this->assertDatabaseCount('performances', 4);
        $this->assertDatabaseCount('draws', 2);
        Http::preventStrayRequests();
        $performance = Performance::orderBy('id')->firstOrFail();
        $this->startScoring($fixture, $performance);
        $this->assertSame('scoring', $performance->fresh()->status);
    }

    public function test_new_version_can_replace_an_unstarted_stage_but_stale_and_conflicting_versions_return_409(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 1)->setTime(6, 0));
        $fixture = $this->competition(5, 0, 'knockout', 1, 1);
        $payload = $this->payload($fixture);
        Sanctum::actingAs($fixture['admin'], ['competition:manage']);
        $this->putJson($this->endpoint($fixture), $payload)->assertOk();
        $oldId = Performance::orderBy('id')->firstOrFail()->id;
        $payload['version'] = 2;
        $payload['expected_version'] = 1;
        $payload['reason'] = 'اصلاح پومسهٔ مرحله';
        $payload['stages'][0]['form_ids'] = $fixture['category']->forms()->orderBy('id')->skip(2)->limit(2)->pluck('poomsae_forms.id')->all();
        $this->putJson($this->endpoint($fixture), $payload)->assertOk()->assertJsonPath('data.version', 2);
        $this->assertDatabaseMissing('performances', ['id' => $oldId]);
        $this->assertDatabaseCount('performances', 4);
        $this->assertDatabaseCount('draws', 4);
        $this->assertSame(2, $fixture['category']->rounds()->firstOrFail()->schedule_version);
        $payload['stages'][0]['name'] = 'تغییر در همان نسخه';
        $this->putJson($this->endpoint($fixture), $payload)->assertConflict();
        $payload['version'] = 1;
        $this->putJson($this->endpoint($fixture), $payload)->assertConflict();
    }

    public function test_started_stage_cannot_be_replaced_and_existing_scores_are_preserved(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 1)->setTime(6, 0));
        $fixture = $this->competition(5, 0, 'knockout', 1, 1);
        $payload = $this->payload($fixture);
        Sanctum::actingAs($fixture['admin'], ['competition:manage']);
        $this->putJson($this->endpoint($fixture), $payload)->assertOk();
        $performance = Performance::orderBy('id')->firstOrFail();
        $this->startScoring($fixture, $performance);
        $this->scoreAll($fixture, $performance);
        $payload['version'] = 2;
        $payload['expected_version'] = 1;
        $payload['reason'] = 'تأیید دوبارهٔ اطلاعات';
        $this->putJson($this->endpoint($fixture), $payload)->assertOk();
        $payload['version'] = 3;
        $payload['expected_version'] = 2;
        $payload['stages'][0]['bouts'][0] = ['athlete-2', 'athlete-1'];
        $this->putJson($this->endpoint($fixture), $payload)->assertConflict();
        $this->assertDatabaseCount('score_sheets', 5);
        $this->assertDatabaseHas('performances', ['id' => $performance->id, 'status' => 'scoring']);
        $this->assertSame(2, $fixture['category']->fresh()->management_version);
    }

    public function test_import_without_form_draw_keeps_execution_blocked_until_a_later_version(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 1)->setTime(6, 0));
        $fixture = $this->competition(5, 0, 'knockout', 1, 1);
        $payload = $this->payload($fixture);
        $forms = $payload['stages'][0]['form_ids'];
        $payload['stages'][0]['form_ids'] = [];
        Sanctum::actingAs($fixture['admin'], ['competition:manage']);
        $this->putJson($this->endpoint($fixture), $payload)->assertOk();
        $performance = Performance::orderBy('id')->firstOrFail();
        $this->actingAs($fixture['admin'])->post('/tournaments/'.$fixture['tournament']->id.'/performances/'.$performance->id.'/command', ['command' => 'start', 'expected_version' => $performance->version])->assertSessionHasErrors('operation');
        $payload['version'] = 2;
        $payload['expected_version'] = 1;
        $payload['reason'] = 'انتشار قرعهٔ فرم‌ها';
        $payload['stages'][0]['form_ids'] = $forms;
        $this->putJson($this->endpoint($fixture), $payload)->assertOk();
        $this->assertNotNull(Performance::orderBy('id')->firstOrFail()->poomsae_form_id);
    }

    public function test_snapshot_requires_authentication_manager_role_and_token_ability(): void
    {
        $fixture = $this->competition(5, 0, 'knockout', 1, 1);
        $payload = $this->payload($fixture);
        $url = $this->endpoint($fixture);
        $this->putJson($url, $payload)->assertUnauthorized();
        Sanctum::actingAs($fixture['admin'], ['tournaments:read']);
        $this->putJson($url, $payload)->assertForbidden();
        Sanctum::actingAs($fixture['judges'][0], ['competition:manage']);
        $this->putJson($url, $payload)->assertForbidden();
        $this->assertDatabaseCount('entries', 0);
    }

    public function test_snapshot_rejects_ranking_and_duplicate_bracket_entries_atomically(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 1)->setTime(6, 0));
        $fixture = $this->competition(5, 0, 'knockout', 1, 1);
        $payload = $this->payload($fixture);
        Sanctum::actingAs($fixture['admin'], ['competition:manage']);
        $payload['draw_method'] = 'ranking';
        $this->putJson($this->endpoint($fixture), $payload)->assertUnprocessable()->assertJsonValidationErrors('draw_method');
        $payload['draw_method'] = 'random';
        $payload['stages'][0]['bouts'][0] = ['athlete-1', 'athlete-1'];
        $this->putJson($this->endpoint($fixture), $payload)->assertUnprocessable()->assertJsonValidationErrors('operation');
        $this->assertDatabaseCount('entries', 0);
        $this->assertDatabaseCount('performances', 0);
        $this->assertSame(0, $fixture['category']->fresh()->management_version);
    }

    public function test_snapshot_rejects_a_category_from_another_tournament(): void
    {
        $fixture = $this->competition(5, 0, 'knockout', 1, 1);
        $other = $this->competition(5, 0, 'knockout', 1, 1);
        Sanctum::actingAs($fixture['admin'], ['competition:manage']);
        $url = '/api/v1/tournaments/'.$fixture['tournament']->id.'/categories/'.$other['category']->id.'/management-snapshot';
        $this->putJson($url, $this->payload($fixture))->assertNotFound();

        $this->assertDatabaseCount('entries', 0);
    }

    public function test_import_accepts_the_age_group_form_pool_and_stage_form_names(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 1)->setTime(6, 0));
        $fixture = $this->competition(5, 0, 'knockout', 1, 1);
        $payload = $this->payload($fixture);
        $payload['category']['form_names'] = array_map(fn ($number) => 'پومسهٔ مدیریت '.$number, range(1, 8));
        $payload['stages'][0]['form_ids'] = [];
        $payload['stages'][0]['form_names'] = ['پومسهٔ مدیریت 3', 'پومسهٔ مدیریت 7'];
        Sanctum::actingAs($fixture['admin'], ['competition:manage']);
        $this->putJson($this->endpoint($fixture), $payload)->assertOk();
        $this->assertSame(8, $fixture['category']->forms()->count());
        $round = $fixture['category']->rounds()->firstOrFail();
        $names = array_map(fn ($id) => $fixture['category']->forms()->whereKey($id)->value('name'), $round->form_sequence);
        $this->assertSame(['پومسهٔ مدیریت 3', 'پومسهٔ مدیریت 7'], $names);
    }

    public function test_management_can_create_a_category_and_its_stage_plan_through_the_api(): void
    {
        $fixture = $this->competition(5, 0, 'knockout', 1, 1);
        Sanctum::actingAs($fixture['admin'], ['competition:manage']);
        $this->postJson('/api/v1/tournaments/'.$fixture['tournament']->id.'/categories', [
            'name' => 'ردهٔ سیستم مدیریت', 'gender' => 'open', 'format' => 'round_robin', 'judge_count' => 5,
            'discard_each_end' => 1, 'rules_acknowledged' => true, 'planned_round_count' => 2,
            'stage_names' => ['مقدماتی', 'نهایی'], 'form_draw_timing' => 'day_before', 'form_draw_time' => '10:00',
            'allow_form_repetition' => false, 'draw_method' => 'random',
            'form_names' => array_map(fn ($number) => 'فرم مدیریت '.$number, range(1, 8)),
        ])->assertCreated()->assertJsonPath('data.planned_round_count', 2)
            ->assertJsonCount(8, 'data.form_pool')->assertJsonPath('data.stages.0.name', 'مقدماتی')->assertJsonPath('data.stages.1.name', 'نهایی');
    }
}
