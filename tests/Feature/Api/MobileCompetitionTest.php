<?php

namespace Tests\Feature\Api;

use App\Actions\CompetitionSetup;
use App\Events\CompetitionUpdated;
use App\Models\Performance;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\CreatesCompetition;
use Tests\TestCase;

class MobileCompetitionTest extends TestCase
{
    use CreatesCompetition, RefreshDatabase;

    public function test_mobile_judge_receives_realtime_contract_and_can_submit_score(): void
    {
        $fixture = $this->competition();
        $this->schedule($fixture);
        $performance = Performance::query()->firstOrFail();
        $this->startScoring($fixture, $performance);
        $judge = $fixture['judges'][0];
        Sanctum::actingAs($judge, ['tournaments:read', 'scores:write']);

        $this->getJson('/api/v1/tournaments/'.$fixture['tournament']->id.'/competition')
            ->assertOk()
            ->assertJsonPath('data.id', $fixture['tournament']->id)
            ->assertJsonPath('realtime.protocol', 'mercure-sse')
            ->assertJsonPath('realtime.hub_url', url('/.well-known/mercure'))
            ->assertJsonPath('realtime.channel', 'private-tournaments.'.$fixture['tournament']->id)
            ->assertJsonPath('realtime.event', 'competition.updated');

        $this->postJson('/api/v1/tournaments/'.$fixture['tournament']->id.'/performances/'.$performance->id.'/scores', [
            'request_id' => (string) Str::uuid(),
            'expected_version' => $performance->fresh()->version,
            'expected_revision' => 0,
            ...$this->detailedScoreInput('2.50', '6.00'),
        ])->assertOk()->assertJsonPath('data.revision', 1);

        $this->assertDatabaseHas('score_sheets', [
            'submitted_by' => $judge->id,
            'submission_mode' => 'judge',
        ]);
    }

    public function test_mobile_score_endpoint_requires_write_ability_and_assignment(): void
    {
        $fixture = $this->competition();
        $this->schedule($fixture);
        $performance = Performance::query()->firstOrFail();
        $this->startScoring($fixture, $performance);
        $payload = [
            'request_id' => (string) Str::uuid(), 'expected_version' => $performance->fresh()->version,
            'expected_revision' => 0, ...$this->detailedScoreInput('2.50', '6.00'),
        ];
        $url = '/api/v1/tournaments/'.$fixture['tournament']->id.'/performances/'.$performance->id.'/scores';

        Sanctum::actingAs($fixture['judges'][0], ['tournaments:read']);
        $this->postJson($url, $payload)->assertForbidden();
    }

    public function test_unpublished_api_result_only_exposes_the_judges_own_sheet(): void
    {
        $fixture = $this->competition();
        $this->schedule($fixture);
        $performance = Performance::query()->firstOrFail();
        $this->startScoring($fixture, $performance);
        $url = '/api/v1/tournaments/'.$fixture['tournament']->id.'/performances/'.$performance->id.'/result';

        foreach ($fixture['judges']->take(2) as $judge) {
            Sanctum::actingAs($judge, ['tournaments:read', 'scores:write']);
            $this->postJson('/api/v1/tournaments/'.$fixture['tournament']->id.'/performances/'.$performance->id.'/scores', [
                'request_id' => (string) Str::uuid(),
                'expected_version' => $performance->fresh()->version,
                'expected_revision' => 0,
                ...$this->detailedScoreInput('2.50', '6.00'),
            ])->assertOk();
        }

        Sanctum::actingAs($fixture['judges'][0], ['tournaments:read']);
        $this->getJson($url)->assertOk()->assertJsonCount(1, 'data.scores')
            ->assertJsonPath('data.scores.0.judge_name', $fixture['judges'][0]->name);

        Sanctum::actingAs($fixture['judges'][2], ['tournaments:read']);
        $this->getJson($url)->assertOk()->assertJsonCount(0, 'data.scores');

        Sanctum::actingAs($fixture['admin'], ['tournaments:read', 'competition:manage']);
        $this->getJson($url)->assertOk()->assertJsonCount(2, 'data.scores');
    }

