<?php

namespace Tests\Feature;

use App\Actions\CompetitionSetup;
use App\Actions\CompetitionView;
use App\Actions\DrawRoundForms;
use App\Actions\RunCompetition;
use App\Models\Draw;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\CreatesCompetition;
use Tests\TestCase;

class AutomaticKnockoutStagesTest extends TestCase
{
    use CreatesCompetition, RefreshDatabase;

    #[TestWith([2, 7, 1])]
    #[TestWith([3, 1, 2])]
    #[TestWith([4, 1, 2])]
    #[TestWith([5, 1, 3])]
    #[TestWith([8, 1, 3])]
    #[TestWith([9, 1, 4])]
    #[TestWith([16, 1, 4])]
    public function test_scheduling_calculates_stages_from_checked_in_entries(int $entryCount, int $initialStageCount, int $expectedStageCount): void
    {
        $fixture = $this->competition(3, $entryCount, 'knockout', 0, $initialStageCount);

        $this->actingAs($fixture['admin'])->post('/tournaments/'.$fixture['tournament']->id.'/categories/'.$fixture['category']->id.'/rounds', [
            'court_id' => $fixture['tournament']->courts()->firstOrFail()->id,
            'judge_ids' => $fixture['judges']->pluck('id')->all(),
        ])->assertSessionHasNoErrors();

        $this->assertSame($expectedStageCount, $fixture['category']->fresh()->planned_round_count);
        $this->assertSame(range(1, $expectedStageCount), $fixture['category']->rounds()->orderBy('sequence')->pluck('sequence')->all());
        $this->assertSame(1, $fixture['category']->rounds()->whereHas('bouts')->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'category.stages_calculated', 'subject_id' => $fixture['category']->id]);
    }

    public function test_drawing_forms_creates_the_automatic_plan_and_draws_every_required_stage(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 6)->setTime(12, 0));
        $fixture = $this->competition(3, 8, 'knockout', 0, 1);

        $this->actingAs($fixture['admin'])->post('/tournaments/'.$fixture['tournament']->id.'/categories/'.$fixture['category']->id.'/forms/draw')->assertSessionHasNoErrors();

        $this->assertSame(3, $fixture['category']->fresh()->planned_round_count);
        $this->assertSame(3, $fixture['category']->rounds()->whereNotNull('forms_drawn_at')->count());
        $this->assertSame(3, Draw::where('kind', 'forms')->count());
        $this->schedule($fixture);
        $firstRound = $fixture['category']->rounds()->where('sequence', 1)->firstOrFail();
        $this->assertSame(4, $firstRound->bouts()->count());
        $this->assertTrue($firstRound->bouts()->with('performances')->get()->flatMap(fn ($bout) => $bout->performances)->every(fn ($performance) => $performance->poomsae_form_id !== null));
    }

    public function test_new_knockout_age_categories_do_not_require_a_manual_stage_count(): void
    {
        $fixture = $this->competition(3, 0, 'knockout', 0, 1);

        $this->actingAs($fixture['admin'])->post('/tournaments/'.$fixture['tournament']->id.'/categories', [
            'name' => 'ردهٔ خودکار', 'discipline' => 'recognized', 'entry_type' => 'individual', 'gender' => 'male',
            'age_category' => 'recognized_individual_under_12', 'format' => 'knockout',
            'judge_count' => 3, 'discard_each_end' => 0, 'rules_acknowledged' => true,
        ])->assertSessionHasNoErrors();

        $category = $fixture['tournament']->categories()->where('name', 'ردهٔ خودکار')->firstOrFail();
        $this->assertSame(1, $category->planned_round_count);
        $this->assertSame(8, $category->forms()->count());
    }

    public function test_added_entries_expand_the_plan_without_redrawing_existing_forms(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 6)->setTime(12, 0));
        $fixture = $this->competition(3, 2, 'knockout', 0, 1);
        app(DrawRoundForms::class)->handle($fixture['admin'], $fixture['tournament'], $fixture['category']);
        $firstRound = $fixture['category']->rounds()->firstOrFail();
        $firstDrawId = $firstRound->form_draw_id;
        for ($number = 1; $number <= 6; $number++) {
            $entry = app(CompetitionSetup::class)->entry($fixture['admin'], $fixture['tournament'], $fixture['category'], [
                'first_name' => 'ورزشکار جدید '.$number, 'last_name' => 'آزمایشی', 'birth_date' => '2005-01-01', 'gender' => 'male',
            ]);
            app(CompetitionSetup::class)->entryStatus($fixture['admin'], $fixture['tournament'], $entry, 'checked_in');
        }

        app(DrawRoundForms::class)->handle($fixture['admin'], $fixture['tournament'], $fixture['category']);

        $this->assertSame(3, $fixture['category']->fresh()->planned_round_count);
        $this->assertSame($firstDrawId, $firstRound->fresh()->form_draw_id);
        $this->assertSame(3, $fixture['category']->rounds()->whereNotNull('forms_drawn_at')->count());
        $this->assertSame(3, Draw::where('kind', 'forms')->count());
    }

    public function test_withdrawals_reduce_the_plan_and_preserve_the_history_of_unused_form_draws(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 6)->setTime(12, 0));
        $fixture = $this->competition(3, 8, 'knockout', 0, 3);
        app(DrawRoundForms::class)->handle($fixture['admin'], $fixture['tournament'], $fixture['category']);
        $firstRound = $fixture['category']->rounds()->where('sequence', 1)->firstOrFail();
        $originalForms = $firstRound->form_sequence;
        foreach ($fixture['category']->entries()->orderBy('id')->skip(2)->take(6)->get() as $entry) {
            app(CompetitionSetup::class)->entryStatus($fixture['admin'], $fixture['tournament'], $entry, 'withdrawn');
        }

        $this->schedule($fixture);

        $this->assertSame(1, $fixture['category']->fresh()->planned_round_count);
        $this->assertSame(1, $fixture['category']->rounds()->count());
        $this->assertSame($originalForms, $firstRound->fresh()->form_sequence);
        $this->assertSame(3, Draw::where('kind', 'forms')->count());
        $this->assertSame(2, Draw::where('kind', 'forms')->whereNull('competition_round_id')->count());
    }

    public function test_unresolved_attendance_blocks_the_automatic_draw_without_changing_the_plan(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 6)->setTime(12, 0));
        $fixture = $this->competition(3, 8, 'knockout', 0, 1);
        $entry = $fixture['category']->entries()->firstOrFail();
        app(CompetitionSetup::class)->entryStatus($fixture['admin'], $fixture['tournament'], $entry, 'registered');

        $this->actingAs($fixture['admin'])->post('/tournaments/'.$fixture['tournament']->id.'/categories/'.$fixture['category']->id.'/forms/draw')->assertSessionHasErrors('operation');

        $this->assertSame(1, $fixture['category']->fresh()->planned_round_count);
        $this->assertSame(1, $fixture['category']->rounds()->count());
        $this->assertDatabaseCount('draws', 0);
    }

    public function test_nonrepeating_forms_reject_a_calculated_plan_over_four_stages(): void
    {
        $fixture = $this->competition(3, 17, 'knockout', 0, 1, false);

        $this->actingAs($fixture['admin'])->post('/tournaments/'.$fixture['tournament']->id.'/categories/'.$fixture['category']->id.'/rounds', [
            'court_id' => $fixture['tournament']->courts()->firstOrFail()->id,
            'judge_ids' => $fixture['judges']->pluck('id')->all(),
        ])->assertSessionHasErrors('operation');

        $this->assertSame(1, $fixture['category']->fresh()->planned_round_count);
        $this->assertDatabaseCount('bouts', 0);
        $this->assertDatabaseCount('draws', 0);
    }

    public function test_the_calculated_plan_stays_fixed_when_winners_advance(): void
    {
        $fixture = $this->competition(3, 5, 'knockout', 0, 1);
        $this->schedule($fixture);
        $round = $fixture['category']->rounds()->where('sequence', 1)->firstOrFail();
        foreach ($round->bouts()->where('status', 'pending')->get() as $bout) {
            app(RunCompetition::class)->resolve($fixture['admin'], $fixture['tournament'], $bout, [
                'decision_type' => 'walkover', 'confirmed' => true,
                'winner_entry_id' => $bout->entries()->firstOrFail()->id, 'reason' => 'انصراف ثبت‌شدهٔ طرف مقابل',
            ]);
        }

        $this->schedule($fixture);

        $this->assertSame(3, $fixture['category']->fresh()->planned_round_count);
        $this->assertSame(3, $fixture['category']->rounds()->count());
        $this->assertSame(2, $fixture['category']->rounds()->where('sequence', 2)->firstOrFail()->bouts()->count());
    }

    public function test_team_entries_are_counted_instead_of_individual_team_members(): void
    {
        $fixture = $this->competition(3, 0, 'knockout', 0, 1);
        $fixture['category']->update(['entry_type' => 'team']);
        for ($teamNumber = 1; $teamNumber <= 3; $teamNumber++) {
            $members = array_map(fn (int $memberNumber): array => ['first_name' => 'ورزشکار '.$teamNumber.' '.$memberNumber, 'last_name' => 'آزمایشی', 'birth_date' => '2005-01-01', 'gender' => 'male'], range(1, 3));
            $entry = app(CompetitionSetup::class)->entry($fixture['admin'], $fixture['tournament'], $fixture['category'], ['members' => $members]);
            app(CompetitionSetup::class)->entryStatus($fixture['admin'], $fixture['tournament'], $entry, 'checked_in');
        }

        $this->schedule($fixture);

        $this->assertSame(2, $fixture['category']->fresh()->planned_round_count);
        $this->assertDatabaseCount('athletes', 9);
        $snapshot = app(CompetitionView::class)->snapshot($fixture['tournament']->fresh(), $fixture['admin']);
        $this->assertTrue($snapshot['categories'][0]['round_count_automatic']);
        $this->assertSame(2, $snapshot['categories'][0]['required_round_count']);
    }
}
