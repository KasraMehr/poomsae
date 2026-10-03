<?php

namespace Tests\Feature;

use App\Actions\CompetitionView;
use App\Actions\DrawRoundForms;
use App\Actions\RunCompetition;
use App\Models\Draw;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesCompetition;
use Tests\TestCase;

class StageDrawTest extends TestCase
{
    use CreatesCompetition, RefreshDatabase;

    public function test_four_nonrepeating_stages_draw_all_eight_forms_once(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 1)->setTime(6, 0));
        $fixture = $this->competition(5, 2, 'round_robin', 1, 4, false);
        $this->artisan('poomsae:draw-forms')->assertSuccessful();
        $rounds = $fixture['category']->rounds()->orderBy('sequence')->get();

        $this->assertCount(4, $rounds);
        $this->assertSame([], $fixture['category']->form_sequence);
        $this->assertCount(8, $rounds->flatMap(fn ($round) => $round->form_sequence)->unique());
        $this->assertTrue($rounds->every(fn ($round) => count($round->form_sequence) === 2 && $round->forms_drawn_at !== null));
        $this->assertDatabaseCount('draws', 4);
        $this->artisan('poomsae:draw-forms')->assertSuccessful();
        $this->assertDatabaseCount('draws', 4);
    }

    public function test_day_before_draw_waits_for_the_configured_local_time(): void
    {
        $this->travelTo(now()->setDate(2026, 9, 30)->setTime(4, 29));
        $fixture = $this->competition(5, 2, 'knockout', 1, 1, true, 'day_before');
        $this->artisan('poomsae:draw-forms')->assertSuccessful();
        $this->assertDatabaseCount('draws', 0);
        $this->travelTo(now()->addMinute());
        $this->artisan('poomsae:draw-forms')->assertSuccessful();
        $this->assertNotNull($fixture['category']->rounds()->firstOrFail()->forms_drawn_at);
    }

    public function test_before_stage_draw_blocks_execution_and_waits_for_previous_stage_completion(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 1)->setTime(6, 0));
        $fixture = $this->competition(5, 2, 'round_robin', 1, 2, false, 'before_stage');
        $this->schedule($fixture);
        $rounds = $fixture['category']->rounds()->orderBy('sequence')->get();
        $performance = $rounds[0]->bouts()->orderBy('sequence')->firstOrFail()->performances()->orderBy('id')->firstOrFail();
        $base = '/tournaments/'.$fixture['tournament']->id;
        $this->actingAs($fixture['admin'])->post($base.'/performances/'.$performance->id.'/command', [
            'command' => 'start', 'expected_version' => $performance->version,
        ])->assertSessionHasErrors('operation');
        $this->assertSame('pending', $performance->fresh()->status);
        $this->post($base.'/categories/'.$fixture['category']->id.'/forms/draw', ['round_id' => $rounds[1]->id])->assertSessionHasErrors('operation');
        $this->post($base.'/categories/'.$fixture['category']->id.'/forms/draw', ['round_id' => $rounds[0]->id])->assertSessionHasNoErrors();

        foreach ($rounds[0]->bouts()->orderBy('sequence')->get() as $bout) {
            foreach ($bout->performances()->orderBy('id')->get() as $item) {
                $this->startScoring($fixture, $item);
                $this->scoreAll($fixture, $item);
                app(RunCompetition::class)->command($fixture['admin'], $fixture['tournament'], $item, ['command' => 'approve', 'expected_version' => $item->fresh()->version]);
            }
        }
        $this->post($base.'/complete')->assertSessionHasErrors('operation');
        $this->assertSame('completed', $rounds[0]->fresh()->status);
        $this->post($base.'/categories/'.$fixture['category']->id.'/forms/draw', ['round_id' => $rounds[1]->id])->assertSessionHasNoErrors();
        $this->schedule($fixture);
        $this->assertDatabaseCount('performances', 8);
        $this->assertEmpty(array_intersect($rounds[0]->fresh()->form_sequence, $rounds[1]->fresh()->form_sequence));
        $this->assertSame(2, Draw::where('kind', 'forms')->count());
        foreach ($rounds[1]->bouts()->orderBy('sequence')->get() as $bout) {
            foreach ($bout->performances()->orderBy('id')->get() as $item) {
                $this->startScoring($fixture, $item);
                $this->scoreAll($fixture, $item, '2.50', '5.00');
                app(RunCompetition::class)->command($fixture['admin'], $fixture['tournament'], $item, ['command' => 'approve', 'expected_version' => $item->fresh()->version]);
            }
        }
        $snapshot = app(CompetitionView::class)->snapshot($fixture['tournament'], $fixture['admin']);
        $this->assertTrue($snapshot['categories'][0]['completed']);
        $this->assertSame($rounds[1]->id, $snapshot['categories'][0]['standings_round_id']);
        $this->assertTrue(collect($snapshot['categories'][0]['standings'])->every(fn ($row) => $row['score'] === '15.000000'));
        $this->post($base.'/complete')->assertSessionHasNoErrors();
    }

    public function test_more_than_four_nonrepeating_stages_are_rejected(): void
    {
        $fixture = $this->competition();
        $this->actingAs($fixture['admin'])->post('/tournaments/'.$fixture['tournament']->id.'/categories', [
            'name' => 'غیرتکراری', 'gender' => 'open', 'format' => 'knockout', 'judge_count' => 5,
            'discard_each_end' => 1, 'rules_acknowledged' => true, 'planned_round_count' => 5,
            'allow_form_repetition' => false, 'form_names' => array_map(fn ($number) => 'فرم '.$number, range(1, 8)),
        ])->assertSessionHasErrors('operation');
        $this->assertDatabaseCount('categories', 1);
    }

    public function test_repetition_allows_six_stages_with_a_distinct_pair_inside_each_stage(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 1)->setTime(6, 0));
        $fixture = $this->competition(5, 2, 'round_robin', 1, 6);
        app(DrawRoundForms::class)->handle($fixture['admin'], $fixture['tournament'], $fixture['category']);
        $rounds = $fixture['category']->rounds()->get();
        $this->assertCount(6, $rounds);
        $this->assertTrue($rounds->every(fn ($round) => count(array_unique($round->form_sequence)) === 2));
    }

    public function test_knockout_stage_count_must_match_checked_in_entries(): void
    {
        $fixture = $this->competition(5, 2, 'knockout', 1, 2);
        $this->actingAs($fixture['admin'])->post('/tournaments/'.$fixture['tournament']->id.'/categories/'.$fixture['category']->id.'/rounds', [
            'court_id' => $fixture['tournament']->courts()->firstOrFail()->id,
            'judge_ids' => $fixture['judges']->pluck('id')->all(),
        ])->assertSessionHasErrors('operation');
        $this->assertDatabaseCount('bouts', 0);
    }

    public function test_draw_requires_manager_and_rejects_a_round_from_another_category(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 1)->setTime(6, 0));
        $fixture = $this->competition(5, 2, 'knockout', 1, 1, true, 'before_stage');
        $other = $this->competition(5, 2, 'knockout', 1, 1, true, 'before_stage');
        $url = '/tournaments/'.$fixture['tournament']->id.'/categories/'.$fixture['category']->id.'/forms/draw';
        $this->actingAs($fixture['judges'][0])->post($url)->assertForbidden();
        $this->actingAs($fixture['admin'])->post($url, ['round_id' => $other['category']->rounds()->firstOrFail()->id])->assertNotFound();

        $this->assertDatabaseCount('draws', 0);
    }

    public function test_planned_knockout_advances_to_the_final_with_the_forms_of_each_stage(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 1)->setTime(6, 0));
        $fixture = $this->competition(5, 4, 'knockout', 1, 2, false);
        app(DrawRoundForms::class)->handle($fixture['admin'], $fixture['tournament'], $fixture['category']);
        $run = app(RunCompetition::class);
        for ($sequence = 1; $sequence <= 2; $sequence++) {
            $this->schedule($fixture);
            $round = $fixture['category']->rounds()->where('sequence', $sequence)->firstOrFail();
            foreach ($round->bouts()->orderBy('sequence')->get() as $bout) {
                $winner = $bout->entries()->firstOrFail()->id;
                foreach ($bout->performances()->orderBy('form_number')->orderBy('id')->get() as $performance) {
                    $this->assertSame($round->form_sequence[$performance->form_number - 1], $performance->poomsae_form_id);
                    $this->startScoring($fixture, $performance);
                    $this->scoreAll($fixture, $performance, '2.50', $performance->entry_id === $winner ? '6.00' : '5.00');
                    $run->command($fixture['admin'], $fixture['tournament'], $performance, ['command' => 'approve', 'expected_version' => $performance->fresh()->version]);
                }
                $this->assertSame($winner, $bout->fresh()->winner_entry_id);
            }
            $this->assertSame('completed', $round->fresh()->status);
        }
        $run->complete($fixture['admin'], $fixture['tournament']);
        $this->assertSame('completed', $fixture['tournament']->fresh()->status);
    }
}