    public function test_operator_cannot_request_mobile_api_token(): void
    {
        $operator = User::factory()->create(['password' => bcrypt('ValidPassword123!')]);
        $tournament = $this->competition()['tournament'];
        $tournament->users()->attach($operator->id, ['role' => 'operator']);

        $this->postJson('/api/v1/auth/token', [
            'email' => $operator->email,
            'password' => 'ValidPassword123!',
            'device_name' => 'Operator Device',
        ])->assertForbidden();
    }

    public function test_operator_command_endpoints_are_not_available_on_mobile_api(): void
    {
        $fixture = $this->competition();
        $this->schedule($fixture);
        $performance = Performance::query()->firstOrFail();
        $this->startScoring($fixture, $performance);

        $judge = $fixture['judges'][0];
        Sanctum::actingAs($judge, ['tournaments:read', 'scores:write']);

        $this->postJson('/api/v1/tournaments/'.$fixture['tournament']->id.'/performances/'.$performance->id.'/command', [
            'command' => 'approve',
            'expected_version' => $performance->fresh()->version,
        ])->assertNotFound();

        $this->postJson('/api/v1/tournaments/'.$fixture['tournament']->id.'/performances/'.$performance->id.'/scores/batch', [
            'expected_version' => $performance->fresh()->version,
            'scores' => [],
        ])->assertNotFound();
    }

    public function test_mobile_judges_submit_scores_via_api_and_web_operator_approves_via_web(): void
    {
        $fixture = $this->competition();
        $this->schedule($fixture);
        $performance = Performance::query()->firstOrFail();
        $this->startScoring($fixture, $performance);

        // Submit scores for all 5 judges via REST
        foreach ($fixture['judges'] as $judge) {
            Sanctum::actingAs($judge, ['tournaments:read', 'scores:write']);
            $this->postJson('/api/v1/tournaments/'.$fixture['tournament']->id.'/performances/'.$performance->id.'/scores', [
                'request_id' => (string) Str::uuid(),
                'expected_version' => $performance->fresh()->version,
                'expected_revision' => 0,
                ...$this->detailedScoreInput('2.50', '6.00'),
            ])->assertOk();
        }

        // Web operator approves via the web route
        $this->actingAs($fixture['operator'])->post(
            route('operations.command', [$fixture['tournament'], $performance]),
            [
                'command' => 'approve',
                'expected_version' => $performance->fresh()->version,
            ]
        )->assertRedirect();

        $this->assertDatabaseHas('performances', [
            'id' => $performance->id,
            'status' => 'approved',
        ]);

        $this->assertDatabaseHas('results', [
            'performance_id' => $performance->id,
            'score' => '8.500000',
        ]);

        // Display-data returns updated court state
        $displayUser = User::factory()->create();
        $fixture['tournament']->users()->attach($displayUser, ['role' => 'display']);
        Sanctum::actingAs($displayUser, ['tournaments:read']);
        $displayResponse = $this->getJson('/api/v1/tournaments/'.$fixture['tournament']->id.'/display-data')
            ->assertOk()
            ->assertJsonPath('changed', true);
        $this->assertNotNull($displayResponse->json('data.revision'));
    }

    public function test_judge_can_fetch_thin_client_current_state(): void
    {
        $fixture = $this->competition();
        $this->schedule($fixture);
        $performance = Performance::query()->firstOrFail();
        $this->startScoring($fixture, $performance);

        $judge = $fixture['judges'][0];
        Sanctum::actingAs($judge, ['tournaments:read', 'scores:write']);

        $response = $this->getJson('/api/v1/judge/current-state')->assertOk();

        $response->assertJsonPath('judge.id', $judge->id)
            ->assertJsonPath('judge.name', $judge->name)
            ->assertJsonPath('judge.seat_number', 1)
            ->assertJsonPath('tournament.id', $fixture['tournament']->id)
            ->assertJsonPath('realtime.protocol', 'mercure-sse')
            ->assertJsonPath('realtime.channel', 'private-tournaments.'.$fixture['tournament']->id)
            ->assertJsonPath('active_performance.id', $performance->id)
            ->assertJsonPath('active_performance.status', 'scoring')
            ->assertJsonPath('active_performance.rules.judge_count', 5)
            ->assertJsonPath('active_performance.my_submission.is_submitted', false);

        $this->assertNotEmpty($response->json('realtime.mercure_token'));
    }

