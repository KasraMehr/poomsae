<?php

namespace Tests\Feature;

use App\Actions\RunCompetition;
use App\Actions\SubmitScore;
use App\Models\AuditLog;
use App\Models\Bout;
use App\Models\Performance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\CreatesCompetition;
use Tests\TestCase;

class CompetitionWorkflowTest extends TestCase
{
    public function test_walkover_releases_court_and_advancement_reaches_final(): void
    {
        $f = $this->competition(5, 4);
        $this->schedule($f);
        $first = Bout::orderBy('id')->firstOrFail();
        $p = $first->performances()->firstOrFail();
        $this->startScoring($f, $p);
        foreach (Bout::orderBy('id')->get() as $bout) {
            $winner = $bout->entries()->firstOrFail()->id;
            $this->actingAs($f['admin'])->post('/tournaments/'.$f['tournament']->id.'/bouts/'.$bout->id.'/resolve', [
                'decision_type' => 'walkover', 'confirmed' => true,
                'winner_entry_id' => $winner, 'reason' => 'انصراف ثبت‌شدهٔ طرف مقابل',
            ])->assertSessionHasNoErrors()->assertRedirect();
        }
        $this->assertSame('cancelled', $p->fresh()->status);
        $this->assertDatabaseCount('results', 0);
        $this->schedule($f);
        $final = $f['category']->rounds()->orderByDesc('sequence')->firstOrFail();
        $this->assertSame(2, $final->sequence);
        $this->assertSame(1, $final->bouts()->count());
        $this->assertSame(2, $final->bouts()->firstOrFail()->entries()->count());
        $this->get('/tournaments/'.$f['tournament']->id)->assertOk();
        $this->get('/tournaments/'.$f['tournament']->id.'/scoreboard')->assertOk();
    }

    public function test_category_and_member_setup_are_available_through_authorized_http_forms(): void
    {
        $f = $this->competition();
        $this->actingAs($f['admin'])->post('/tournaments/'.$f['tournament']->id.'/members', [
            'name' => 'داور جدید', 'email' => 'new-judge@example.test', 'password' => 'Long-Pilot-Password-2026', 'role' => 'judge',
        ])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseHas('users', ['email' => 'new-judge@example.test', 'is_admin' => false]);
        $this->post('/tournaments/'.$f['tournament']->id.'/categories', [
            'name' => 'رده دوم', 'gender' => 'female', 'minimum_age' => 12, 'maximum_age' => 20, 'format' => 'round_robin',
            'judge_count' => 7, 'accuracy_max' => 300, 'discard_extremes' => true, 'rules_acknowledged' => true,
            'form_names' => ['فرم سوم', 'فرم چهارم'],
        ])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseHas('categories', ['name' => 'رده دوم', 'judge_count' => '7']);
    }

    use CreatesCompetition,RefreshDatabase;

    public function test_full_five_judge_competition_finishes_and_exports_results(): void
    {
        $f = $this->competition();
        $this->schedule($f);
        $bout = Bout::firstOrFail();
        $winner = $bout->entries()->firstOrFail()->id;
        foreach ($bout->performances()->orderBy('id')->get() as $performance) {
            $this->startScoring($f, $performance);
            $this->scoreAll($f, $performance, '2.50', $performance->entry_id === $winner ? '6.00' : '5.00');
            $this->actingAs($f['admin'])->post('/tournaments/'.$f['tournament']->id.'/performances/'.$performance->id.'/command', [
                'command' => 'approve', 'expected_version' => $performance->fresh()->version,
            ])->assertSessionHasNoErrors()->assertRedirect();
        }
        $this->assertSame($winner, $bout->fresh()->winner_entry_id);
        $this->assertSame('completed', $bout->fresh()->status);
        $this->post('/tournaments/'.$f['tournament']->id.'/complete')->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('completed', $f['tournament']->fresh()->status);
        $this->get('/tournaments/'.$f['tournament']->id)->assertOk();
        $this->get('/tournaments/'.$f['tournament']->id.'/results.csv')->assertOk()->assertDownload();
        $this->assertDatabaseCount('results', 4);
        $this->assertDatabaseCount('score_revisions', 20);
    }

    public function test_seven_judge_scores_are_required_and_retry_is_idempotent(): void
    {
        $f = $this->competition(7);
        $this->schedule($f);
        $p = Performance::firstOrFail();
        $this->startScoring($f, $p);
        $data = ['request_id' => (string) Str::uuid(), 'expected_version' => $p->fresh()->version, 'expected_revision' => 0, 'accuracy' => '2.50', 'presentation' => '6.00'];
        $submit = app(SubmitScore::class);
        $first = $submit->handle($f['judges'][0], $f['tournament'], $p, $data);
        $this->assertSame($first, $submit->handle($f['judges'][0], $f['tournament'], $p, $data));
        $this->assertDatabaseCount('score_revisions', 1);
        $this->actingAs($f['admin'])->post('/tournaments/'.$f['tournament']->id.'/performances/'.$p->id.'/command', ['command' => 'approve', 'expected_version' => $p->fresh()->version])->assertSessionHasErrors('operation');
        foreach ($f['judges']->skip(1) as $judge) {
            $submit->handle($judge, $f['tournament'], $p, [...$data, 'request_id' => (string) Str::uuid()]);
        }
        app(RunCompetition::class)->command($f['admin'], $f['tournament'], $p, ['command' => 'approve', 'expected_version' => $p->fresh()->version]);
        $this->assertSame('8.500000', $p->result()->firstOrFail()->score);
    }

