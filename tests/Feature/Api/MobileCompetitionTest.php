<?php

namespace Tests\Feature\Api;

use App\Models\Performance;
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
}