    public function test_judge_can_submit_score_via_simplified_endpoint_without_concurrency_fields(): void
    {
        $fixture = $this->competition();
        $this->schedule($fixture);
        $performance = Performance::query()->firstOrFail();
        $this->startScoring($fixture, $performance);

        $judge = $fixture['judges'][1];
        Sanctum::actingAs($judge, ['tournaments:read', 'scores:write']);

        // Simplified payload with only score components, omitting request_id, expected_version, expected_revision
        $this->postJson('/api/v1/performances/'.$performance->id.'/scores', [
            ...$this->detailedScoreInput('3.20', '5.50'),
        ])->assertOk()->assertJsonPath('data.revision', 1);

        $this->assertDatabaseHas('score_sheets', [
            'performance_id' => $performance->id,
            'submitted_by' => $judge->id,
            'submission_mode' => 'judge',
            'status' => 'submitted',
        ]);

        // After submit, current-state reflects my_submission
        $stateResponse = $this->getJson('/api/v1/judge/current-state')->assertOk();
        $stateResponse->assertJsonPath('active_performance.my_submission.is_submitted', true)
            ->assertJsonPath('active_performance.my_submission.accuracy', '3.20')
            ->assertJsonPath('active_performance.my_submission.presentation', '5.50');
    }

    public function test_operator_command_publishes_enriched_mercure_payload(): void
    {
        Event::fake([CompetitionUpdated::class]);

        $fixture = $this->competition();
        $this->schedule($fixture);
        $performance = Performance::query()->firstOrFail();

        // Operator starts performance via web route
        $this->actingAs($fixture['operator'])->post(
            route('operations.command', [$fixture['tournament'], $performance]),
            [
                'command' => 'start',
                'expected_version' => $performance->version,
            ]
        )->assertRedirect();

        Event::assertDispatched(CompetitionUpdated::class, function ($event) use ($fixture, $performance) {
            $data = $event->broadcastWith();

            return $data['tournament_id'] === $fixture['tournament']->id
                && $data['action'] === 'start'
                && ($data['should_refresh'] ?? false) === true
                && isset($data['active_performance'])
                && $data['active_performance']['id'] === $performance->id
                && $data['active_performance']['status'] === 'running';
        });
    }

