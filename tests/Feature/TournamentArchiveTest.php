<?php

namespace Tests\Feature;

use App\Actions\RunCompetition;
use App\Events\CompetitionDomainUpdated;
use App\Events\CompetitionUpdated;
use App\Models\AuditLog;
use App\Models\Performance;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\CreatesCompetition;
use Tests\TestCase;

class TournamentArchiveTest extends TestCase
{
    use CreatesCompetition, RefreshDatabase;

    public function test_archive_and_restore_preserve_results_and_publish_audited_status_changes(): void
    {
        $fixture = $this->finishedCompetition();
        $results = DB::table('results')->orderBy('id')->get()->toArray();
        $scores = DB::table('score_sheets')->orderBy('id')->get()->toArray();
        $performances = Performance::orderBy('id')->pluck('id')->all();
        Event::fake([CompetitionUpdated::class, CompetitionDomainUpdated::class]);

        $this->actingAs($fixture['admin'])->post(route('tournaments.archive', $fixture['tournament']), [
            'expected_status' => 'completed', 'reason' => 'Final results checked',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('tournaments', ['id' => $fixture['tournament']->id, 'status' => 'archived']);
        $archive = AuditLog::where('action', 'tournament.archived')->firstOrFail();
        $this->assertSame(['status' => 'completed'], $archive->before);
        $this->assertSame('Final results checked', $archive->after['reason']);
        Event::assertDispatched(CompetitionUpdated::class, fn ($event) => $event->tournamentId === $fixture['tournament']->id);

        $this->get(route('reports.judges', $fixture['tournament']))->assertOk();
        $this->get(route('scoreboard.excel', $fixture['tournament']))->assertOk();
        $this->post(route('tournaments.restore', $fixture['tournament']), [
            'expected_status' => 'archived', 'reason' => 'Review final records',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('tournaments', ['id' => $fixture['tournament']->id, 'status' => 'completed']);
        $this->assertDatabaseHas('audit_logs', ['tournament_id' => $fixture['tournament']->id, 'action' => 'tournament.restored']);
        $this->assertEquals($results, DB::table('results')->orderBy('id')->get()->toArray());
        $this->assertEquals($scores, DB::table('score_sheets')->orderBy('id')->get()->toArray());
        $this->assertSame($performances, Performance::orderBy('id')->pluck('id')->all());
    }

    public function test_archived_tournament_rejects_score_correction_and_restoring_reopens_existing_correction_window(): void
    {
        $fixture = $this->finishedCompetition();
        $performance = $fixture['performance']->fresh();
        $payload = [
            'request_id' => (string) Str::uuid(), 'expected_version' => $performance->version, 'expected_revision' => 1,
            ...$this->detailedScoreInput('3.50', '6.00'), 'reason' => 'Recheck final score',
        ];
        $this->actingAs($fixture['admin'])->post(route('tournaments.archive', $fixture['tournament']), [
            'expected_status' => 'completed', 'reason' => 'Preserve final results',
        ])->assertSessionHasNoErrors();
        $this->actingAs($fixture['judges'][0])->post(route('judging.store', [$fixture['tournament'], $performance]), $payload)
            ->assertRedirect()->assertSessionHasErrors('operation');
        $this->assertSame(1, $performance->scoreSheets()->where('judge_assignment_id', $performance->bout->judges()->where('user_id', $fixture['judges'][0]->id)->firstOrFail()->id)->firstOrFail()->revision);
        $this->actingAs($fixture['admin'])->post(route('tournaments.restore', $fixture['tournament']), [
            'expected_status' => 'archived', 'reason' => 'Allow final score review',
        ])->assertSessionHasNoErrors();
        $this->actingAs($fixture['judges'][0])->post(route('judging.store', [$fixture['tournament'], $performance]), $payload)
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('score_sheets', ['performance_id' => $performance->id, 'revision' => 2]);
    }

    #[TestWith(['operator'])]
    #[TestWith(['judge'])]
    #[TestWith(['display'])]
    public function test_non_managers_cannot_archive_or_restore(string $role): void
    {
        $fixture = $this->finishedCompetition();
        $member = User::factory()->create();
        $fixture['tournament']->users()->attach($member, ['role' => $role]);
        $this->actingAs($member)->post(route('tournaments.archive', $fixture['tournament']), ['expected_status' => 'completed', 'reason' => 'Attempt archive'])->assertForbidden();
        $fixture['tournament']->update(['status' => 'archived']);
        $this->post(route('tournaments.restore', $fixture['tournament']), ['expected_status' => 'archived', 'reason' => 'Attempt restore'])->assertForbidden();
        $this->assertSame('archived', $fixture['tournament']->fresh()->status);
    }

    public function test_archive_requests_require_login_reason_and_fresh_status(): void
    {
        $fixture = $this->finishedCompetition();
        $url = route('tournaments.archive', $fixture['tournament']);
        $this->post($url, [])->assertRedirect(route('login'));
        $this->actingAs($fixture['admin'])->postJson($url, [])->assertUnprocessable()->assertJsonValidationErrors(['reason', 'expected_status']);
        $this->postJson($url, ['reason' => 'tiny', 'expected_status' => 'completed'])->assertUnprocessable()->assertJsonValidationErrors('reason');
        $fixture['tournament']->update(['status' => 'running']);
        $this->postJson($url, ['reason' => 'Finished competition', 'expected_status' => 'completed'])->assertConflict();
        $this->assertSame('running', $fixture['tournament']->fresh()->status);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'tournament.archived']);
    }

    public function test_archive_refuses_open_performances_even_if_tournament_is_marked_completed(): void
    {
        $fixture = $this->competition();
        $this->schedule($fixture);
        $performance = $fixture['category']->rounds()->firstOrFail()->bouts()->firstOrFail()->performances()->orderBy('id')->firstOrFail();
        $this->startScoring($fixture, $performance);
        $fixture['tournament']->update(['status' => 'completed']);
        $this->actingAs($fixture['admin'])->postJson(route('tournaments.archive', $fixture['tournament']), [
            'reason' => 'Finished competition', 'expected_status' => 'completed',
        ])->assertUnprocessable()->assertJsonValidationErrors('operation');
        $this->assertSame('completed', $fixture['tournament']->fresh()->status);
    }

    public function test_archive_listing_is_filtered_and_respects_tournament_membership(): void
    {
        $manager = User::factory()->create();
        $visible = Tournament::factory()->create(['status' => 'archived']);
        $visible->users()->attach($manager, ['role' => 'manager']);
        Tournament::factory()->create(['status' => 'archived']);
        $active = Tournament::factory()->create();
        $active->users()->attach($manager, ['role' => 'manager']);
        $this->actingAs($manager)->get(route('tournaments.index', ['status' => 'archived']))
            ->assertInertia(fn (Assert $page) => $page->where('statusFilter', 'archived')->has('tournaments.data', 1)->where('tournaments.data.0.id', $visible->id));
        $this->get(route('tournaments.index'))->assertInertia(fn (Assert $page) => $page->has('tournaments.data', 1)->where('tournaments.data.0.id', $active->id));
        $this->get(route('tournaments.index', ['status' => 'all']))->assertInertia(fn (Assert $page) => $page->has('tournaments.data', 2));
        $this->getJson(route('tournaments.index', ['status' => 'unknown']))->assertUnprocessable()->assertJsonValidationErrors('status');
    }

    public function test_archive_refuses_unfinished_categories_without_open_performances(): void
    {
        $fixture = $this->competition();
        $this->schedule($fixture);
        $fixture['tournament']->update(['status' => 'completed']);
        $this->actingAs($fixture['admin'])->postJson(route('tournaments.archive', $fixture['tournament']), [
            'expected_status' => 'completed', 'reason' => 'Premature archive request',
        ])->assertUnprocessable()->assertJsonValidationErrors('operation');
        $this->assertSame('completed', $fixture['tournament']->fresh()->status);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'tournament.archived']);
    }

