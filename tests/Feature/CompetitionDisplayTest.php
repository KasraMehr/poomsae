<?php

namespace Tests\Feature;

use App\Actions\CompetitionDisplay;
use App\Actions\RunCompetition;
use App\Actions\SubmitScore;
use App\Models\Performance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\CreatesCompetition;
use Tests\TestCase;

class CompetitionDisplayTest extends TestCase
{
    use CreatesCompetition, RefreshDatabase;

    public function test_display_pages_and_data_are_read_only_and_members_only(): void
    {
        $fixture = $this->competition();
        $this->schedule($fixture);
        $viewer = User::factory()->create();
        $fixture['tournament']->users()->attach($viewer, ['role' => 'display']);
        $this->actingAs($viewer)->get(route('scoreboard.rtds', $fixture['tournament']))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Competition/Rtds')->has('display.courts', 1)->has('displayUrls.data'));
        $this->get(route('scoreboard.show', $fixture['tournament']))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Competition/Scoreboard')->has('display.tournament.categories', 1));
        $response = $this->getJson(route('scoreboard.data', $fixture['tournament']))->assertOk()->assertJsonPath('data.tournament.members', []);
        $performance = $response->json('data.tournament.categories.0.rounds.0.bouts.0.performances.0');
        $this->assertSame([], $performance['scores']);
        $this->assertNull($performance['own_score']);
        $this->assertNull($performance['music_url']);
        $this->post(route('operations.command', [$fixture['tournament'], $performance['id']]), ['command' => 'start', 'expected_version' => $performance['version']])->assertForbidden();
        $this->actingAs(User::factory()->create())->getJson(route('scoreboard.data', $fixture['tournament']))->assertForbidden();
        $this->get(route('scoreboard.rtds', $fixture['tournament']))->assertForbidden();
    }

    public function test_revision_polling_observes_a_new_judge_score_without_publishing_it(): void
    {
        $fixture = $this->competition();
        $this->schedule($fixture);
        $performance = Performance::query()->orderBy('id')->firstOrFail();
        $this->startScoring($fixture, $performance);
        $url = route('scoreboard.data', $fixture['tournament']);
        $initial = $this->actingAs($fixture['admin'])->getJson($url)->assertOk();
        $revision = $initial->json('data.revision');
        $this->getJson($url.'?revision='.$revision)->assertJsonPath('changed', false)->assertJsonMissingPath('data');
        app(SubmitScore::class)->handle($fixture['judges'][0], $fixture['tournament'], $performance, [
            'request_id' => (string) Str::uuid(), 'expected_version' => $performance->fresh()->version, 'expected_revision' => 0,
            ...$this->detailedScoreInput('3.50', '5.50'),
        ]);
        $updated = $this->getJson($url.'?revision='.$revision)->assertJsonPath('changed', true)
            ->assertJsonPath('data.courts.0.current.performances.0.submitted_count', 1)
            ->assertJsonPath('data.courts.0.current.performances.0.preview_result', null)
            ->assertJsonPath('data.courts.0.current.performances.0.result', null);
        $this->assertGreaterThan($revision, $updated->json('data.revision'));
        $this->assertDatabaseCount('results', 0);
    }

    public function test_full_panel_has_a_labelled_preview_then_an_approved_result(): void
    {
        $fixture = $this->competition();
        $this->schedule($fixture);
        $performance = Performance::query()->orderBy('id')->firstOrFail();
        $this->startScoring($fixture, $performance);
        $this->scoreAll($fixture, $performance, '3.50', '5.50');
        $display = app(CompetitionDisplay::class);
        $snapshot = $display->snapshot($fixture['tournament'], $fixture['admin']);
        $preview = $snapshot['courts'][0]['current']['performances'][0];
        $this->assertSame('9.000000', $preview['preview_result']);
        $this->assertNull($preview['result']);
        $this->assertSame([], $preview['scores']);
        $this->assertDatabaseCount('results', 0);
        app(RunCompetition::class)->command($fixture['admin'], $fixture['tournament'], $performance, ['command' => 'approve', 'expected_version' => $performance->fresh()->version]);
        $approved = $display->snapshot($fixture['tournament'], $fixture['admin'])['tournament']['categories'][0]['rounds'][0]['bouts'][0]['performances'][0];
        $this->assertSame('9.000000', $approved['result']);
        $this->assertNull($approved['preview_result']);
    }