    public function test_judge_current_state_returns_pair_athletes_and_entry_type(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $tournament = Tournament::factory()->create(['created_by' => $admin->id]);
        $setup = app(CompetitionSetup::class);
        $setup->court($admin, $tournament, ['name' => 'زمین یک']);
        $setup->category($admin, $tournament, [
            'name' => 'تیمی دونفره (جفتی)',
            'entry_type' => 'pair',
            'gender' => 'male',
            'minimum_age' => 10,
            'maximum_age' => 40,
            'format' => 'knockout',
            'judge_count' => 5,
            'accuracy_max' => 400,
            'discard_each_end' => 1,
            'rules_acknowledged' => true,
            'form_names' => ['فرم اول', 'فرم دوم'],
        ]);
        $category = $tournament->categories()->firstOrFail();

        for ($i = 0; $i < 2; $i++) {
            $setup->entry($admin, $tournament, $category, [
                'members' => [
                    ['first_name' => 'یار_اول_'.$i, 'last_name' => 'تیم', 'birth_date' => '2005-01-01', 'gender' => 'male', 'club' => 'باشگاه الف'],
                    ['first_name' => 'یار_دوم_'.$i, 'last_name' => 'تیم', 'birth_date' => '2005-01-02', 'gender' => 'male', 'club' => 'باشگاه الف'],
                ],
            ]);
        }
        foreach ($category->entries()->get() as $entry) {
            $setup->entryStatus($admin, $tournament, $entry, 'checked_in');
        }
        $judges = User::factory()->count(5)->create();
        foreach ($judges as $judge) {
            $tournament->users()->attach($judge->id, ['role' => 'judge']);
        }
        $fixture = compact('admin', 'tournament', 'category', 'judges');
        $this->schedule($fixture);

        $performance = Performance::query()->firstOrFail();
        $this->startScoring($fixture, $performance);

        $judge = $judges[0];
        Sanctum::actingAs($judge, ['tournaments:read', 'scores:write']);

        $response = $this->getJson('/api/v1/judge/current-state')->assertOk();

        $athletes = $performance->entry->athletes()->orderBy('entry_members.position')->get();

        $response->assertJsonPath('active_performance.entry_type', 'pair')
            ->assertJsonCount(2, 'active_performance.athletes')
            ->assertJsonPath('active_performance.athletes.0.name', $athletes[0]->first_name.' '.$athletes[0]->last_name)
            ->assertJsonPath('active_performance.athletes.0.position', 1)
            ->assertJsonPath('active_performance.athletes.1.name', $athletes[1]->first_name.' '.$athletes[1]->last_name)
            ->assertJsonPath('active_performance.athletes.1.position', 2)
            ->assertJsonPath('active_performance.athlete.name', $athletes[0]->first_name.' '.$athletes[0]->last_name);
    }

    public function test_judge_current_state_returns_team_athletes_and_entry_type(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $tournament = Tournament::factory()->create(['created_by' => $admin->id]);
        $setup = app(CompetitionSetup::class);
        $setup->court($admin, $tournament, ['name' => 'زمین یک']);
        $setup->category($admin, $tournament, [
            'name' => 'تیمی سه‌نفره',
            'entry_type' => 'team',
            'gender' => 'male',
            'minimum_age' => 10,
            'maximum_age' => 40,
            'format' => 'knockout',
            'judge_count' => 5,
            'accuracy_max' => 400,
            'discard_each_end' => 1,
            'rules_acknowledged' => true,
            'form_names' => ['فرم اول', 'فرم دوم'],
        ]);
        $category = $tournament->categories()->firstOrFail();

        for ($i = 0; $i < 2; $i++) {
            $setup->entry($admin, $tournament, $category, [
                'members' => [
                    ['first_name' => 'یار_اول_'.$i, 'last_name' => 'سه', 'birth_date' => '2005-01-01', 'gender' => 'male', 'club' => 'باشگاه ب'],
                    ['first_name' => 'یار_دوم_'.$i, 'last_name' => 'سه', 'birth_date' => '2005-01-02', 'gender' => 'male', 'club' => 'باشگاه ب'],
                    ['first_name' => 'یار_سوم_'.$i, 'last_name' => 'سه', 'birth_date' => '2005-01-03', 'gender' => 'male', 'club' => 'باشگاه ب'],
                ],
            ]);
        }
        foreach ($category->entries()->get() as $entry) {
            $setup->entryStatus($admin, $tournament, $entry, 'checked_in');
        }
        $judges = User::factory()->count(5)->create();
        foreach ($judges as $judge) {
            $tournament->users()->attach($judge->id, ['role' => 'judge']);
        }
        $fixture = compact('admin', 'tournament', 'category', 'judges');
        $this->schedule($fixture);

        $performance = Performance::query()->firstOrFail();
        $this->startScoring($fixture, $performance);

        $judge = $judges[0];
        Sanctum::actingAs($judge, ['tournaments:read', 'scores:write']);

        $response = $this->getJson('/api/v1/judge/current-state')->assertOk();

        $athletes = $performance->entry->athletes()->orderBy('entry_members.position')->get();

        $response->assertJsonPath('active_performance.entry_type', 'team')
            ->assertJsonCount(3, 'active_performance.athletes')
            ->assertJsonPath('active_performance.athletes.0.name', $athletes[0]->first_name.' '.$athletes[0]->last_name)
            ->assertJsonPath('active_performance.athletes.0.position', 1)
            ->assertJsonPath('active_performance.athletes.1.name', $athletes[1]->first_name.' '.$athletes[1]->last_name)
            ->assertJsonPath('active_performance.athletes.1.position', 2)
            ->assertJsonPath('active_performance.athletes.2.name', $athletes[2]->first_name.' '.$athletes[2]->last_name)
            ->assertJsonPath('active_performance.athletes.2.position', 3);
    }

