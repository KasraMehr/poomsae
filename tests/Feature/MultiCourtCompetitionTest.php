<?php

namespace Tests\Feature;

use App\Actions\CompetitionConsole;
use App\Actions\CompetitionDisplay;
use App\Actions\CompetitionSetup;
use App\Actions\CompetitionView;
use App\Actions\DrawRoundForms;
use App\Actions\RunCompetition;
use App\Actions\ScheduleRound;
use App\Models\Category;
use App\Models\Draw;
use App\Models\Performance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\CreatesCompetition;
use Tests\TestCase;

class MultiCourtCompetitionTest extends TestCase
{
    use CreatesCompetition, RefreshDatabase;

    /** @return array<string, mixed> */
    private function multiCourtCompetition(int $entryCount = 8, string $format = 'knockout', ?int $stageCount = null): array
    {
        $fixture = $this->competition(3, $entryCount, $format, 0, $stageCount);
        $panels = [['court_id' => $fixture['tournament']->courts()->firstOrFail()->id, 'judge_ids' => $fixture['judges']->pluck('id')->all()]];
        for ($number = 2; $number <= 4; $number++) {
            app(CompetitionSetup::class)->court($fixture['admin'], $fixture['tournament'], ['name' => 'زمین '.$number]);
            $judges = User::factory()->count(3)->create();
            foreach ($judges as $judge) {
                $fixture['tournament']->users()->attach($judge->id, ['role' => 'judge']);
            }
            $panels[] = ['court_id' => $fixture['tournament']->courts()->orderByDesc('id')->firstOrFail()->id, 'judge_ids' => $judges->pluck('id')->all()];
        }

        return [...$fixture, 'court_panels' => $panels];
    }

    /** @param array<string, mixed> $fixture */
    private function scheduleOnCourts(array $fixture, ?array $panels = null, ?Category $category = null): void
    {
        app(ScheduleRound::class)->handle($fixture['admin'], $fixture['tournament'], $category ?? $fixture['category'], ['courts' => $panels ?? $fixture['court_panels']]);
    }

    /** @param array<string, mixed> $fixture */
    private function approvePerformance(array $fixture, Performance $performance, string $presentation = '6.00'): void
    {
        $runner = app(RunCompetition::class);
        if ($performance->fresh()->status === 'pending') {
            $runner->command($fixture['admin'], $fixture['tournament'], $performance, ['command' => 'start', 'expected_version' => $performance->fresh()->version]);
        }
        if ($performance->fresh()->status === 'running') {
            $runner->command($fixture['admin'], $fixture['tournament'], $performance, ['command' => 'finish', 'expected_version' => $performance->fresh()->version]);
        }
        $judges = User::whereIn('id', $performance->bout()->firstOrFail()->judges()->pluck('user_id'))->get();
        $this->scoreAll([...$fixture, 'judges' => $judges], $performance, '2.50', $presentation);
        $runner->command($fixture['admin'], $fixture['tournament'], $performance, ['command' => 'approve', 'expected_version' => $performance->fresh()->version]);
    }

