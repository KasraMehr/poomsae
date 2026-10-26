<?php

namespace Tests\Feature;

use App\Actions\CompetitionConsole;
use App\Models\Performance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\CreatesCompetition;
use Tests\TestCase;

class ProxyScorePublicationTest extends TestCase
{
    use CreatesCompetition, RefreshDatabase;

    public function test_proxy_scores_must_be_confirmed_before_an_operator_can_publish(): void
    {
        $fixture = $this->competition(3, 2, 'knockout', 0);
        $actor = $fixture['operator'];
        $this->schedule($fixture);
        $performance = Performance::query()->orderBy('id')->firstOrFail();
        $this->startScoring($fixture, $performance);
        $parameters = [$fixture['tournament'], $performance];

        foreach ($performance->bout->judges()->orderBy('seat')->get() as $judge) {
            $this->actingAs($actor)->post(route('operations.proxy-score', $parameters), [
                'judge_assignment_id' => $judge->id,
                'request_id' => (string) Str::uuid(), 'expected_version' => $performance->fresh()->version,
                'expected_revision' => 0, 'reason' => 'قطعی ارتباط دستگاه داور در زمان اجرا',
                ...$this->detailedScoreInput('2.50', '6.00'),
            ])->assertRedirect()->assertSessionHasNoErrors();
        }

        $console = app(CompetitionConsole::class);
        $pending = $console->snapshot($fixture['tournament'], $actor)['courts'][0]['active'];
        $this->assertSame(0, $pending['submitted_count']);
        $this->assertSame([1, 2, 3], $pending['pending_review_seats']);
        $command = ['command' => 'approve', 'expected_version' => $performance->fresh()->version];
        $this->actingAs($fixture['operator'])->post(route('operations.command', $parameters), $command)->assertSessionHasErrors('operation');
        $this->assertDatabaseCount('results', 0);
        $this->assertSame('scoring', $performance->fresh()->status);

        foreach ($performance->scoreSheets()->orderBy('id')->get() as $index => $sheet) {
            $this->actingAs($fixture['operator'])->post(route('operations.proxy-score.confirm', [...$parameters, $sheet]), [
                'expected_revision' => $sheet->revision,
            ])->assertRedirect()->assertSessionHasNoErrors();
            $this->assertDatabaseHas('score_sheets', ['id' => $sheet->id, 'status' => 'submitted', 'confirmed_by' => $actor->id]);
            $this->assertDatabaseHas('audit_logs', ['action' => 'score.proxy_confirmed', 'subject_id' => $sheet->id, 'user_id' => $actor->id]);
            $this->assertSame($index + 1, $console->snapshot($fixture['tournament'], $actor)['courts'][0]['active']['submitted_count']);
        }

        $this->actingAs($fixture['operator'])->post(route('operations.command', $parameters), $command)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('performances', ['id' => $performance->id, 'status' => 'approved', 'approved_by' => $actor->id]);
        $this->assertDatabaseHas('results', ['performance_id' => $performance->id, 'score' => '8.500000']);
        $this->assertNotNull($performance->result()->firstOrFail()->published_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'performance.approve', 'subject_id' => $performance->id, 'user_id' => $actor->id]);
    }
}
