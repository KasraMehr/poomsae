<?php

namespace Tests\Feature;

use App\Actions\DrawRoundForms;
use App\Actions\RunCompetition;
use App\Actions\ScheduleRound;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesCompetition;
use Tests\TestCase;

class CutoffAdvancementTest extends TestCase
{
    use CreatesCompetition, RefreshDatabase;

    public function test_automatic_wt_cutoff_advances_top_eight_from_ten_entries(): void
    {
        // 10 entries in a multi-stage round-robin competition (2 planned rounds)
        $fixture = $this->competition(5, 10, 'round_robin', 1, 2);
        app(DrawRoundForms::class)->handle($fixture['admin'], $fixture['tournament'], $fixture['category']);
        $this->schedule($fixture);

        $round1 = $fixture['category']->rounds()->orderBy('sequence')->firstOrFail();
        $this->assertCount(10, $round1->bouts);

        $run = app(RunCompetition::class);

        // Score all entries differently so there is a clear ranking 1 to 10
        // Entry at bout index 0 gets 8.00 (highest), index 9 gets 3.50 (lowest)
        foreach ($round1->bouts()->orderBy('sequence')->get() as $index => $bout) {
            $accuracy = number_format(3.00 - ($index * 0.10), 2, '.', '');
            $presentation = '5.00';
            foreach ($bout->performances()->orderBy('form_number')->get() as $perf) {
                $this->startScoring($fixture, $perf);
                $this->scoreAll($fixture, $perf, $accuracy, $presentation);
                $run->command($fixture['admin'], $fixture['tournament'], $perf, [
                    'command' => 'approve',
                    'expected_version' => $perf->fresh()->version,
                ]);
            }
        }

        $this->assertSame('completed', $round1->fresh()->status);

        // Schedule round 2 without explicit advancement_count -> triggers automatic WT Cut-off
        // 10 entries (9 <= count <= 19) -> Top 8 advance, bottom 2 are cut off!
        app(ScheduleRound::class)->handle($fixture['admin'], $fixture['tournament'], $fixture['category'], [
            'court_id' => $fixture['tournament']->courts()->firstOrFail()->id,
            'judge_ids' => $fixture['judges']->pluck('id')->all(),
        ]);

        $round2 = $fixture['category']->rounds()->where('sequence', 2)->firstOrFail();
        $this->assertCount(8, $round2->bouts);

        // Top 8 entry IDs should match the first 8 bouts of round 1
        $top8EntryIds = $round1->bouts()->orderBy('sequence')->take(8)->pluck('winner_entry_id')->all();
        // For round-robin, each bout has 1 entry
        $expectedEntryIds = $round1->bouts()->with('entries')->orderBy('sequence')->take(8)
            ->get()->flatMap(fn ($b) => $b->entries->pluck('id'))->all();
        $actualEntryIds = $round2->bouts()->with('entries')->get()
            ->flatMap(fn ($b) => $b->entries->pluck('id'))->all();

        $this->assertSame($expectedEntryIds, $actualEntryIds);
    }

