<?php

namespace Tests\Feature;

use App\Actions\CompetitionConsole;
use App\Actions\CompetitionSetup;
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
                ->where('console.attention.ready', 1));

        $this->actingAs($fixture['judges'][0])->get(route('tournaments.show', $fixture['tournament']))
            ->assertRedirect(route('judging.index', $fixture['tournament']));

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
        $this->assertSame(1, $initial['attention']['ready']);
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
            ...$this->detailedScoreInput('2.50', '6.00'),
        ]);
        $oneScore = $console->snapshot($fixture['tournament'], $fixture['admin']);
        $this->assertSame([1], $oneScore['courts'][0]['active']['submitted_seats']);
        $this->assertSame([2, 3, 4, 5], $oneScore['courts'][0]['active']['missing_seats']);
    }

    public function test_court_continues_the_current_knockout_bout_before_another_category(): void
    {
        $fixture = $this->competition();
        $this->schedule($fixture);
        $otherPerformance = $fixture['category']->rounds()->firstOrFail()->bouts()->firstOrFail()->performances()->orderBy('form_number')->orderBy('id')->firstOrFail();
        $setup = app(CompetitionSetup::class);
        $category = $setup->category($fixture['admin'], $fixture['tournament'], [
            'name' => 'ردهٔ دوم', 'gender' => 'male', 'minimum_age' => 10, 'maximum_age' => 40,
            'format' => 'knockout', 'judge_count' => 5, 'accuracy_max' => 400,
            'discard_each_end' => 1, 'rules_acknowledged' => true, 'form_names' => ['فرم اول', 'فرم دوم'],
        ]);
        foreach ([1, 2] as $number) {
            $entry = $setup->entry($fixture['admin'], $fixture['tournament'], $category, [
                'first_name' => 'ورزشکار'.$number, 'last_name' => 'ردهٔ دوم', 'birth_date' => '2005-01-01', 'gender' => 'male',
            ]);
            $setup->entryStatus($fixture['admin'], $fixture['tournament'], $entry, 'checked_in');
        }
        $this->schedule([...$fixture, 'category' => $category]);
        $bout = $category->rounds()->firstOrFail()->bouts()->firstOrFail();
        $performances = $bout->performances()->orderBy('form_number')->orderBy('id')->get();
        $winnerEntryId = $performances->first()->entry_id;
        $console = app(CompetitionConsole::class);

        foreach ($performances as $index => $performance) {
            $this->startScoring($fixture, $performance);
            $this->scoreAll($fixture, $performance, '2.50', $performance->entry_id === $winnerEntryId ? '6.00' : '5.00');
            $this->actingAs($fixture['admin'])->post(route('operations.command', [$fixture['tournament'], $performance]), [
                'command' => 'approve', 'expected_version' => $performance->fresh()->version,
            ])->assertRedirect()->assertSessionHasNoErrors();

            $court = $console->snapshot($fixture['tournament'], $fixture['admin'])['courts'][0];
            $this->assertNull($court['active']);
            if ($index < $performances->count() - 1) {
                $this->assertSame([$performances[$index + 1]->id], $court['queue']->pluck('id')->all());
                $this->assertSame('running', $bout->fresh()->status);
            } else {
                $this->assertSame([$otherPerformance->id], $court['queue']->pluck('id')->all());
                $this->assertSame('completed', $bout->fresh()->status);
            }
        }
    }

    public function test_court_does_not_offer_another_bout_while_knockout_tie_is_unresolved(): void
    {
        $fixture = $this->competition(5, 4);
        $this->schedule($fixture);
        $bout = $fixture['category']->rounds()->firstOrFail()->bouts()->orderBy('sequence')->firstOrFail();
        foreach ($bout->performances()->orderBy('form_number')->orderBy('id')->get() as $performance) {
            $this->startScoring($fixture, $performance);
            $this->scoreAll($fixture, $performance);
            app(RunCompetition::class)->command($fixture['admin'], $fixture['tournament'], $performance, [
                'command' => 'approve', 'expected_version' => $performance->fresh()->version,
            ]);
        }

        $court = app(CompetitionConsole::class)->snapshot($fixture['tournament'], $fixture['admin'])['courts'][0];
        $this->assertSame('running', $bout->fresh()->status);
        $this->assertNull($bout->fresh()->winner_entry_id);
        $this->assertNull($court['active']);
        $this->assertCount(0, $court['queue']);
    }
}
