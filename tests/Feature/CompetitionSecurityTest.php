<?php

namespace Tests\Feature;

use App\Actions\CompetitionView;
use App\Actions\RunCompetition;
use App\Models\Bout;
use App\Models\Performance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\CreatesCompetition;
use Tests\TestCase;

class CompetitionSecurityTest extends TestCase
{
    public function test_walkover_requires_explicit_confirmation_and_cannot_rewrite_completed_bout(): void
    {
        $f = $this->competition();
        $this->schedule($f);
        $bout = Bout::firstOrFail();
        $url = '/tournaments/'.$f['tournament']->id.'/bouts/'.$bout->id.'/resolve';
        $data = ['decision_type' => 'walkover', 'winner_entry_id' => $bout->entries()->firstOrFail()->id, 'reason' => 'انصراف ورزشکار مقابل'];
        $this->actingAs($f['admin'])->post($url, $data)->assertSessionHasErrors('confirmed');
        $this->post($url, [...$data, 'confirmed' => true])->assertSessionHasNoErrors();
        $this->post($url, [...$data, 'confirmed' => true])->assertSessionHasErrors('operation');
    }

    public function test_scoring_secrets_do_not_reach_display_payload(): void
    {
        $f = $this->competition();
        $this->schedule($f);
        $p = Performance::firstOrFail();
        $this->startScoring($f, $p);
        $this->scoreAll($f, $p);
        $snapshot = app(CompetitionView::class)->snapshot($f['tournament'], $f['admin'], true);
        $performance = $snapshot['categories'][0]['rounds'][0]['bouts'][0]['performances'][0];
        $this->assertSame([], $snapshot['members']);
        $this->assertSame([], $performance['scores']);
        $this->assertNull($performance['own_score']);
        $this->assertNull($performance['result']);
    }

    public function test_web_conflict_keeps_inertia_form_with_readable_error(): void
    {
        $f = $this->competition();
        $this->schedule($f);
        $p = Performance::firstOrFail();
        $this->startScoring($f, $p);
        $this->actingAs($f['admin'])->withHeader('X-Inertia', 'true')->post(
            '/tournaments/'.$f['tournament']->id.'/performances/'.$p->id.'/command',
            ['command' => 'finish', 'expected_version' => 1]
        )->assertRedirect()->assertSessionHasErrors('operation');
    }

    use CreatesCompetition,RefreshDatabase;

    public function test_foreign_event_objects_are_not_mutable(): void
    {
        $f = $this->competition();
        $other = $this->competition();
        $this->schedule($other);
        $p = Performance::firstOrFail();
        $this->actingAs($f['admin'])->post('/tournaments/'.$f['tournament']->id.'/performances/'.$p->id.'/command', ['command' => 'start', 'expected_version' => $p->version])->assertNotFound();
        $this->post('/tournaments/'.$f['tournament']->id.'/categories/'.$other['category']->id.'/entries', ['first_name' => 'آزمون', 'last_name' => 'آزمون', 'birth_date' => '2005-01-01', 'gender' => 'male'])->assertNotFound();
    }

    public function test_judge_cannot_control_execution_or_see_other_scores(): void
    {
        $f = $this->competition();
        $this->schedule($f);
        $p = Performance::firstOrFail();
        $this->startScoring($f, $p);
        $this->scoreAll($f, $p);
        $this->actingAs($f['judges'][0])->post('/tournaments/'.$f['tournament']->id.'/performances/'.$p->id.'/command', ['command' => 'approve', 'expected_version' => $p->fresh()->version])->assertForbidden();
        $snapshot = app(CompetitionView::class)->snapshot($f['tournament'], $f['judges'][0]);
        $performance = $snapshot['categories'][0]['rounds'][0]['bouts'][0]['performances'][0];
        $this->assertSame([], $performance['scores']);
        $this->assertNotNull($performance['own_score']);
        $this->assertNull($performance['result']);
    }

    public function test_non_assigned_judge_cannot_submit_and_bounds_are_enforced(): void
    {
        $f = $this->competition();
        $this->schedule($f);
        $p = Performance::firstOrFail();
        $this->startScoring($f, $p);
        $outsider = User::factory()->create();
        $f['tournament']->users()->attach($outsider, ['role' => 'judge']);
        $data = ['request_id' => (string) Str::uuid(), 'expected_version' => $p->fresh()->version, 'expected_revision' => 0, 'accuracy' => '3.01', 'presentation' => '6.00'];
        $url = '/tournaments/'.$f['tournament']->id.'/performances/'.$p->id.'/scores';
        $this->actingAs($outsider)->post($url, $data)->assertForbidden();
        $this->actingAs($f['judges'][0])->post($url, $data)->assertSessionHasErrors('operation');
        $this->assertDatabaseCount('score_sheets', 0);
    }

