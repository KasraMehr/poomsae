<?php

namespace Tests\Feature;

use App\Actions\CompetitionConsole;
use App\Actions\SubmitScore;
use App\Models\Performance;
use App\Models\ScoreSheet;
use App\Models\User;
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
            ...$this->detailedScoreInput('2.50', '6.00'),
            'reason' => 'تبلت صندلی یک از شبکه خارج شد.',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertDatabaseHas('score_sheets', [
            'judge_assignment_id' => $assignment->id,
            'submitted_by' => $fixture['admin']->id,
            'submission_mode' => 'operator_proxy',
            'status' => 'draft',
            'confirmed_by' => null,
            'confirmed_at' => null,
        ]);
        $this->assertDatabaseHas('score_revisions', ['changed_by' => $fixture['admin']->id, 'revision' => 1]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'score.proxy_submitted', 'user_id' => $fixture['admin']->id]);

        $active = app(CompetitionConsole::class)->snapshot($fixture['tournament'], $fixture['admin'])['courts'][0]['active'];
        $this->assertSame([$assignment->seat], $active['pending_review_seats']);
        $this->assertSame(0, $active['submitted_count']);
        $this->assertSame($fixture['admin']->id, $active['scores'][0]['submitted_by']);
        $this->assertSame('draft', $active['scores'][0]['status']);
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
            ...$this->detailedScoreInput('2.50', '6.00'), 'reason' => 'کوتاه',
        ];

        $this->actingAs($fixture['judges'][0])->post($url, $payload)->assertForbidden();
        $this->actingAs($fixture['admin'])->post($url, $payload)->assertSessionHasErrors('reason');

        $this->actingAs($fixture['judges'][0])->post(route('judging.store', [$fixture['tournament'], $performance]), [
            'request_id' => (string) Str::uuid(), 'expected_version' => $performance->fresh()->version,
            'expected_revision' => 0, ...$this->detailedScoreInput('2.50', '6.00'),
        ])->assertSessionHasNoErrors();

        $this->actingAs($fixture['admin'])->post($url, [...$payload, 'request_id' => (string) Str::uuid(), 'reason' => 'ارتباط داور قطع شده است.'])
            ->assertStatus(409);
    }

    public function test_proxy_score_requires_confirmation_by_another_operator_before_publication(): void
    {
        $fixture = $this->competition();
        $this->schedule($fixture);
        $performance = Performance::query()->firstOrFail();
        $this->startScoring($fixture, $performance);
        $assignment = $performance->bout->judges()->orderBy('seat')->firstOrFail();

        $this->actingAs($fixture['admin'])->post(route('operations.proxy-score', [$fixture['tournament'], $performance]), [
            'request_id' => (string) Str::uuid(),
            'judge_assignment_id' => $assignment->id,
            'expected_version' => $performance->fresh()->version,
            'expected_revision' => 0,
            ...$this->detailedScoreInput('2.50', '6.00'),
            'reason' => 'تبلت صندلی یک از شبکه خارج شد.',
        ])->assertSessionHasNoErrors();

        foreach ($fixture['judges']->where('id', '!=', $assignment->user_id) as $judge) {
            app(SubmitScore::class)->handle($judge, $fixture['tournament'], $performance, [
                'request_id' => (string) Str::uuid(),
                'expected_version' => $performance->fresh()->version,
                'expected_revision' => 0,
                ...$this->detailedScoreInput('2.50', '6.00'),
            ]);
        }

        $this->actingAs($fixture['admin'])->post(route('operations.command', [$fixture['tournament'], $performance]), [
            'command' => 'approve', 'expected_version' => $performance->fresh()->version,
        ])->assertSessionHasErrors('operation');

        $scoreSheet = ScoreSheet::where('judge_assignment_id', $assignment->id)->firstOrFail();
        $confirmUrl = route('operations.proxy-score.confirm', [$fixture['tournament'], $performance, $scoreSheet]);
        $this->post($confirmUrl, ['expected_revision' => 1])->assertSessionHasErrors('operation');

        $this->actingAs($fixture['judges']->firstWhere('id', $assignment->user_id))->post(route('judging.store', [$fixture['tournament'], $performance]), [
            'request_id' => (string) Str::uuid(),
            'expected_version' => $performance->fresh()->version,
            'expected_revision' => 0,
            ...$this->detailedScoreInput('2.50', '6.00'),
        ])->assertStatus(409);

        $reviewer = User::factory()->create();
        $fixture['tournament']->users()->attach($reviewer, ['role' => 'operator']);
        $this->actingAs($reviewer)->post($confirmUrl, ['expected_revision' => 1])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('score_sheets', [
            'id' => $scoreSheet->id,
            'status' => 'submitted',
            'confirmed_by' => $reviewer->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'score.proxy_confirmed',
            'subject_id' => $scoreSheet->id,
            'user_id' => $reviewer->id,
        ]);

        $this->actingAs($fixture['judges']->firstWhere('id', $assignment->user_id))->post(route('judging.store', [$fixture['tournament'], $performance]), [
            'request_id' => (string) Str::uuid(),
            'expected_version' => $performance->fresh()->version,
            'expected_revision' => 1,
            ...$this->detailedScoreInput('2.40', '5.90'),
            'reason' => 'ارسال دیرهنگام تبلت داور',
        ])->assertSessionHasErrors('operation');

        $this->actingAs($fixture['admin'])->post(route('operations.command', [$fixture['tournament'], $performance]), [
            'command' => 'approve', 'expected_version' => $performance->fresh()->version,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('results', ['performance_id' => $performance->id, 'score' => '8.500000']);
    }

    public function test_judge_can_replace_an_unconfirmed_proxy_score_with_a_traced_revision(): void
    {
        $fixture = $this->competition();
        $this->schedule($fixture);
        $performance = Performance::query()->firstOrFail();
        $this->startScoring($fixture, $performance);
        $assignment = $performance->bout->judges()->orderBy('seat')->firstOrFail();

        $this->actingAs($fixture['admin'])->post(route('operations.proxy-score', [$fixture['tournament'], $performance]), [
            'request_id' => (string) Str::uuid(),
            'judge_assignment_id' => $assignment->id,
            'expected_version' => $performance->fresh()->version,
            'expected_revision' => 0,
            ...$this->detailedScoreInput('2.50', '6.00'),
            'reason' => 'تبلت صندلی یک از شبکه خارج شد.',
        ])->assertSessionHasNoErrors();

        $judge = $fixture['judges']->firstWhere('id', $assignment->user_id);
        $this->actingAs($judge)->post(route('judging.store', [$fixture['tournament'], $performance]), [
            'request_id' => (string) Str::uuid(),
            'expected_version' => $performance->fresh()->version,
            'expected_revision' => 1,
            ...$this->detailedScoreInput('2.40', '5.90'),
            'reason' => 'اصلاح نمرهٔ ثبت‌شده توسط اپراتور',
        ])->assertSessionHasNoErrors();

        $scoreSheet = ScoreSheet::where('judge_assignment_id', $assignment->id)->firstOrFail();
        $this->assertSame('judge', $scoreSheet->submission_mode);
        $this->assertSame('submitted', $scoreSheet->status);
        $this->assertSame(2, $scoreSheet->revision);
        $this->assertSame($judge->id, $scoreSheet->submitted_by);
        $this->assertNull($scoreSheet->confirmed_by);
        $this->assertDatabaseHas('score_revisions', [
            'score_sheet_id' => $scoreSheet->id,
            'changed_by' => $judge->id,
            'revision' => 2,
            'reason' => 'اصلاح نمرهٔ ثبت‌شده توسط اپراتور',
        ]);
    }
}
