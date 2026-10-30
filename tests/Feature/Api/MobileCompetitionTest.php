<?php

namespace Tests\Feature\Api;

use App\Events\CompetitionUpdated;
use App\Models\Performance;
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
}