    public function test_stale_command_and_concurrent_court_are_rejected(): void
    {
        $f = $this->competition();
        $this->schedule($f);
        $ps = Performance::orderBy('id')->get();
        $run = app(RunCompetition::class);
        $run->command($f['admin'], $f['tournament'], $ps[0], ['command' => 'start', 'expected_version' => 1]);
        $this->actingAs($f['admin'])->post('/tournaments/'.$f['tournament']->id.'/performances/'.$ps[0]->id.'/command', ['command' => 'finish', 'expected_version' => 1])->assertStatus(409);
        $this->post('/tournaments/'.$f['tournament']->id.'/performances/'.$ps[2]->id.'/command', ['command' => 'start', 'expected_version' => 1])->assertSessionHasErrors('operation');
    }

    public function test_unknown_execution_command_cannot_approve_a_score(): void
    {
        $fixture = $this->competition();
        $this->schedule($fixture);
        $performance = Performance::firstOrFail();
        $this->startScoring($fixture, $performance);
        $this->scoreAll($fixture, $performance);

        $this->expectException(ValidationException::class);

        app(RunCompetition::class)->command($fixture['admin'], $fixture['tournament'], $performance, [
            'command' => 'pause', 'expected_version' => $performance->fresh()->version,
        ]);
    }

    public function test_unknown_bout_decision_cannot_resolve_a_tie(): void
    {
        $fixture = $this->competition();
        $this->schedule($fixture);
        $run = app(RunCompetition::class);

        foreach (Performance::orderBy('form_number')->orderBy('id')->get() as $performance) {
            $this->startScoring($fixture, $performance);
            $this->scoreAll($fixture, $performance);
            $run->command($fixture['admin'], $fixture['tournament'], $performance, [
                'command' => 'approve', 'expected_version' => $performance->fresh()->version,
            ]);
        }

        $bout = Bout::firstOrFail();
        $this->expectException(ValidationException::class);

        $run->resolve($fixture['admin'], $fixture['tournament'], $bout, [
            'decision_type' => 'forfeit',
            'winner_entry_id' => $bout->entries()->firstOrFail()->id,
            'reason' => 'تصمیم ثبت‌شدهٔ سرداور',
        ]);
    }

    public function test_published_score_is_immutable_and_idempotency_conflict_is_rejected(): void
    {
        $f = $this->competition();
        $this->schedule($f);
        $p = Performance::firstOrFail();
        $this->startScoring($f, $p);
        $this->scoreAll($f, $p);
        $record = DB::table('idempotency_keys')->where('user_id', $f['judges'][0]->id)->first();
        $url = '/tournaments/'.$f['tournament']->id.'/performances/'.$p->id.'/scores';
        $data = ['request_id' => $record->request_id, 'expected_version' => $p->fresh()->version, 'expected_revision' => 1, 'accuracy' => '2.00', 'presentation' => '6.00', 'reason' => 'تصحیح نمره'];
        $this->actingAs($f['judges'][0])->post($url, $data)->assertStatus(409);
        app(RunCompetition::class)->command($f['admin'], $f['tournament'], $p, ['command' => 'approve', 'expected_version' => $p->fresh()->version]);
        $this->post($url, [...$data, 'request_id' => (string) Str::uuid(), 'expected_version' => $p->fresh()->version])->assertSessionHasErrors('operation');
    }

    public function test_entry_age_and_setup_lock_are_enforced(): void
    {
        $f = $this->competition();
        $this->actingAs($f['admin'])->post('/tournaments/'.$f['tournament']->id.'/categories/'.$f['category']->id.'/entries', ['first_name' => 'آزمون', 'last_name' => 'سن', 'birth_date' => '2020-01-01', 'gender' => 'male'])->assertSessionHasErrors('operation');
        $this->schedule($f);
        $entry = $f['category']->entries()->firstOrFail();
        $this->patch('/tournaments/'.$f['tournament']->id.'/entries/'.$entry->id.'/status', ['status' => 'withdrawn'])->assertSessionHasErrors('operation');
    }
}