    public function test_judge_current_state_supports_simultaneous_knockout_with_paired_performance(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $tournament = Tournament::factory()->create(['created_by' => $admin->id]);
        $setup = app(CompetitionSetup::class);
        $setup->court($admin, $tournament, ['name' => 'زمین یک']);
        $setup->category($admin, $tournament, [
            'name' => 'تک‌حذفی همزمان',
            'entry_type' => 'individual',
            'gender' => 'male',
            'minimum_age' => 10,
            'maximum_age' => 40,
            'format' => 'knockout',
            'execution_mode' => 'simultaneous',
            'judge_count' => 5,
            'accuracy_max' => 400,
            'discard_each_end' => 1,
            'rules_acknowledged' => true,
            'form_names' => ['فرم اول', 'فرم دوم'],
        ]);
        $category = $tournament->categories()->firstOrFail();

        for ($i = 0; $i < 2; $i++) {
            $setup->entry($admin, $tournament, $category, [
                'first_name' => 'بازیکن_'.$i,
                'last_name' => 'تک‌حذفی',
                'birth_date' => '2005-01-01',
                'gender' => 'male',
                'club' => 'باشگاه '.$i,
            ]);
        }
        foreach ($category->entries()->get() as $entry) {
            $setup->entryStatus($admin, $tournament, $entry, 'checked_in');
        }
        $judges = User::factory()->count(5)->create();
        foreach ($judges as $judge) {
            $tournament->users()->attach($judge->id, ['role' => 'judge']);
        }
        $fixture = compact('admin', 'tournament', 'category', 'judges');
        $this->schedule($fixture);

        $performances = Performance::query()->orderBy('id')->get();
        $this->assertCount(4, $performances); // 2 entries * 2 forms = 4 performances

        // Start scoring for the simultaneous group
        $perf1 = $performances[0];
        $this->startScoring($fixture, $perf1);

        $judge = $judges[0];
        Sanctum::actingAs($judge, ['tournaments:read', 'scores:write']);

        $response = $this->getJson('/api/v1/judge/current-state')->assertOk();

        $response->assertJsonPath('active_performance.id', $perf1->id)
            ->assertJsonPath('active_performance.entry_type', 'individual');

        $this->assertNotNull($response->json('active_performance.paired_performance'));

        $pairedId = $response->json('active_performance.paired_performance.id');
        $this->assertNotEquals($perf1->id, $pairedId);

        // Judge can submit score for first performance
        $this->postJson('/api/v1/performances/'.$perf1->id.'/scores', [
            ...$this->detailedScoreInput('3.50', '5.00'),
        ])->assertOk();

        // Judge can also submit score for the paired simultaneous performance
        $this->postJson('/api/v1/performances/'.$pairedId.'/scores', [
            ...$this->detailedScoreInput('3.80', '5.20'),
        ])->assertOk();

        // Check that current-state reflects both submissions
        $updatedResponse = $this->getJson('/api/v1/judge/current-state')->assertOk();
        $updatedResponse->assertJsonPath('active_performance.my_submission.is_submitted', true)
            ->assertJsonPath('active_performance.my_submission.accuracy', '3.50')
            ->assertJsonPath('active_performance.paired_performance.my_submission.is_submitted', true)
            ->assertJsonPath('active_performance.paired_performance.my_submission.accuracy', '3.80');
    }
}