    public function test_one_category_is_distributed_across_four_courts_with_separate_panels(): void
    {
        $fixture = $this->multiCourtCompetition(16);
        $url = '/tournaments/'.$fixture['tournament']->id.'/categories/'.$fixture['category']->id.'/rounds';

        $this->actingAs($fixture['admin'])->post($url, ['courts' => $fixture['court_panels']])->assertSessionHasNoErrors();
        $round = $fixture['category']->rounds()->firstOrFail();
        $this->assertSame([2, 2, 2, 2], $round->bouts()->orderBy('sequence')->get()->groupBy('court_id')->map->count()->values()->all());
        $this->assertSame(16, $round->bouts()->with('entries')->get()->flatMap(fn ($bout) => $bout->entries->pluck('id'))->unique()->count());
        foreach ($fixture['court_panels'] as $panel) {
            foreach ($round->bouts()->where('court_id', $panel['court_id'])->get() as $bout) {
                $this->assertSame($panel['judge_ids'], $bout->judges()->orderBy('seat')->pluck('user_id')->all());
            }
        }
        $draw = Draw::where('kind', 'bracket')->sole();
        $this->assertSame($fixture['court_panels'], $draw->input_snapshot['courts']);
        $this->assertSame($round->bouts()->orderBy('sequence')->pluck('court_id')->all(), $draw->output_snapshot['court_ids']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'round.scheduled', 'subject_id' => $round->id]);
    }

    public function test_four_courts_run_the_same_knockout_category_and_merge_winners_until_the_final(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 1)->setTime(6, 0));
        $fixture = $this->multiCourtCompetition(8, 'knockout', 3);
        app(DrawRoundForms::class)->handle($fixture['admin'], $fixture['tournament'], $fixture['category']);
        $this->scheduleOnCourts($fixture);
        $runner = app(RunCompetition::class);
        $firstRound = $fixture['category']->rounds()->where('sequence', 1)->firstOrFail();
        $bouts = $firstRound->bouts()->orderBy('sequence')->get();
        foreach ($bouts->reverse() as $bout) {
            $first = $bout->performances()->orderBy('form_number')->orderBy('id')->firstOrFail();
            $this->actingAs($fixture['operator'])->post('/tournaments/'.$fixture['tournament']->id.'/performances/'.$first->id.'/command', ['command' => 'start', 'expected_version' => $first->version])->assertSessionHasNoErrors();
        }
        $console = app(CompetitionConsole::class)->snapshot($fixture['tournament'], $fixture['admin']);
        $this->assertSame(4, $console['attention']['running']);
        $this->assertTrue($console['courts']->every(fn (array $court): bool => $court['active']['category_id'] === $fixture['category']->id));
        $display = app(CompetitionDisplay::class)->snapshot($fixture['tournament'], $fixture['admin']);
        $this->assertCount(4, $display['courts']);

        foreach ($bouts as $index => $bout) {
            $winnerId = $bout->entries()->firstOrFail()->id;
            foreach ($bout->performances()->orderBy('form_number')->orderBy('id')->get() as $performance) {
                $this->approvePerformance($fixture, $performance, $performance->entry_id === $winnerId ? '6.00' : '5.00');
            }
            if ($index === 2) {
                $this->assertSame('running', $firstRound->fresh()->status);
                $this->actingAs($fixture['admin'])->post('/tournaments/'.$fixture['tournament']->id.'/categories/'.$fixture['category']->id.'/rounds', ['courts' => array_slice($fixture['court_panels'], 0, 2)])->assertSessionHasErrors('operation');
                $this->assertNull($fixture['category']->rounds()->where('sequence', 2)->firstOrFail()->scheduled_at);
            }
        }
        $this->assertSame('completed', $firstRound->fresh()->status);
        $winners = $firstRound->bouts()->orderBy('sequence')->pluck('winner_entry_id')->all();

        $this->scheduleOnCourts($fixture, array_slice($fixture['court_panels'], 0, 2));
        $semifinal = $fixture['category']->rounds()->where('sequence', 2)->firstOrFail();
        $semifinalBouts = $semifinal->bouts()->orderBy('sequence')->get();
        $this->assertSame(2, $semifinalBouts->pluck('court_id')->unique()->count());
        $this->assertSame(array_chunk($winners, 2), $semifinalBouts->map(fn ($bout): array => $bout->entries()->orderByPivot('side')->pluck('entries.id')->all())->all());
        foreach ($semifinalBouts as $bout) {
            $winnerId = $bout->entries()->firstOrFail()->id;
            foreach ($bout->performances()->orderBy('form_number')->orderBy('id')->get() as $performance) {
                $this->approvePerformance($fixture, $performance, $performance->entry_id === $winnerId ? '6.00' : '5.00');
            }
        }

        $this->scheduleOnCourts($fixture, [$fixture['court_panels'][0]]);
        $final = $fixture['category']->rounds()->where('sequence', 3)->firstOrFail()->bouts()->sole();
        $this->assertSame($fixture['court_panels'][0]['court_id'], $final->court_id);
        $championId = $final->entries()->firstOrFail()->id;
        foreach ($final->performances()->orderBy('form_number')->orderBy('id')->get() as $performance) {
            $this->approvePerformance($fixture, $performance, $performance->entry_id === $championId ? '6.00' : '5.00');
        }
        $runner->complete($fixture['admin'], $fixture['tournament']);
        $snapshot = app(CompetitionView::class)->snapshot($fixture['tournament'], $fixture['admin']);
        $this->assertSame($championId, $snapshot['categories'][0]['champion_id']);
        $this->assertSame('completed', $fixture['tournament']->fresh()->status);
        $this->assertCount(3, Draw::where('kind', 'bracket')->get());
    }

    #[TestWith(['consecutive'])]
    #[TestWith(['phased'])]
    public function test_score_based_court_queues_are_independent_and_share_one_ranking(string $performanceOrder): void
    {
        $fixture = $this->multiCourtCompetition(8, 'round_robin');
        $fixture['category']->update(['performance_order' => $performanceOrder]);
        $this->scheduleOnCourts($fixture);
        $round = $fixture['category']->rounds()->firstOrFail();
        $runner = app(RunCompetition::class);
        $courts = app(CompetitionConsole::class)->snapshot($fixture['tournament'], $fixture['admin'])['courts'];
        foreach ($courts->reverse() as $court) {
            $performance = Performance::findOrFail($court['queue'][0]['id']);
            $runner->command($fixture['admin'], $fixture['tournament'], $performance, ['command' => 'start', 'expected_version' => $performance->version]);
        }
        $this->assertSame(4, app(CompetitionConsole::class)->snapshot($fixture['tournament'], $fixture['admin'])['attention']['running']);
        foreach (array_reverse($fixture['court_panels']) as $panel) {
            $performances = $round->bouts()->where('court_id', $panel['court_id'])->with('performances')->orderBy('sequence')->get()->flatMap->performances;
            $performances = $performanceOrder === 'phased'
                ? $performances->sortBy(fn ($performance): string => sprintf('%02d-%010d', $performance->form_number, $performance->id))
                : $performances->sortBy('id');
            foreach ($performances as $performance) {
                $this->approvePerformance($fixture, $performance);
            }
        }
        $this->assertSame('completed', $round->fresh()->status);
        $standings = app(CompetitionView::class)->snapshot($fixture['tournament'], $fixture['admin'])['categories'][0]['standings'];
        $this->assertCount(8, $standings);
        $this->assertTrue(collect($standings)->every(fn (array $entry): bool => $entry['score'] === '17.000000' && $entry['rank'] === 1));
    }

    public function test_four_different_categories_can_run_at_the_same_time(): void
    {
        $fixture = $this->multiCourtCompetition(2);
        $categories = [$fixture['category']];
        for ($number = 2; $number <= 4; $number++) {
            $category = app(CompetitionSetup::class)->category($fixture['admin'], $fixture['tournament'], [
                'name' => 'رده '.$number, 'gender' => 'male', 'minimum_age' => 10, 'maximum_age' => 40,
                'format' => 'knockout', 'judge_count' => 3, 'discard_each_end' => 0, 'form_names' => ['فرم اول', 'فرم دوم'],
            ]);
            foreach ([1, 2] as $entryNumber) {
                $entry = app(CompetitionSetup::class)->entry($fixture['admin'], $fixture['tournament'], $category, ['first_name' => 'ورزشکار'.$number.$entryNumber, 'last_name' => 'آزمایشی', 'birth_date' => '2005-01-01', 'gender' => 'male']);
                app(CompetitionSetup::class)->entryStatus($fixture['admin'], $fixture['tournament'], $entry, 'checked_in');
            }
            $categories[] = $category;
        }
        foreach ($categories as $index => $category) {
            $this->scheduleOnCourts($fixture, [$fixture['court_panels'][$index]], $category);
            $performance = $category->rounds()->firstOrFail()->bouts()->firstOrFail()->performances()->orderBy('id')->firstOrFail();
            $this->actingAs($fixture['operator'])->post('/tournaments/'.$fixture['tournament']->id.'/performances/'.$performance->id.'/command', ['command' => 'start', 'expected_version' => $performance->version])->assertSessionHasNoErrors();
        }
        $console = app(CompetitionConsole::class)->snapshot($fixture['tournament'], $fixture['admin']);
        $this->assertSame(4, $console['attention']['running']);
        $this->assertSame(4, $console['courts']->pluck('active.category_id')->unique()->count());
    }

    public function test_four_double_knockout_courts_start_and_finish_their_own_pair_together(): void
    {
        $fixture = $this->multiCourtCompetition();
        $fixture['category']->update(['execution_mode' => 'simultaneous']);
        $this->scheduleOnCourts($fixture);
        $runner = app(RunCompetition::class);
        $courts = app(CompetitionConsole::class)->snapshot($fixture['tournament'], $fixture['admin'])['courts'];
        foreach ($courts as $court) {
            $performance = Performance::findOrFail($court['queue'][0]['id']);
            $runner->command($fixture['admin'], $fixture['tournament'], $performance, ['command' => 'start', 'expected_version' => $performance->version]);
        }
        $this->assertSame(8, Performance::where('status', 'running')->count());
        $judgeView = app(CompetitionView::class)->snapshot($fixture['tournament'], $fixture['judges'][0]);
        $firstBout = collect($judgeView['categories'][0]['rounds'][0]['bouts'])
            ->firstWhere('court_id', $fixture['court_panels'][0]['court_id']);
        $this->assertSame('simultaneous', $judgeView['categories'][0]['execution_mode']);
        $this->assertSame(['chung', 'hong'], collect($firstBout['entries'])->pluck('side')->sort()->values()->all());
        $this->assertCount(2, collect($firstBout['performances'])->filter(fn (array $performance): bool => $performance['status'] === 'running' && $performance['is_assigned'] && $performance['started_at'] !== null));
        $this->assertSame(4, collect(app(CompetitionDisplay::class)->snapshot($fixture['tournament'], $fixture['admin'])['courts'])->filter(fn (array $court): bool => $court['current'] !== null)->count());

        foreach ($courts as $court) {
            $performance = Performance::findOrFail($court['queue'][0]['id']);
            $runner->command($fixture['admin'], $fixture['tournament'], $performance, ['command' => 'finish', 'expected_version' => $performance->version]);
        }
        $this->assertSame(8, Performance::where('status', 'scoring')->count());
        $this->assertSame(0, Performance::where('status', 'running')->count());
    }

    #[TestWith(['individual', 1])]
    #[TestWith(['pair', 2])]
    public function test_freestyle_entries_run_with_music_on_four_independent_courts(string $entryType, int $memberCount): void
    {
        $fixture = $this->multiCourtCompetition(0, 'round_robin');
        $setup = app(CompetitionSetup::class);
        $category = $setup->category($fixture['admin'], $fixture['tournament'], [
            'name' => 'ابداعی', 'discipline' => 'freestyle', 'entry_type' => $entryType, 'gender' => 'male',
            'format' => 'round_robin', 'judge_count' => 3, 'discard_each_end' => 0, 'accuracy_max' => 600,
        ], $fixture['category']);
        Storage::fake('local');
        for ($number = 1; $number <= 4; $number++) {
            $members = array_map(fn (int $member): array => ['first_name' => 'ورزشکار'.$number.$member, 'last_name' => 'آزمایشی', 'birth_date' => '2005-01-01', 'gender' => 'male'], range(1, $memberCount));
            $entry = $setup->entry($fixture['admin'], $fixture['tournament'], $category, [
                ...($memberCount === 1 ? $members[0] : ['members' => $members]),
                'music' => UploadedFile::fake()->create('track.mp3', 100, 'audio/mpeg'),
            ]);
            $setup->entryStatus($fixture['admin'], $fixture['tournament'], $entry, 'checked_in');
            Storage::disk('local')->assertExists($entry->music_path);
        }
        $this->scheduleOnCourts($fixture, category: $category);
        $courts = app(CompetitionConsole::class)->snapshot($fixture['tournament'], $fixture['admin'])['courts'];
        foreach ($courts->reverse() as $court) {
            $performance = Performance::findOrFail($court['queue'][0]['id']);
            app(RunCompetition::class)->command($fixture['admin'], $fixture['tournament'], $performance, ['command' => 'start', 'expected_version' => $performance->version]);
        }
        $this->assertSame(4, Performance::where('status', 'running')->count());
        $this->assertSame(0, Performance::whereNull('music_path')->count());
        $this->assertDatabaseCount('performances', 4);
    }

    #[TestWith(['empty', 'courts'])]
    #[TestWith(['five', 'courts'])]
    #[TestWith(['duplicate_court', 'courts.1.court_id'])]
    #[TestWith(['shared_judge', 'courts.1.judge_ids.0'])]
    #[TestWith(['foreign_court', 'operation'])]
    #[TestWith(['inactive_judge', 'operation'])]
    #[TestWith(['missing_judge', 'operation'])]
    #[TestWith(['mixed_input', 'court_id'])]
    public function test_invalid_court_panels_are_rejected_without_creating_a_partial_draw(string $case, string $errorField): void
    {
        $fixture = $this->multiCourtCompetition();
        $panels = $fixture['court_panels'];
        if ($case === 'empty') {
            $panels = [];
        } elseif ($case === 'five') {
            $panels[] = $panels[0];
        } elseif ($case === 'duplicate_court') {
            $panels[1]['court_id'] = $panels[0]['court_id'];
        } elseif ($case === 'shared_judge') {
            $panels[1]['judge_ids'][0] = $panels[0]['judge_ids'][0];
        } elseif ($case === 'foreign_court') {
            $other = $this->competition(3, 2, 'knockout', 0);
            $panels[0]['court_id'] = $other['tournament']->courts()->firstOrFail()->id;
        } elseif ($case === 'inactive_judge') {
            User::findOrFail($panels[1]['judge_ids'][0])->forceFill(['is_active' => false])->save();
        } elseif ($case === 'missing_judge') {
            array_pop($panels[1]['judge_ids']);
        }
        $data = ['courts' => $panels];
        if ($case === 'mixed_input') {
            $data = [...$data, ...$panels[0]];
        }

        $this->actingAs($fixture['admin'])->post('/tournaments/'.$fixture['tournament']->id.'/categories/'.$fixture['category']->id.'/rounds', $data)->assertSessionHasErrors($errorField);

        $this->assertSame(0, $fixture['category']->rounds()->count());
        $this->assertDatabaseCount('draws', 0);
        $this->assertDatabaseCount('bouts', 0);
    }

    public function test_manager_permission_is_required_for_multi_court_scheduling(): void
    {
        $fixture = $this->multiCourtCompetition();

        $this->actingAs($fixture['judges'][0])->post('/tournaments/'.$fixture['tournament']->id.'/categories/'.$fixture['category']->id.'/rounds', ['courts' => $fixture['court_panels']])->assertForbidden();

        $this->assertDatabaseCount('draws', 0);
    }

    public function test_management_snapshot_can_assign_one_stage_to_four_courts(): void
    {
        $fixture = $this->multiCourtCompetition(0, 'knockout', 3);
        Sanctum::actingAs($fixture['admin'], ['competition:manage']);

        $this->putJson('/api/v1/tournaments/'.$fixture['tournament']->id.'/categories/'.$fixture['category']->id.'/management-snapshot', [
            'source' => 'management', 'version' => 1, 'expected_version' => 0, 'draw_method' => 'random',
            'entries' => array_map(fn (int $number): array => [
                'external_id' => 'athlete-'.$number, 'status' => 'checked_in',
                'members' => [['first_name' => 'ورزشکار'.$number, 'last_name' => 'دریافتی', 'birth_date' => '2005-01-01', 'gender' => 'male']],
            ], range(1, 8)),
            'stages' => [['sequence' => 1, 'form_ids' => [], 'courts' => $fixture['court_panels'], 'bouts' => [['athlete-1', 'athlete-2'], ['athlete-3', 'athlete-4'], ['athlete-5', 'athlete-6'], ['athlete-7', 'athlete-8']]]],
        ])->assertOk()->assertJsonPath('data.version', 1);

        $round = $fixture['category']->rounds()->where('sequence', 1)->firstOrFail();
        $this->assertSame(array_column($fixture['court_panels'], 'court_id'), $round->bouts()->orderBy('sequence')->pluck('court_id')->all());
        $this->assertSame($fixture['court_panels'], $round->source_snapshot['courts']);
        $this->assertDatabaseCount('entries', 8);
        $this->assertDatabaseCount('performances', 16);
    }
}
