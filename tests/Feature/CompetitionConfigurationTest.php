<?php

namespace Tests\Feature;

use App\Actions\RunCompetition;
use App\Models\Bout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\CreatesCompetition;
use Tests\TestCase;

class CompetitionConfigurationTest extends TestCase
{
    use CreatesCompetition, RefreshDatabase;

    private function categoryData(): array
    {
        return [
            'name' => 'رده جدید', 'gender' => 'male', 'format' => 'knockout',
            'judge_count' => 3, 'discard_each_end' => 0, 'rules_acknowledged' => true,
            'planned_round_count' => 7, 'allow_form_repetition' => true,
            'form_names' => array_map(fn (int $number): string => 'پومسه '.$number, range(1, 8)),
        ];
    }

    public function test_three_judge_category_creates_numbered_stages_without_custom_names(): void
    {
        $fixture = $this->competition();
        $data = [...$this->categoryData(), 'stage_names' => ['نام دلخواه']];

        $this->actingAs($fixture['admin'])->post('/tournaments/'.$fixture['tournament']->id.'/categories', $data)
            ->assertSessionHasNoErrors()->assertRedirect();

        $category = $fixture['tournament']->categories()->where('name', $data['name'])->firstOrFail();
        $this->assertSame(3, $category->judge_count);
        $this->assertSame(0, $category->scoringRuleSet->definition['discard_each_end']);
        $this->assertSame(['مرحله 1', 'مرحله 2', 'مرحله 3', 'مرحله 4', 'مرحله 5', 'مرحله 6', 'مرحله 7'], $category->rounds()->orderBy('sequence')->pluck('name')->all());
    }

    #[TestWith(['male'])]
    #[TestWith(['female'])]
    public function test_categories_accept_only_the_selected_male_or_female_gender(string $gender): void
    {
        $fixture = $this->competition();
        $data = [...$this->categoryData(), 'gender' => $gender];

        $this->actingAs($fixture['admin'])->post('/tournaments/'.$fixture['tournament']->id.'/categories', $data)
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('categories', ['tournament_id' => $fixture['tournament']->id, 'name' => $data['name'], 'gender' => $gender]);
    }

    #[TestWith(['open', 'individual'])]
    #[TestWith(['mixed', 'team'])]
    public function test_category_rejects_open_and_mixed_gender(string $gender, string $entryType): void
    {
        $fixture = $this->competition();

        $this->actingAs($fixture['admin'])->post('/tournaments/'.$fixture['tournament']->id.'/categories', [
            ...$this->categoryData(), 'gender' => $gender, 'entry_type' => $entryType,
        ])->assertSessionHasErrors('gender');

        $this->assertDatabaseCount('categories', 1);
    }

    public function test_three_judge_panel_rejects_discarding_scores(): void
    {
        $fixture = $this->competition();

        $this->actingAs($fixture['admin'])->post('/tournaments/'.$fixture['tournament']->id.'/categories', [
            ...$this->categoryData(), 'discard_each_end' => 1,
        ])->assertSessionHasErrors('discard_each_end');

        $this->assertDatabaseCount('categories', 1);
    }

    public function test_three_judge_scoring_requires_all_scores_and_completes_the_competition(): void
    {
        $fixture = $this->competition(3, 2, 'knockout', 0);
        $this->schedule($fixture);
        $bout = Bout::firstOrFail();
        $winner = $bout->entries()->firstOrFail()->id;
        $run = app(RunCompetition::class);
        foreach ($bout->performances()->orderBy('form_number')->orderBy('id')->get() as $performance) {
            $this->startScoring($fixture, $performance);
            $this->actingAs($fixture['admin'])->post('/tournaments/'.$fixture['tournament']->id.'/performances/'.$performance->id.'/command', [
                'command' => 'approve', 'expected_version' => $performance->fresh()->version,
            ])->assertSessionHasErrors('operation');
            $this->scoreAll($fixture, $performance, '2.50', $performance->entry_id === $winner ? '6.00' : '5.00');
            $run->command($fixture['admin'], $fixture['tournament'], $performance, ['command' => 'approve', 'expected_version' => $performance->fresh()->version]);
        }
        $run->complete($fixture['admin'], $fixture['tournament']);

        $this->assertSame($winner, $bout->fresh()->winner_entry_id);
        $this->assertSame('completed', $fixture['tournament']->fresh()->status);
        $this->assertDatabaseCount('score_sheets', 12);
        $this->assertDatabaseCount('results', 4);
    }

