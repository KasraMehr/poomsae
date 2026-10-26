<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesCompetition;
use Tests\TestCase;

class PanelRoleIsolationTest extends TestCase
{
    use CreatesCompetition, RefreshDatabase;

    public function test_each_account_enters_only_its_own_panel(): void
    {
        $fixture = $this->competition();
        $tournament = $fixture['tournament'];
        $display = User::factory()->create();
        $tournament->users()->attach($display, ['role' => 'display']);

        foreach ([
            [$fixture['admin'], 'tournaments.setup'],
            [$fixture['operator'], 'tournaments.show'],
            [$fixture['judges'][0], 'judging.index'],
            [$display, 'scoreboard.rtds'],
        ] as [$account, $home]) {
            $this->actingAs($account)->get(route('dashboard'))->assertRedirect(route($home, $tournament));
            $show = $this->get(route('tournaments.show', $tournament));
            $home === 'tournaments.show' ? $show->assertOk() : $show->assertRedirect(route($home, $tournament));
            foreach (['tournaments.setup', 'judging.index', 'scoreboard.rtds', 'competition.system'] as $panel) {
                if ($panel === $home || ($home === 'tournaments.setup' && $panel === 'competition.system')) {
                    $this->get(route($panel, $tournament))->assertOk();
                } else {
                    $this->get(route($panel, $tournament))->assertForbidden();
                }
            }
        }
    }

    public function test_an_account_cannot_take_another_roles_action(): void
    {
        $fixture = $this->competition();
        $this->schedule($fixture);
        $tournament = $fixture['tournament'];
        $performance = $fixture['category']->rounds()->firstOrFail()->bouts()->firstOrFail()->performances()->firstOrFail();
        $display = User::factory()->create();
        $tournament->users()->attach($display, ['role' => 'display']);

        foreach ([$fixture['admin'], $fixture['judges'][0], $display] as $account) {
            $this->actingAs($account)->post(route('operations.command', [$tournament, $performance]), [
                'command' => 'start', 'expected_version' => $performance->version,
            ])->assertForbidden();
        }
        foreach ([$fixture['admin'], $fixture['operator'], $display] as $account) {
            $this->actingAs($account)->post(route('judging.store', [$tournament, $performance]), [])->assertForbidden();
        }
        foreach ([$fixture['operator'], $fixture['judges'][0], $display] as $account) {
            $this->actingAs($account)->post(route('operations.court', $tournament), ['name' => 'Other court'])->assertForbidden();
        }
        $this->actingAs($fixture['operator'])->get(route('scoreboard.data', $tournament))->assertForbidden();
        $this->actingAs($display)->get(route('scoreboard.data', $tournament))->assertOk();
    }

    public function test_legacy_mixed_role_membership_cannot_open_either_panel(): void
    {
        $first = $this->competition();
        $second = $this->competition();
        $account = $first['judges'][0];
        $second['tournament']->users()->attach($account, ['role' => 'operator']);

        $this->actingAs($account)->get(route('judging.index', $first['tournament']))->assertForbidden();
        $this->get(route('tournaments.show', $second['tournament']))->assertForbidden();
        $this->get(route('dashboard'))->assertForbidden();
    }
}
