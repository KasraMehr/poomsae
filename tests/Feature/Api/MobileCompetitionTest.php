<?php

namespace Tests\Feature\Api;

use App\Models\JudgeAssignment;
use App\Models\Performance;
use App\Models\ScoreSheet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        Sanctum::actingAs($fixture['operator'], ['tournaments:read', 'competition:operate']);
        $this->getJson($url)->assertOk()->assertJsonCount(2, 'data.scores');
    }

    public function test_operator_token_generation_includes_competition_operate_ability(): void
    {
        $operator = User::factory()->create(['password' => bcrypt('ValidPassword123!')]);
        $tournament = $this->competition()['tournament'];
        $tournament->users()->attach($operator->id, ['role' => 'operator']);

        $response = $this->postJson('/api/v1/auth/token', [
            'email' => $operator->email,
            'password' => 'ValidPassword123!',
            'device_name' => 'Operator Tablet',
        ])->assertCreated();

        $tokenRecord = $operator->tokens()->first();
        $this->assertNotNull($tokenRecord);
        $this->assertTrue($tokenRecord->can('competition:operate'));
        $this->assertTrue($tokenRecord->can('tournaments:read'));
    }

    public function test_operator_can_approve_scores_via_command_api_and_results_are_published(): void
    {
        $fixture = $this->competition();
        $this->schedule($fixture);
        $performance = Performance::query()->firstOrFail();
        $this->startScoring($fixture, $performance);

        // Submit scores for all 5 judges via REST
        foreach ($fixture['judges'] as $index => $judge) {
            Sanctum::actingAs($judge, ['tournaments:read', 'scores:write']);
            $this->postJson('/api/v1/tournaments/'.$fixture['tournament']->id.'/performances/'.$performance->id.'/scores', [
                'request_id' => (string) Str::uuid(),
                'expected_version' => $performance->fresh()->version,
                'expected_revision' => 0,
                ...$this->detailedScoreInput('2.50', '6.00'),
            ])->assertOk();
        }

        // Operator approves via REST API command
        $operator = User::factory()->create();
        $fixture['tournament']->users()->attach($operator->id, ['role' => 'operator']);
        Sanctum::actingAs($operator, ['tournaments:read', 'competition:operate']);

        $commandResponse = $this->postJson('/api/v1/tournaments/'.$fixture['tournament']->id.'/performances/'.$performance->id.'/command', [
            'command' => 'approve',
            'expected_version' => $performance->fresh()->version,
        ])->assertOk();

        $commandResponse->assertJsonPath('data.status', 'approved');
        $commandResponse->assertJsonPath('data.result.score', '8.500000');

        $this->assertDatabaseHas('performances', [
            'id' => $performance->id,
            'status' => 'approved',
        ]);

        $this->assertDatabaseHas('results', [
            'performance_id' => $performance->id,
            'score' => '8.500000',
        ]);

        // Front-end can fetch performance result via REST
        $this->getJson('/api/v1/tournaments/'.$fixture['tournament']->id.'/performances/'.$performance->id.'/result')
            ->assertOk()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.result.score', '8.500000')
            ->assertJsonCount(5, 'data.scores');

        // Front-end display-data returns updated court state
        $displayUser = User::factory()->create();
        $fixture['tournament']->users()->attach($displayUser, ['role' => 'display']);
        Sanctum::actingAs($displayUser, ['tournaments:read']);
        $displayResponse = $this->getJson('/api/v1/tournaments/'.$fixture['tournament']->id.'/display-data')
            ->assertOk()
            ->assertJsonPath('changed', true);
        $this->assertNotNull($displayResponse->json('data.revision'));
    }

    public function test_operator_can_submit_batch_scores_and_approve_in_single_request(): void
    {
        $fixture = $this->competition();
        $this->schedule($fixture);
        $performance = Performance::query()->firstOrFail();
        $this->startScoring($fixture, $performance);

        $operator = User::factory()->create();
        $fixture['tournament']->users()->attach($operator->id, ['role' => 'operator']);
        Sanctum::actingAs($operator, ['tournaments:read', 'competition:operate']);

        $assignments = JudgeAssignment::where('bout_id', $performance->bout_id)->orderBy('seat')->get();
        $scoresPayload = [];
        foreach ($assignments as $assignment) {
            $scoresPayload[] = [
                'request_id' => (string) Str::uuid(),
                'judge_assignment_id' => $assignment->id,
                'expected_revision' => 0,
                'reason' => 'ثبت دسته‌ای نمرات داوران تبلت',
                ...$this->detailedScoreInput('2.60', '5.80'),
            ];
        }

        $batchResponse = $this->postJson('/api/v1/tournaments/'.$fixture['tournament']->id.'/performances/'.$performance->id.'/scores/batch', [
            'expected_version' => $performance->fresh()->version,
            'approve' => true,
            'reason' => 'ثبت کلیه نمرات توسط اپراتور',
            'scores' => $scoresPayload,
        ])->assertOk();

        $batchResponse->assertJsonPath('data.status', 'approved');
        $batchResponse->assertJsonPath('data.approved', true);
        $batchResponse->assertJsonPath('data.result.score', '8.400000');

        $this->assertDatabaseHas('performances', [
            'id' => $performance->id,
            'status' => 'approved',
        ]);
        $this->assertDatabaseHas('results', [
            'performance_id' => $performance->id,
            'score' => '8.400000',
        ]);
    }

    public function test_five_judge_api_flow_handles_one_failed_tablet_with_audited_manual_score(): void
    {
        $fixture = $this->competition();
        $this->schedule($fixture);
        $performance = Performance::query()->firstOrFail();
        $this->startScoring($fixture, $performance);
        $tournament = $fixture['tournament'];
        $scoreUrl = '/api/v1/tournaments/'.$tournament->id.'/performances/'.$performance->id.'/scores';

        foreach ($fixture['judges']->take(4) as $judge) {
            Sanctum::actingAs($judge, ['tournaments:read', 'scores:write']);
            $this->postJson($scoreUrl, [
                'request_id' => (string) Str::uuid(),
                'expected_version' => $performance->fresh()->version,
                'expected_revision' => 0,
                ...$this->detailedScoreInput('2.50', '6.00'),
            ])->assertOk()->assertJsonPath('data.revision', 1);
        }

        $operator = User::factory()->create();
        $tournament->users()->attach($operator->id, ['role' => 'operator']);
        $missingAssignment = JudgeAssignment::where('bout_id', $performance->bout_id)->orderByDesc('seat')->firstOrFail();
        $baseUrl = '/api/v1/tournaments/'.$tournament->id.'/performances/'.$performance->id;
        Sanctum::actingAs($operator, ['tournaments:read', 'competition:operate']);

        $this->postJson($baseUrl.'/proxy-scores', [
            'request_id' => (string) Str::uuid(),
            'judge_assignment_id' => $missingAssignment->id,
            'expected_version' => $performance->fresh()->version,
            'expected_revision' => 0,
            ...$this->detailedScoreInput('2.50', '6.00'),
            'reason' => 'Judge tablet disconnected during scoring.',
        ])->assertOk()->assertJsonPath('data.requires_confirmation', true);

        $sheet = ScoreSheet::where('judge_assignment_id', $missingAssignment->id)->firstOrFail();
        $this->postJson($baseUrl.'/command', [
            'command' => 'approve',
            'expected_version' => $performance->fresh()->version,
        ])->assertStatus(422);

        $this->postJson($baseUrl.'/proxy-scores/'.$sheet->id.'/confirm', [
            'expected_revision' => $sheet->revision,
        ])->assertOk();

        $this->postJson($baseUrl.'/command', [
            'command' => 'approve',
            'expected_version' => $performance->fresh()->version,
        ])->assertOk()->assertJsonPath('data.status', 'approved');

        $this->getJson($baseUrl.'/result')->assertOk()->assertJsonCount(5, 'data.scores');
        $this->assertDatabaseHas('score_sheets', [
            'id' => $sheet->id,
            'status' => 'submitted',
            'submission_mode' => 'operator_proxy',
            'confirmed_by' => $operator->id,
        ]);
    }
}