    public function test_knockout_schedules_more_than_sixty_four_entries_with_byes_and_advances(): void
    {
        $fixture = $this->competition(3, 65, 'knockout', 0, 7);

        $this->actingAs($fixture['admin'])->post('/tournaments/'.$fixture['tournament']->id.'/categories/'.$fixture['category']->id.'/rounds', [
            'court_id' => $fixture['tournament']->courts()->firstOrFail()->id,
            'judge_ids' => $fixture['judges']->pluck('id')->all(),
        ])->assertSessionHasNoErrors();

        $round = $fixture['category']->rounds()->where('sequence', 1)->firstOrFail();
        $this->assertSame(64, $round->bouts()->count());
        $this->assertSame(63, $round->bouts()->where('status', 'completed')->count());
        $bout = $round->bouts()->where('status', 'pending')->firstOrFail();
        $this->post('/tournaments/'.$fixture['tournament']->id.'/bouts/'.$bout->id.'/resolve', [
            'decision_type' => 'walkover', 'confirmed' => true,
            'winner_entry_id' => $bout->entries()->firstOrFail()->id, 'reason' => 'انصراف ثبت شده ورزشکار',
        ])->assertSessionHasNoErrors();
        $this->schedule($fixture);

        $this->assertSame(32, $fixture['category']->rounds()->where('sequence', 2)->firstOrFail()->bouts()->count());
    }

    public function test_round_robin_schedules_more_than_sixteen_entries(): void
    {
        $fixture = $this->competition(3, 33, 'round_robin', 0, 1);

        $this->actingAs($fixture['admin'])->post('/tournaments/'.$fixture['tournament']->id.'/categories/'.$fixture['category']->id.'/rounds', [
            'court_id' => $fixture['tournament']->courts()->firstOrFail()->id,
            'judge_ids' => $fixture['judges']->pluck('id')->all(),
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseCount('bouts', 33);
        $this->assertDatabaseCount('performances', 66);
        $this->assertDatabaseCount('judge_assignments', 99);
    }

    #[TestWith(['knockout', 7, 65, 64])]
    #[TestWith(['round_robin', 1, 65, 65])]
    public function test_management_import_accepts_large_competitions(string $format, int $stageCount, int $entryCount, int $boutCount): void
    {
        $fixture = $this->competition(3, 0, $format, 0, $stageCount);
        $entries = array_map(fn (int $number): array => [
            'external_id' => 'athlete-'.$number, 'status' => 'checked_in',
            'members' => [['first_name' => 'بازیکن'.$number, 'last_name' => 'آزمایشی', 'birth_date' => '2005-01-01', 'gender' => 'male']],
        ], range(1, $entryCount));
        $pairs = array_map(fn (int $number): array => ['athlete-'.$number], range(1, 63));
        $pairs[] = ['athlete-64', 'athlete-65'];
        $stages = array_map(fn (int $sequence): array => [
            'sequence' => $sequence, 'form_ids' => [],
            'court_id' => $fixture['tournament']->courts()->firstOrFail()->id,
            'judge_ids' => $fixture['judges']->pluck('id')->all(), 'bouts' => [],
        ], range(1, $stageCount));
        $stages[0]['bouts'] = $format === 'knockout' ? $pairs : array_map(fn (array $entry): array => [$entry['external_id']], $entries);
        Sanctum::actingAs($fixture['admin'], ['competition:manage']);

        $this->putJson('/api/v1/tournaments/'.$fixture['tournament']->id.'/categories/'.$fixture['category']->id.'/management-snapshot', [
            'source' => 'management', 'version' => 1, 'expected_version' => 0,
            'draw_method' => 'random', 'entries' => $entries, 'stages' => $stages,
        ])->assertOk()->assertJsonPath('data.version', 1);

        $this->assertDatabaseCount('entries', 65);
        $this->assertDatabaseCount('bouts', $boutCount);
        $this->assertSame('مرحله 1', $fixture['category']->rounds()->firstOrFail()->name);
    }
}
