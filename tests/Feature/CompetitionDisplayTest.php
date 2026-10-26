<?php

namespace Tests\Feature;

use App\Actions\CompetitionConsole;
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

    public function test_published_final_supplies_current_winner_and_knockout_placements(): void
    {
        $fixture = $this->competition();
        $this->schedule($fixture);
        $winnerId = $fixture['category']->entries()->orderBy('id')->firstOrFail()->id;
        $runner = app(RunCompetition::class);

        for ($index = 0; $index < 4; $index++) {
            $next = app(CompetitionConsole::class)->snapshot($fixture['tournament'], $fixture['admin'])['courts'][0]['queue'][0];
            $performance = Performance::findOrFail($next['id']);
            $this->startScoring($fixture, $performance);
            $this->scoreAll($fixture, $performance, $performance->entry_id === $winnerId ? '3.00' : '2.50', '5.50');
            $runner->command($fixture['admin'], $fixture['tournament'], $performance, ['command' => 'approve', 'expected_version' => $performance->fresh()->version]);
        }

        $snapshot = app(CompetitionDisplay::class)->snapshot($fixture['tournament'], $fixture['admin']);
        $this->assertSame($winnerId, $snapshot['tournament']['current_result']['winner_entry_id']);
        $this->assertSame([$snapshot['courts'][0]['previous_bout_id']], $snapshot['tournament']['current_result']['bout_ids']);
        $this->assertSame([1, 2], collect($snapshot['tournament']['categories'][0]['placements'])->pluck('rank')->all());
        $this->assertSame($winnerId, $snapshot['tournament']['categories'][0]['placements'][0]['entry_id']);
        $finalBout = $snapshot['tournament']['categories'][0]['rounds'][0]['bouts'][0];
        $this->assertSame('6.000000', $finalBout['component_totals'][$winnerId]['accuracy']);
        $this->assertSame('11.000000', $finalBout['component_totals'][$winnerId]['presentation']);
        $this->assertSame('20.000000', $finalBout['component_totals'][$winnerId]['total_max']);
    }

    public function test_modular_scoreboard_pages_use_the_live_display_snapshot(): void
    {
        $fixture = $this->competition();
        $this->schedule($fixture);
        $courtId = $fixture['tournament']->courts()->firstOrFail()->id;
        $displayUser = User::factory()->create();
        $fixture['tournament']->users()->attach($displayUser, ['role' => 'display']);

        foreach (['standby' => 'Standby', 'live' => 'LiveBoard', 'scoring' => 'Scoring', 'result' => 'Result', 'ranking' => 'Ranking', 'top-four' => 'TopFour', 'draw' => 'Draw', 'winner' => 'Winner'] as $view => $component) {
            $this->actingAs($displayUser)->get(route('scoreboard.page', [$fixture['tournament'], $view, 'court' => $courtId]))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page->component('Scoreboard/'.$component)->where('courtId', $courtId)->has('display.tournament.categories', 1));
        }

        $this->get(route('scoreboard.page', [$fixture['tournament'], 'draw', 'court' => 999]))->assertNotFound();
        $this->get(route('scoreboard.page', [$fixture['tournament'], 'unknown']))->assertNotFound();
        $this->actingAs(User::factory()->create())->get(route('scoreboard.page', [$fixture['tournament'], 'scoring']))->assertForbidden();
    }

    public function test_display_pages_and_data_are_read_only_and_members_only(): void
    {
        $fixture = $this->competition();
        $this->schedule($fixture);
        $viewer = User::factory()->create();
        $fixture['tournament']->users()->attach($viewer, ['role' => 'display']);
        $operator = User::factory()->create();
        $fixture['tournament']->users()->attach($operator, ['role' => 'operator']);
        $this->actingAs($operator)->get(route('scoreboard.show', $fixture['tournament']))->assertForbidden();
        $this->actingAs($viewer)->get(route('scoreboard.rtds', $fixture['tournament']))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Competition/Rtds')->has('display.courts', 1)
                ->where('courtId', $fixture['tournament']->courts()->firstOrFail()->id)->has('displayUrls.data'));
        $this->get(route('scoreboard.rtds', [$fixture['tournament'], 'court' => 999]))->assertNotFound();
        $this->get(route('scoreboard.show', $fixture['tournament']))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Competition/Scoreboard')->has('display.tournament.categories', 1));
        $response = $this->getJson(route('scoreboard.data', $fixture['tournament']))->assertOk()->assertJsonPath('data.tournament.members', []);
        $performance = $response->json('data.tournament.categories.0.rounds.0.bouts.0.performances.0');
        $this->assertSame([], $performance['scores']);
        $this->assertNull($performance['own_score']);
        $this->assertNull($performance['music_url']);
        $this->actingAs($viewer)->post(route('operations.command', [$fixture['tournament'], $performance['id']]), ['command' => 'start', 'expected_version' => $performance['version']])->assertForbidden();
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
        $displayUser = User::factory()->create();
        $fixture['tournament']->users()->attach($displayUser, ['role' => 'display']);
        $initial = $this->actingAs($displayUser)->getJson($url)->assertOk();
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

    public function test_rtds_phased_queue_keeps_other_first_forms_before_the_started_athletes_second_form(): void
    {
        $fixture = $this->competition(5, 3, 'round_robin');
        $fixture['category']->update(['performance_order' => 'phased']);
        $this->schedule($fixture);
        $bouts = $fixture['category']->rounds()->firstOrFail()->bouts()->with('performances')->orderBy('sequence')->get();
        $firstForm = $bouts[0]->performances->firstWhere('form_number', 1);
        $this->startScoring($fixture, $firstForm);
        $this->scoreAll($fixture, $firstForm);
        app(RunCompetition::class)->command($fixture['admin'], $fixture['tournament'], $firstForm, [
            'command' => 'approve', 'expected_version' => $firstForm->fresh()->version,
        ]);

        $court = app(CompetitionDisplay::class)->snapshot($fixture['tournament'], $fixture['admin'])['courts'][0];
        $this->assertNull($court['current']);
        $this->assertSame([
            $bouts[1]->performances->firstWhere('form_number', 1)->id,
            $bouts[2]->performances->firstWhere('form_number', 1)->id,
            $bouts[0]->performances->firstWhere('form_number', 2)->id,
            $bouts[1]->performances->firstWhere('form_number', 2)->id,
            $bouts[2]->performances->firstWhere('form_number', 2)->id,
        ], array_column($court['upcoming'], 'id'));
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
