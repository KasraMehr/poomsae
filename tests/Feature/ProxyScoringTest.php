<?php

namespace Tests\Feature;

use App\Models\Performance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\CreatesCompetition;
use Tests\TestCase;

class ProxyScoringTest extends TestCase
{
    use CreatesCompetition, RefreshDatabase;

    public function test_operator_can_record_a_traceable_score_for_a_disconnected_judge(): void
    {
        $fixture = $this->competition();
        $this->schedule($fixture);
        $performance = Performance::query()->firstOrFail();
        $this->startScoring($fixture, $performance);
        $assignment = $performance->bout->judges()->orderBy('seat')->firstOrFail();
        $url = route('operations.proxy-score', [$fixture['tournament'], $performance]);

        $this->actingAs($fixture['admin'])->post($url, [
            'request_id' => (string) Str::uuid(),
            'judge_assignment_id' => $assignment->id,
            'expected_version' => $performance->fresh()->version,
            'expected_revision' => 0,
            'accuracy' => '2.50',
            'presentation' => '6.00',
            'reason' => 'تبلت صندلی یک از شبکه خارج شد.',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertDatabaseHas('score_sheets', [
            'judge_assignment_id' => $assignment->id,
            'submitted_by' => $fixture['admin']->id,
            'submission_mode' => 'operator_proxy',
        ]);
        $this->assertDatabaseHas('score_revisions', ['changed_by' => $fixture['admin']->id, 'revision' => 1]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'score.proxy_submitted', 'user_id' => $fixture['admin']->id]);
    }

    public function test_proxy_score_requires_operator_a_missing_seat_and_a_reason(): void
    {
        $fixture = $this->competition();
        $this->schedule($fixture);
        $performance = Performance::query()->firstOrFail();
        $this->startScoring($fixture, $performance);
        $assignment = $performance->bout->judges()->orderBy('seat')->firstOrFail();
        $url = route('operations.proxy-score', [$fixture['tournament'], $performance]);
        $payload = [
            'request_id' => (string) Str::uuid(), 'judge_assignment_id' => $assignment->id,
            'expected_version' => $performance->fresh()->version, 'expected_revision' => 0,
            'accuracy' => '2.50', 'presentation' => '6.00', 'reason' => 'کوتاه',
        ];

        $this->actingAs($fixture['judges'][0])->post($url, $payload)->assertForbidden();
        $this->actingAs($fixture['admin'])->post($url, $payload)->assertSessionHasErrors('reason');

        $this->actingAs($fixture['judges'][0])->post(route('judging.store', [$fixture['tournament'], $performance]), [
            'request_id' => (string) Str::uuid(), 'expected_version' => $performance->fresh()->version,
            'expected_revision' => 0, 'accuracy' => '2.50', 'presentation' => '6.00',
        ])->assertSessionHasNoErrors();

        $this->actingAs($fixture['admin'])->post($url, [...$payload, 'request_id' => (string) Str::uuid(), 'reason' => 'ارتباط داور قطع شده است.'])
            ->assertStatus(409);
    }
}
