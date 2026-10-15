<?php

namespace Tests\Feature;

use App\Actions\CompetitionView;
use App\Actions\RunCompetition;
use App\Models\Bout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesCompetition;
use Tests\TestCase;

class TableWithdrawalTest extends TestCase
{
    use CreatesCompetition, RefreshDatabase;

    public function test_table_solo_bout_walkover_cancels_performances_and_allows_round_to_proceed_and_complete(): void
    {
        // 3 entries in round_robin (Table) format
        $fixture = $this->competition(5, 3, 'round_robin');
        $this->schedule($fixture);

        $bouts = Bout::orderBy('sequence')->get();
        $this->assertCount(3, $bouts);
        $this->assertSame(1, $bouts[0]->entries()->count());

        $firstBout = $bouts[0];
        $secondBout = $bouts[1];
        $thirdBout = $bouts[2];

        // 1. Walkover first bout (solo entry withdrawal) via HTTP endpoint
        $resolveUrl = '/tournaments/'.$fixture['tournament']->id.'/bouts/'.$firstBout->id.'/resolve';

        // Confirmation is required
        $this->actingAs($fixture['admin'])->post($resolveUrl, [
            'decision_type' => 'walkover',
            'reason' => 'ورزشکار به علت آسیب‌دیدگی انصراف داد',
        ])->assertSessionHasErrors('confirmed');

        // Successful solo walkover without winner_entry_id
        $this->post($resolveUrl, [
            'decision_type' => 'walkover',
            'confirmed' => true,
            'reason' => 'ورزشکار به علت آسیب‌دیدگی انصراف داد',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $firstBout->refresh();
        $this->assertSame('completed', $firstBout->status);
        $this->assertNull($firstBout->winner_entry_id);
        $this->assertStringContainsString('انصراف / عدم حضور', $firstBout->resolution_reason);

        // Performances of the withdrawn bout must be cancelled
        foreach ($firstBout->performances()->get() as $perf) {
            $this->assertSame('cancelled', $perf->status);
        }

        // 2. The next athlete (second bout) can start and complete their performances without order conflicts
        $run = app(RunCompetition::class);
        foreach ($secondBout->performances()->orderBy('form_number')->get() as $perf) {
            $this->startScoring($fixture, $perf);
            $this->scoreAll($fixture, $perf);
            $run->command($fixture['admin'], $fixture['tournament'], $perf, [
                'command' => 'approve',
                'expected_version' => $perf->fresh()->version,
            ]);
        }

        $secondBout->refresh();
        $this->assertSame('completed', $secondBout->status);

        // 3. The third athlete (third bout) can also start and complete their performances
        foreach ($thirdBout->performances()->orderBy('form_number')->get() as $perf) {
            $this->startScoring($fixture, $perf);
            $this->scoreAll($fixture, $perf);
            $run->command($fixture['admin'], $fixture['tournament'], $perf, [
                'command' => 'approve',
                'expected_version' => $perf->fresh()->version,
            ]);
        }

        $thirdBout->refresh();
        $this->assertSame('completed', $thirdBout->status);

        // 4. The round automatically completes because all bouts are completed
        $round = $fixture['category']->rounds()->firstOrFail();
        $this->assertSame('completed', $round->status);

        // 5. Standings properly reflect the withdrawn athlete at the bottom with withdrawn = true and rank = null
        $snapshot = app(CompetitionView::class)->snapshot($fixture['tournament'], $fixture['admin'], true);
        $standings = $snapshot['categories'][0]['standings'];

        $this->assertCount(3, $standings);
        $withdrawnRow = collect($standings)->firstWhere('id', $firstBout->entries()->first()->id);
        $this->assertTrue($withdrawnRow['withdrawn']);
        $this->assertNull($withdrawnRow['rank']);
        $this->assertNull($withdrawnRow['score']);

        // The other two athletes have ranks and scores
        $scoredRows = collect($standings)->where('withdrawn', false)->values();
        $this->assertCount(2, $scoredRows);
        $this->assertNotNull($scoredRows[0]['rank']);
        $this->assertNotNull($scoredRows[0]['score']);

        // 6. Tournament can now be completed successfully
        $this->post('/tournaments/'.$fixture['tournament']->id.'/complete')->assertSessionHasNoErrors();
        $this->assertSame('completed', $fixture['tournament']->fresh()->status);
    }

    public function test_table_solo_walkover_after_first_form_cancels_remaining_form(): void
    {
        $fixture = $this->competition(5, 2, 'round_robin');
        $this->schedule($fixture);

        $bouts = Bout::orderBy('sequence')->get();
        $firstBout = $bouts[0];
        $forms = $firstBout->performances()->orderBy('form_number')->get();

        // Complete form 1
        $this->startScoring($fixture, $forms[0]);
        $this->scoreAll($fixture, $forms[0]);
        app(RunCompetition::class)->command($fixture['admin'], $fixture['tournament'], $forms[0], [
            'command' => 'approve',
            'expected_version' => $forms[0]->fresh()->version,
        ]);

        $this->assertSame('approved', $forms[0]->fresh()->status);
        $this->assertSame('pending', $forms[1]->fresh()->status);

        // Athlete withdraws before form 2
        $this->actingAs($fixture['admin'])->post('/tournaments/'.$fixture['tournament']->id.'/bouts/'.$firstBout->id.'/resolve', [
            'decision_type' => 'walkover',
            'confirmed' => true,
            'reason' => 'انصراف قبل از فرم دوم',
        ])->assertSessionHasNoErrors();

        // Form 1 stays approved, Form 2 becomes cancelled
        $this->assertSame('approved', $forms[0]->fresh()->status);
        $this->assertSame('cancelled', $forms[1]->fresh()->status);
        $this->assertSame('completed', $firstBout->fresh()->status);
    }
}
