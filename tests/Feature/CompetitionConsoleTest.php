<?php

namespace Tests\Feature;

use App\Actions\CompetitionConsole;
use App\Actions\RunCompetition;
use App\Actions\SubmitScore;
use App\Models\Performance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\CreatesCompetition;
use Tests\TestCase;

class CompetitionConsoleTest extends TestCase
{
    use CreatesCompetition, RefreshDatabase;

    public function test_each_role_enters_the_correct_live_competition_surface(): void
    {
        $fixture = $this->competition();
        $this->schedule($fixture);

        $this->actingAs($fixture['admin'])->get(route('tournaments.show', $fixture['tournament']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Competition/Console')
                ->has('console.courts', 1)
                ->where('console.attention.ready', 2));

        $this->actingAs($fixture['judges'][0])->get(route('tournaments.show', $fixture['tournament']))
            ->assertRedirect(route('judging.index', $fixture['tournament']));

        $display = User::factory()->create();
        $fixture['tournament']->users()->attach($display, ['role' => 'display']);
        $this->actingAs($display)->get(route('tournaments.show', $fixture['tournament']))
            ->assertRedirect(route('scoreboard.show', $fixture['tournament']));

        $this->actingAs(User::factory()->create())->get(route('tournaments.show', $fixture['tournament']))
            ->assertForbidden();
    }

    public function test_console_tracks_court_state_and_each_missing_judge_seat(): void
    {
        $fixture = $this->competition();
        $this->schedule($fixture);
        $performance = Performance::query()->orderBy('id')->firstOrFail();
        $console = app(CompetitionConsole::class);

        $initial = $console->snapshot($fixture['tournament'], $fixture['admin']);
        $this->assertNull($initial['courts'][0]['active']);
        $this->assertSame(2, $initial['attention']['ready']);
        $this->assertTrue(collect($initial['courts'][0]['queue'])->every(fn (array $queued) => $queued['form_number'] === 1));

        $runner = app(RunCompetition::class);
        $runner->command($fixture['admin'], $fixture['tournament'], $performance, [
            'command' => 'start', 'expected_version' => $performance->fresh()->version,
        ]);
        $running = $console->snapshot($fixture['tournament'], $fixture['admin']);
        $this->assertSame($performance->id, $running['courts'][0]['active']['id']);
        $this->assertSame(1, $running['attention']['running']);

        $runner->command($fixture['admin'], $fixture['tournament'], $performance, [
            'command' => 'finish', 'expected_version' => $performance->fresh()->version,
        ]);
        $scoring = $console->snapshot($fixture['tournament'], $fixture['admin']);
        $this->assertSame([1, 2, 3, 4, 5], $scoring['courts'][0]['active']['missing_seats']);

        app(SubmitScore::class)->handle($fixture['judges'][0], $fixture['tournament'], $performance, [
            'request_id' => (string) Str::uuid(),
            'expected_version' => $performance->fresh()->version,
            'expected_revision' => 0,
            'accuracy' => '2.50',
            'presentation' => '6.00',
        ]);
        $oneScore = $console->snapshot($fixture['tournament'], $fixture['admin']);
        $this->assertSame([1], $oneScore['courts'][0]['active']['submitted_seats']);
        $this->assertSame([2, 3, 4, 5], $oneScore['courts'][0]['active']['missing_seats']);
    }
}