    public function test_knockout_byes_do_not_create_phantom_performances(): void
    {
        $f = $this->competition(5, 5);
        $this->schedule($f);
        $this->assertDatabaseCount('bouts', 4);
        $this->assertSame(3, Bout::where('status', 'completed')->whereNotNull('winner_entry_id')->count());
        $this->assertDatabaseCount('performances', 4);
        $this->assertDatabaseCount('draws', 1);
    }

    public function test_round_robin_schedules_each_pair_exactly_once(): void
    {
        $f = $this->competition(5, 4, 'round_robin');
        $this->schedule($f);
        $this->assertDatabaseCount('bouts', 6);
        $this->assertDatabaseCount('performances', 24);
        $pairs = Bout::with('entries')->get()->map(fn ($b) => $b->entries->pluck('id')->sort()->implode(','))->all();
        $this->assertCount(6, array_unique($pairs));
    }

    public function test_equal_final_scores_require_a_recorded_operator_decision(): void
    {
        $f = $this->competition();
        $this->schedule($f);
        foreach (Performance::orderBy('id')->get() as $p) {
            $this->startScoring($f, $p);
            $this->scoreAll($f, $p);
            app(RunCompetition::class)->command($f['admin'], $f['tournament'], $p, ['command' => 'approve', 'expected_version' => $p->fresh()->version]);
        }
        $bout = Bout::firstOrFail();
        $this->assertNull($bout->winner_entry_id);
        $this->assertSame('running', $bout->status);
        $winner = $bout->entries()->firstOrFail()->id;
        $this->actingAs($f['admin'])->post('/tournaments/'.$f['tournament']->id.'/bouts/'.$bout->id.'/resolve', ['winner_entry_id' => $winner, 'reason' => 'تصمیم سرداور طبق آیین‌نامه رویداد'])->assertSessionHasNoErrors();
        $this->assertSame($winner, $bout->fresh()->winner_entry_id);
        $this->assertDatabaseHas('audit_logs', ['action' => 'bout.tie_resolved', 'subject_id' => $bout->id]);
    }

    public function test_restored_judge_mean_breaks_a_tie_without_changing_published_scores(): void
    {
        $fixture = $this->competition();
        $this->schedule($fixture);
        $bout = Bout::firstOrFail();
        $favoredEntryId = $bout->entries()->pluck('entries.id')[1];

        foreach ($bout->performances()->orderBy('id')->get() as $performance) {
            $this->startScoring($fixture, $performance);
            $presentations = $performance->entry_id === $favoredEntryId
                ? ['5.00', '5.00', '5.00', '5.00', '6.00']
                : ['4.00', '5.00', '5.00', '5.00', '6.00'];

            foreach ($fixture['judges'] as $seat => $judge) {
                app(SubmitScore::class)->handle($judge, $fixture['tournament'], $performance, [
                    'request_id' => (string) Str::uuid(),
                    'expected_version' => $performance->fresh()->version,
                    'expected_revision' => 0,
                    'accuracy' => '2.50',
                    'presentation' => $presentations[$seat],
                ]);
            }

            app(RunCompetition::class)->command($fixture['admin'], $fixture['tournament'], $performance, [
                'command' => 'approve', 'expected_version' => $performance->fresh()->version,
            ]);
        }

        $this->assertSame($favoredEntryId, $bout->fresh()->winner_entry_id);
        $this->assertSame('completed', $bout->fresh()->status);
        $this->assertDatabaseCount('results', 4);
        $this->assertSame(['7.500000'], $bout->performances()->with('result')->get()->pluck('result.score')->unique()->values()->all());
        $this->assertDatabaseHas('audit_logs', ['action' => 'bout.tie_break_resolved', 'subject_id' => $bout->id]);
        $comparison = AuditLog::where('action', 'bout.tie_break_resolved')->where('subject_id', $bout->id)->firstOrFail()->after['restored_mean_totals_micros'];
        $this->assertSame(7700000, $comparison[$favoredEntryId]);
        $this->assertSame(7500000, $comparison[$bout->entries()->where('entries.id', '!=', $favoredEntryId)->firstOrFail()->id]);
    }

    public function test_correction_requires_revision_and_is_audited(): void
    {
        $f = $this->competition();
        $this->schedule($f);
        $p = Performance::firstOrFail();
        $this->startScoring($f, $p);
        $data = ['request_id' => (string) Str::uuid(), 'expected_version' => $p->fresh()->version, 'expected_revision' => 0, 'accuracy' => '2.50', 'presentation' => '6.00'];
        $submit = app(SubmitScore::class);
        $submit->handle($f['judges'][0], $f['tournament'], $p, $data);
        $this->actingAs($f['judges'][0])->post('/tournaments/'.$f['tournament']->id.'/performances/'.$p->id.'/scores', [...$data, 'request_id' => (string) Str::uuid(), 'expected_revision' => 1, 'presentation' => '5.50', 'reason' => 'اصلاح اشتباه ورود نمره'])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('score_revisions', 2);
        $this->assertDatabaseHas('score_components', ['criterion' => 'presentation', 'value_hundredths' => 550]);
    }
}