    public function test_rtds_keeps_the_form_order_for_table_consecutive_and_phased_execution(): void
    {
        $fixture = $this->competition(5, 3, 'round_robin');
        $this->schedule($fixture);
        $display = app(CompetitionDisplay::class);
        $consecutive = $display->snapshot($fixture['tournament'], $fixture['admin'])['courts'][0]['upcoming'];
        $this->assertSame([1, 2, 1, 2, 1], array_column($consecutive, 'form_number'));
        $fixture['category']->update(['performance_order' => 'phased']);
        $phased = $display->snapshot($fixture['tournament'], $fixture['admin'])['courts'][0]['upcoming'];
        $this->assertSame([1, 1, 1, 2, 2], array_column($phased, 'form_number'));
        $this->assertNotSame($phased[0]['entries'][0]['id'], $phased[1]['entries'][0]['id']);
    }

    public function test_rtds_groups_simultaneous_opponents_and_shows_the_active_court(): void
    {
        $fixture = $this->competition();
        $fixture['category']->update(['execution_mode' => 'simultaneous']);
        $this->schedule($fixture);
        $display = app(CompetitionDisplay::class);
        $queue = $display->snapshot($fixture['tournament'], $fixture['admin'])['courts'][0]['upcoming'];
        $this->assertCount(2, $queue);
        $this->assertCount(2, $queue[0]['entries']);
        $this->assertSame(['chung', 'hong'], array_column($queue[0]['entries'], 'side'));
        $performance = Performance::findOrFail($queue[0]['id']);
        app(RunCompetition::class)->command($fixture['admin'], $fixture['tournament'], $performance, ['command' => 'start', 'expected_version' => $performance->version]);
        $court = $display->snapshot($fixture['tournament'], $fixture['admin'])['courts'][0];
        $this->assertSame('running', $court['current']['status']);
        $this->assertCount(2, $court['current']['performances']);
        $this->assertCount(1, $court['upcoming']);
        $this->assertFalse($court['upcoming'][0]['ready']);
    }

    public function test_display_keeps_stage_draw_waiting_and_does_not_queue_byes(): void
    {
        $fixture = $this->competition(5, 3, 'knockout', 1, 2, true, 'before_stage');
        $this->schedule($fixture);
        $snapshot = app(CompetitionDisplay::class)->snapshot($fixture['tournament'], $fixture['admin']);
        $this->assertFalse($snapshot['tournament']['categories'][0]['rounds'][0]['forms_ready']);
        $this->assertCount(2, $snapshot['tournament']['categories'][0]['rounds']);
        $this->assertCount(4, $snapshot['courts'][0]['upcoming']);
        $this->assertFalse($snapshot['courts'][0]['upcoming'][0]['ready']);
        $this->assertSame('در انتظار قرعهٔ پومسه', $snapshot['courts'][0]['upcoming'][0]['form_name']);
    }

    public function test_rtds_waits_for_a_tie_decision_and_retains_all_published_form_scores(): void
    {
        $fixture = $this->competition();
        $this->schedule($fixture);
        foreach (Performance::query()->orderBy('form_number')->orderBy('id')->get() as $performance) {
            $this->startScoring($fixture, $performance);
            $this->scoreAll($fixture, $performance, '3.50', '5.50');
            app(RunCompetition::class)->command($fixture['admin'], $fixture['tournament'], $performance, ['command' => 'approve', 'expected_version' => $performance->fresh()->version]);
        }
        $snapshot = app(CompetitionDisplay::class)->snapshot($fixture['tournament'], $fixture['admin']);
        $this->assertSame('decision', $snapshot['courts'][0]['current']['status']);
        $bout = $snapshot['tournament']['categories'][0]['rounds'][0]['bouts'][0];
        $this->assertNull($bout['winner_entry_id']);
        $this->assertSame(['18.000000', '18.000000'], array_values($bout['totals']));
        $this->assertTrue(collect($bout['performances'])->every(fn (array $performance) => $performance['result'] === '9.000000'));
    }
}