    public function test_assigned_manager_can_archive_and_operator_sees_reports_without_archive_controls(): void
    {
        $fixture = $this->finishedCompetition();
        $manager = User::factory()->create();
        $operator = User::factory()->create();
        $fixture['tournament']->users()->attach($manager, ['role' => 'manager']);
        $fixture['tournament']->users()->attach($operator, ['role' => 'operator']);
        $this->actingAs($manager)->post(route('tournaments.archive', $fixture['tournament']), ['expected_status' => 'completed', 'reason' => 'Manager archives finals'])->assertSessionHasNoErrors();
        $this->actingAs($operator)->get(route('reports.audit', $fixture['tournament']))->assertInertia(fn (Assert $page) => $page->where('canArchive', false)->where('tournament.status', 'archived'));
        $this->get(route('tournaments.show', $fixture['tournament']))->assertInertia(fn (Assert $page) => $page->where('can.operate', false));
    }

    private function finishedCompetition(): array
    {
        $fixture = $this->competition();
        $this->schedule($fixture);
        $bout = $fixture['category']->rounds()->firstOrFail()->bouts()->firstOrFail();
        $performances = $bout->performances()->orderBy('form_number')->orderBy('id')->get();
        $winnerId = $performances->first()->entry_id;
        foreach ($performances as $performance) {
            $this->startScoring($fixture, $performance);
            $this->scoreAll($fixture, $performance, $performance->entry_id === $winnerId ? '3.90' : '3.80');
            app(RunCompetition::class)->command($fixture['admin'], $fixture['tournament'], $performance, ['command' => 'approve', 'expected_version' => $performance->fresh()->version]);
        }
        app(RunCompetition::class)->complete($fixture['admin'], $fixture['tournament']);

        return [...$fixture, 'performance' => $performances->first()];
    }
}