    public function test_manual_advancement_quota_by_operator_is_respected(): void
    {
        // 6 entries in round-robin, operator manually chooses to advance only top 3
        $fixture = $this->competition(5, 6, 'round_robin', 1, 2);
        app(DrawRoundForms::class)->handle($fixture['admin'], $fixture['tournament'], $fixture['category']);
        $this->schedule($fixture);

        $round1 = $fixture['category']->rounds()->orderBy('sequence')->firstOrFail();
        $run = app(RunCompetition::class);

        foreach ($round1->bouts()->orderBy('sequence')->get() as $index => $bout) {
            $accuracy = number_format(3.50 - ($index * 0.20), 2, '.', '');
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

        // Schedule round 2 with manual quota: advancement_count = 3
        app(ScheduleRound::class)->handle($fixture['admin'], $fixture['tournament'], $fixture['category'], [
            'court_id' => $fixture['tournament']->courts()->firstOrFail()->id,
            'judge_ids' => $fixture['judges']->pluck('id')->all(),
            'advancement_count' => 3,
        ]);

        $round2 = $fixture['category']->rounds()->where('sequence', 2)->firstOrFail();
        $this->assertCount(3, $round2->bouts);

        $expectedTop3 = $round1->bouts()->with('entries')->orderBy('sequence')->take(3)
            ->get()->flatMap(fn ($b) => $b->entries->pluck('id'))->all();
        $actualRound2Entries = $round2->bouts()->with('entries')->get()
            ->flatMap(fn ($b) => $b->entries->pluck('id'))->all();

        $this->assertSame($expectedTop3, $actualRound2Entries);
    }

    public function test_exact_tie_at_the_cutoff_boundary_advances_all_tied_contestants(): void
    {
        // 6 entries, manual quota of 3, but 3rd and 4th entries have identical scores!
        $fixture = $this->competition(5, 6, 'round_robin', 1, 2);
        app(DrawRoundForms::class)->handle($fixture['admin'], $fixture['tournament'], $fixture['category']);
        $this->schedule($fixture);

        $round1 = $fixture['category']->rounds()->orderBy('sequence')->firstOrFail();
        $run = app(RunCompetition::class);

        $scores = [
            0 => ['3.50', '5.00'], // rank 1
            1 => ['3.20', '5.00'], // rank 2
            2 => ['3.00', '5.00'], // rank 3 (tied with 4)
            3 => ['3.00', '5.00'], // rank 4 (tied with 3)
            4 => ['2.50', '5.00'], // rank 5
            5 => ['2.00', '5.00'], // rank 6
        ];

        foreach ($round1->bouts()->orderBy('sequence')->get() as $index => $bout) {
            foreach ($bout->performances()->orderBy('form_number')->get() as $perf) {
                $this->startScoring($fixture, $perf);
                $this->scoreAll($fixture, $perf, $scores[$index][0], $scores[$index][1]);
                $run->command($fixture['admin'], $fixture['tournament'], $perf, [
                    'command' => 'approve',
                    'expected_version' => $perf->fresh()->version,
                ]);
            }
        }

        // Quota is 3, but since 3rd and 4th are tied, both must advance -> 4 entries advance!
        app(ScheduleRound::class)->handle($fixture['admin'], $fixture['tournament'], $fixture['category'], [
            'court_id' => $fixture['tournament']->courts()->firstOrFail()->id,
            'judge_ids' => $fixture['judges']->pluck('id')->all(),
            'advancement_count' => 3,
        ]);

        $round2 = $fixture['category']->rounds()->where('sequence', 2)->firstOrFail();
        $this->assertCount(4, $round2->bouts);
    }

    public function test_presentation_score_breaks_tie_for_cutoff_advancement(): void
    {
        // 4 entries, manual quota of 2. Entries 1 and 2 both total 8.00, but entry 1 has higher presentation!
        $fixture = $this->competition(5, 4, 'round_robin', 1, 2);
        app(DrawRoundForms::class)->handle($fixture['admin'], $fixture['tournament'], $fixture['category']);
        $this->schedule($fixture);

        $round1 = $fixture['category']->rounds()->orderBy('sequence')->firstOrFail();
        $run = app(RunCompetition::class);

        $scores = [
            0 => ['3.50', '5.00'], // rank 1: total 8.50
            1 => ['2.50', '5.50'], // total 8.00 (accuracy 2.50 + presentation 5.50) -> Higher Presentation!
            2 => ['3.00', '5.00'], // total 8.00 (accuracy 3.00 + presentation 5.00) -> Lower Presentation!
            3 => ['2.00', '4.00'], // total 6.00
        ];

        foreach ($round1->bouts()->orderBy('sequence')->get() as $index => $bout) {
            foreach ($bout->performances()->orderBy('form_number')->get() as $perf) {
                $this->startScoring($fixture, $perf);
                $this->scoreAll($fixture, $perf, $scores[$index][0], $scores[$index][1]);
                $run->command($fixture['admin'], $fixture['tournament'], $perf, [
                    'command' => 'approve',
                    'expected_version' => $perf->fresh()->version,
                ]);
            }
        }

        // Quota is 2: Entry 0 (8.50) and Entry 1 (8.00 with higher pres) must advance! Entry 2 is eliminated.
        app(ScheduleRound::class)->handle($fixture['admin'], $fixture['tournament'], $fixture['category'], [
            'court_id' => $fixture['tournament']->courts()->firstOrFail()->id,
            'judge_ids' => $fixture['judges']->pluck('id')->all(),
            'advancement_count' => 2,
        ]);

        $round2 = $fixture['category']->rounds()->where('sequence', 2)->firstOrFail();
        $this->assertCount(2, $round2->bouts);

        $round1Bouts = $round1->bouts()->orderBy('sequence')->get();
        $entry0Id = $round1Bouts[0]->entries()->first()->id;
        $entry1Id = $round1Bouts[1]->entries()->first()->id;

        $actualAdvancingIds = $round2->bouts()->with('entries')->get()
            ->flatMap(fn ($b) => $b->entries->pluck('id'))->all();

        $this->assertContains($entry0Id, $actualAdvancingIds);
        $this->assertContains($entry1Id, $actualAdvancingIds);
    }

    public function test_reverse_rank_performance_order_for_advancing_round(): void
    {
        // 4 entries, top 3 advance, with advancement_order = 'reverse_rank'
        $fixture = $this->competition(5, 4, 'round_robin', 1, 2);
        app(DrawRoundForms::class)->handle($fixture['admin'], $fixture['tournament'], $fixture['category']);
        $this->schedule($fixture);

        $round1 = $fixture['category']->rounds()->orderBy('sequence')->firstOrFail();
        $run = app(RunCompetition::class);

        foreach ($round1->bouts()->orderBy('sequence')->get() as $index => $bout) {
            $accuracy = number_format(3.50 - ($index * 0.30), 2, '.', '');
            foreach ($bout->performances()->orderBy('form_number')->get() as $perf) {
                $this->startScoring($fixture, $perf);
                $this->scoreAll($fixture, $perf, $accuracy, '5.00');
                $run->command($fixture['admin'], $fixture['tournament'], $perf, [
                    'command' => 'approve',
                    'expected_version' => $perf->fresh()->version,
                ]);
            }
        }

        app(ScheduleRound::class)->handle($fixture['admin'], $fixture['tournament'], $fixture['category'], [
            'court_id' => $fixture['tournament']->courts()->firstOrFail()->id,
            'judge_ids' => $fixture['judges']->pluck('id')->all(),
            'advancement_count' => 3,
            'advancement_order' => 'reverse_rank',
        ]);

        $round2 = $fixture['category']->rounds()->where('sequence', 2)->firstOrFail();
        $this->assertCount(3, $round2->bouts);

        $round1Entries = $round1->bouts()->with('entries')->orderBy('sequence')->get()
            ->flatMap(fn ($b) => $b->entries->pluck('id'))->all();

        $actualRound2Entries = $round2->bouts()->with('entries')->get()
            ->flatMap(fn ($b) => $b->entries->pluck('id'))->all();

        // Since rank was Entry0 (rank 1), Entry1 (rank 2), Entry2 (rank 3),
        // reverse_rank should have: Entry2 (rank 3 first), Entry1 (rank 2 second), Entry0 (rank 1 last)
        $this->assertSame([$round1Entries[2], $round1Entries[1], $round1Entries[0]], $actualRound2Entries);
    }
}
