<?php

namespace Tests\Feature;

use App\Actions\CompetitionView;
use App\Actions\RunCompetition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesCompetition;
use Tests\TestCase;

class RoundRobinCutoffTest extends TestCase
{
    use CreatesCompetition, RefreshDatabase;

    public function test_semi_final_to_final_cut_off_advances_top_eight_contestants(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 1)->setTime(9, 0));
        // 10 entries in round_robin with 2 planned rounds (Semi-final and Final)
        $fixture = $this->competition(5, 10, 'round_robin', 1, 2);
        $this->artisan('poomsae:draw-forms')->assertSuccessful();
        $this->schedule($fixture);

        $category = $fixture['category'];
        $rounds = $category->rounds()->orderBy('sequence')->get();
        $this->assertCount(2, $rounds);

        $round1 = $rounds[0];
        $round2 = $rounds[1];

        $run = app(RunCompetition::class);
        $bouts = $round1->bouts()->with(['entries', 'performances'])->orderBy('sequence')->get();
        $this->assertCount(10, $bouts);

        // Score entries: bout 0 lowest (2.00), bout 9 highest (2.90)
        foreach ($bouts as $index => $bout) {
            $accuracy = number_format(2.00 + ($index * 0.10), 2, '.', '');
            foreach ($bout->performances()->orderBy('form_number')->get() as $perf) {
                $this->startScoring($fixture, $perf);
                $this->scoreAll($fixture, $perf, $accuracy, '5.00');
                $run->command($fixture['admin'], $fixture['tournament'], $perf, [
                    'command' => 'approve',
                    'expected_version' => $perf->fresh()->version,
                ]);
            }
        }

        $this->assertSame('completed', $round1->fresh()->status);

        // Schedule Round 2 (Final)
        $this->schedule($fixture);

        $round2Bouts = $round2->bouts()->with(['entries', 'performances'])->orderBy('sequence')->get();
        // Top 8 advance to the final
        $this->assertCount(8, $round2Bouts);

        $advancingEntryIds = $round2Bouts->flatMap(fn ($b) => $b->entries->pluck('id'))->all();

        $eliminated1 = $bouts[0]->entries->first()->id; // score 2.00 (rank 10)
        $eliminated2 = $bouts[1]->entries->first()->id; // score 2.10 (rank 9)

        $this->assertNotContains($eliminated1, $advancingEntryIds);
        $this->assertNotContains($eliminated2, $advancingEntryIds);

        // Top 8 contestants (bouts 2..9) are in the final
        for ($i = 2; $i <= 9; $i++) {
            $this->assertContains($bouts[$i]->entries->first()->id, $advancingEntryIds);
        }

        $topAthleteId = $bouts[9]->entries->first()->id;

        // Complete final round
        foreach ($round2Bouts as $bout) {
            $isTopAthlete = $bout->entries->first()->id === $topAthleteId;
            $acc = $isTopAthlete ? '3.50' : '2.50';
            foreach ($bout->performances()->orderBy('form_number')->get() as $perf) {
                $this->startScoring($fixture, $perf);
                $this->scoreAll($fixture, $perf, $acc, '5.00');
                $run->command($fixture['admin'], $fixture['tournament'], $perf, [
                    'command' => 'approve',
                    'expected_version' => $perf->fresh()->version,
                ]);
            }
        }

        $this->assertSame('completed', $round2->fresh()->status);

        $snapshot = app(CompetitionView::class)->snapshot($fixture['tournament']->fresh(), $fixture['admin'], true);
        $catData = $snapshot['categories'][0];

        $this->assertTrue($catData['completed']);
        $this->assertSame($topAthleteId, $catData['champion_id']);
    }

    public function test_preliminary_cut_off_advances_top_fifty_percent(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 1)->setTime(9, 0));
        // 20 entries in round_robin with 3 planned rounds (Preliminary, Semi-Final, Final)
        $fixture = $this->competition(5, 20, 'round_robin', 1, 3);
        $this->artisan('poomsae:draw-forms')->assertSuccessful();
        $this->schedule($fixture);

        $category = $fixture['category'];
        $rounds = $category->rounds()->orderBy('sequence')->get();
        $this->assertCount(3, $rounds);

        $round1 = $rounds[0];
        $round2 = $rounds[1];
        $run = app(RunCompetition::class);
        $bouts = $round1->bouts()->with(['entries', 'performances'])->orderBy('sequence')->get();
        $this->assertCount(20, $bouts);

        // Score all entries with distinct scores (0 to 19)
        foreach ($bouts as $index => $bout) {
            $accuracy = number_format(1.00 + ($index * 0.10), 2, '.', '');
            foreach ($bout->performances()->orderBy('form_number')->get() as $perf) {
                $this->startScoring($fixture, $perf);
                $this->scoreAll($fixture, $perf, $accuracy, '5.00');
                $run->command($fixture['admin'], $fixture['tournament'], $perf, [
                    'command' => 'approve',
                    'expected_version' => $perf->fresh()->version,
                ]);
            }
        }

        $this->assertSame('completed', $round1->fresh()->status);

        // Schedule Round 2 (Semi-final)
        $this->schedule($fixture);

        $round2Bouts = $round2->bouts()->with(['entries', 'performances'])->orderBy('sequence')->get();
        // 50% of 20 = 10 advance to semi-final
        $this->assertCount(10, $round2Bouts);

        $advancingIds = $round2Bouts->flatMap(fn ($b) => $b->entries->pluck('id'))->all();

        // Bouts 0..9 were eliminated (bottom 10)
        for ($i = 0; $i < 10; $i++) {
            $this->assertNotContains($bouts[$i]->entries->first()->id, $advancingIds);
        }

        // Bouts 10..19 advanced (top 10)
        for ($i = 10; $i < 20; $i++) {
            $this->assertContains($bouts[$i]->entries->first()->id, $advancingIds);
        }
    }

    public function test_tie_at_cut_off_boundary_advances_all_tied_contestants(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 1)->setTime(9, 0));
        // 10 entries in round_robin with 2 planned rounds
        $fixture = $this->competition(5, 10, 'round_robin', 1, 2);
        $this->artisan('poomsae:draw-forms')->assertSuccessful();
        $this->schedule($fixture);

        $category = $fixture['category'];
        $round1 = $category->rounds()->firstOrFail();
        $run = app(RunCompetition::class);
        $bouts = $round1->bouts()->with(['entries', 'performances'])->orderBy('sequence')->get();

        // Bout 0: lowest score (2.00)
        // Bout 1 and Bout 2: exactly tied at 2.10 (8th and 9th place)
        // Bouts 3..9: scores 2.20..2.80 (top 7 places)
        foreach ($bouts as $index => $bout) {
            $accuracy = match ($index) {
                0 => '2.00',
                1 => '2.10',
                2 => '2.10', // tied with index 1
                default => number_format(2.20 + (($index - 3) * 0.10), 2, '.', ''),
            };

            foreach ($bout->performances()->orderBy('form_number')->get() as $perf) {
                $this->startScoring($fixture, $perf);
                $this->scoreAll($fixture, $perf, $accuracy, '5.00');
                $run->command($fixture['admin'], $fixture['tournament'], $perf, [
                    'command' => 'approve',
                    'expected_version' => $perf->fresh()->version,
                ]);
            }
        }

        $this->assertSame('completed', $round1->fresh()->status);

        // Schedule Round 2 (Final)
        $this->schedule($fixture);

        $round2 = $category->rounds()->where('sequence', 2)->firstOrFail();
        $round2Bouts = $round2->bouts()->with(['entries', 'performances'])->orderBy('sequence')->get();

        // Since bout 1 and bout 2 tied for 8th place, both advance -> 9 advance instead of 8
        $this->assertCount(9, $round2Bouts);

        $advancingIds = $round2Bouts->flatMap(fn ($b) => $b->entries->pluck('id'))->all();

        $this->assertNotContains($bouts[0]->entries->first()->id, $advancingIds); // eliminated (rank 10)
        $this->assertContains($bouts[1]->entries->first()->id, $advancingIds); // tied at 8th
        $this->assertContains($bouts[2]->entries->first()->id, $advancingIds); // tied at 8th
    }

    public function test_manager_can_override_advancing_count_with_custom_cutoff(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 1)->setTime(9, 0));
        // 10 entries in round_robin with 2 planned rounds
        $fixture = $this->competition(5, 10, 'round_robin', 1, 2);
        $this->artisan('poomsae:draw-forms')->assertSuccessful();
        $this->schedule($fixture);

        $category = $fixture['category'];
        $round1 = $category->rounds()->firstOrFail();
        $run = app(RunCompetition::class);
        $bouts = $round1->bouts()->with(['entries', 'performances'])->orderBy('sequence')->get();

        // Score entries: bout 0 lowest (2.00), bout 9 highest (2.90)
        foreach ($bouts as $index => $bout) {
            $accuracy = number_format(2.00 + ($index * 0.10), 2, '.', '');
            foreach ($bout->performances()->orderBy('form_number')->get() as $perf) {
                $this->startScoring($fixture, $perf);
                $this->scoreAll($fixture, $perf, $accuracy, '5.00');
                $run->command($fixture['admin'], $fixture['tournament'], $perf, [
                    'command' => 'approve',
                    'expected_version' => $perf->fresh()->version,
                ]);
            }
        }

        $this->assertSame('completed', $round1->fresh()->status);

        // Schedule Round 2 (Final) with custom advancing_count = 4 via HTTP endpoint
        $courtId = $fixture['tournament']->courts()->firstOrFail()->id;
        $judgeIds = $fixture['judges']->pluck('id')->all();

        $this->actingAs($fixture['admin'])->post('/tournaments/'.$fixture['tournament']->id.'/categories/'.$category->id.'/rounds', [
            'court_id' => $courtId,
            'judge_ids' => $judgeIds,
            'advancing_count' => 4,
        ])->assertSessionHasNoErrors();

        $round2 = $category->rounds()->where('sequence', 2)->firstOrFail();
        $round2Bouts = $round2->bouts()->with(['entries', 'performances'])->orderBy('sequence')->get();

        // Exactly 4 advance (top 4: bouts 6, 7, 8, 9)
        $this->assertCount(4, $round2Bouts);

        $advancingIds = $round2Bouts->flatMap(fn ($b) => $b->entries->pluck('id'))->all();

        // Bottom 6 eliminated
        for ($i = 0; $i < 6; $i++) {
            $this->assertNotContains($bouts[$i]->entries->first()->id, $advancingIds);
        }

        // Top 4 advanced
        for ($i = 6; $i < 10; $i++) {
            $this->assertContains($bouts[$i]->entries->first()->id, $advancingIds);
        }
    }
}
