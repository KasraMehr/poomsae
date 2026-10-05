<?php

namespace Tests\Feature;

use App\Actions\CompetitionSetup;
use App\Actions\CompetitionView;
use App\Actions\RunCompetition;
use App\Models\Performance;
use App\Models\ScoreSheet;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\CreatesCompetition;
use Tests\TestCase;

class FreestyleDetailedScoringTest extends TestCase
{
    use CreatesCompetition, RefreshDatabase;

    private function freestyle(): array
    {
        Storage::fake('local');
        $fixture = $this->competition();
        $setup = app(CompetitionSetup::class);
        $setup->category($fixture['admin'], $fixture['tournament'], [
            'name' => 'Freestyle', 'discipline' => 'freestyle', 'entry_type' => 'individual',
            'gender' => 'open', 'minimum_age' => 10, 'maximum_age' => 40, 'format' => 'knockout',
            'judge_count' => 5, 'accuracy_max' => 600, 'discard_each_end' => 1, 'rules_acknowledged' => true,
        ]);
        $fixture['category'] = $fixture['tournament']->categories()->where('discipline', 'freestyle')->firstOrFail();
        foreach (range(1, 2) as $index) {
            $setup->entry($fixture['admin'], $fixture['tournament'], $fixture['category'], [
                'first_name' => 'Athlete '.$index, 'last_name' => 'Test', 'birth_date' => '2005-01-01',
                'gender' => 'male', 'music' => UploadedFile::fake()->create('track.mp3', 10, 'audio/mpeg'),
            ]);
        }
        foreach ($fixture['category']->entries()->get() as $entry) {
            $setup->entryStatus($fixture['admin'], $fixture['tournament'], $entry, 'checked_in');
        }
        $this->schedule($fixture);
        $fixture['performance'] = Performance::query()->orderBy('id')->firstOrFail();

        return $fixture;
    }

    private function payload(Performance $performance): array
    {
        return [
            'request_id' => (string) Str::uuid(), 'expected_version' => $performance->fresh()->version,
            'expected_revision' => 0, 'accuracy' => '5.50', 'presentation' => '3.60',
            'accuracy_components' => ['2.00', '1.75', '1.75'], 'presentation_penalties' => ['0.30', '0.10'],
        ];
    }

    private function migration(): Migration
    {
        return require database_path('migrations/2026_10_05_120037_upgrade_unstarted_freestyle_scoring_rules.php');
    }

    private function legacyRule(array $fixture): void
    {
        $rule = $fixture['category']->scoringRuleSet;
        $definition = $rule->definition;
        unset($definition['accuracy_component_max'], $definition['presentation_deduction_options']);
        $rule->update(['definition' => [...$definition, 'accuracy_max' => 1000, 'presentation_max' => 0, 'input_method' => 'single_score_v1']]);
    }

    public function test_mobile_panel_uses_six_accuracy_plus_four_presentation_and_publishes_both(): void
    {
        $fixture = $this->freestyle();
        $performance = $fixture['performance'];
        $definition = $fixture['category']->scoringRuleSet->definition;
        $this->assertSame(600, $definition['accuracy_max']);
        $this->assertSame(400, $definition['presentation_max']);
        $this->assertSame(200, $definition['accuracy_component_max']);
        $this->assertSame([10, 30], $definition['presentation_deduction_options']);
        $this->startScoring($fixture, $performance);
        foreach ($fixture['judges'] as $judge) {
            Sanctum::actingAs($judge, ['scores:write']);
            $this->postJson('/api/v1/tournaments/'.$fixture['tournament']->id.'/performances/'.$performance->id.'/scores', $this->payload($performance))->assertOk();
        }
        $sheet = ScoreSheet::query()->firstOrFail();
        $this->assertSame([200, 175, 175], $sheet->breakdown['accuracy_components_hundredths']);
        $this->assertSame([30, 10], $sheet->breakdown['presentation_penalties_hundredths']);
        $public = app(CompetitionView::class)->snapshot($fixture['tournament'], $fixture['admin'], true);
        $category = collect($public['categories'])->firstWhere('id', $fixture['category']->id);
        $preview = collect(collect(collect($category['rounds'])->first()['bouts'])->first()['performances'])->first();
        $this->assertSame('9.100000', $preview['preview_result']);
        $this->assertNull($preview['result']);
        $this->assertSame([], $preview['scores']);

        app(RunCompetition::class)->command($fixture['admin'], $fixture['tournament'], $performance, ['command' => 'approve', 'expected_version' => $performance->fresh()->version]);
        $result = $performance->fresh()->result;
        $this->assertSame('9.100000', $result->score);
        $this->assertSame([550, 550, 550], $result->calculation_snapshot['components']['accuracy']['kept_hundredths']);
        $this->assertSame([360, 360, 360], $result->calculation_snapshot['components']['presentation']['kept_hundredths']);
    }

    public function test_invalid_caps_components_penalties_and_totals_are_rejected(): void
    {
        $fixture = $this->freestyle();
        $performance = $fixture['performance'];
        $this->startScoring($fixture, $performance);
        $url = route('judging.store', [$fixture['tournament'], $performance]);
        $payload = $this->payload($performance);
        $this->actingAs($fixture['judges'][0]);
        foreach ([
            ['accuracy' => '5.60'], ['presentation' => '3.70'],
            ['accuracy_components' => ['2.01', '1.74', '1.75']],
            ['accuracy_components' => ['-0.10', '2.00', '2.00']],
            ['presentation_penalties' => ['0.20']],
            ['presentation' => '0.00', 'presentation_penalties' => array_fill(0, 14, '0.30')],
            ['presentation' => '4.10', 'presentation_penalties' => []],
            ['accuracy_components' => ['2.00', '2.00']],
            ['presentation_components' => ['2.00', '2.00', '2.00']],
        ] as $invalid) {
            $this->postJson($url, [...$payload, ...$invalid])->assertUnprocessable();
        }
        $this->postJson($url, array_diff_key($payload, array_flip(['accuracy_components', 'presentation_penalties'])))->assertUnprocessable();
        $this->assertDatabaseCount('score_sheets', 0);
    }

    public function test_zero_deductions_allow_full_four_and_correction_restores_a_tenth(): void
    {
        $fixture = $this->freestyle();
        $performance = $fixture['performance'];
        $this->startScoring($fixture, $performance);
        $url = route('judging.store', [$fixture['tournament'], $performance]);
        $this->actingAs($fixture['judges'][0])->post($url, $this->payload($performance))->assertSessionHasNoErrors();
        $this->post($url, [...$this->payload($performance), 'expected_revision' => 1, 'presentation' => '3.70', 'presentation_penalties' => ['0.30'], 'reason' => 'Restore incorrect deduction'])->assertSessionHasNoErrors();
        $this->post($url, [...$this->payload($performance), 'expected_revision' => 2, 'presentation' => '4.00', 'presentation_penalties' => [], 'reason' => 'Restore remaining deduction'])->assertSessionHasNoErrors();
        $sheet = ScoreSheet::query()->firstOrFail();
        $this->assertSame(3, $sheet->revision);
        $this->assertSame([], $sheet->breakdown['presentation_penalties_hundredths']);
        $this->assertDatabaseHas('score_components', ['score_sheet_id' => $sheet->id, 'criterion' => 'presentation', 'value_hundredths' => 400]);
        $this->assertDatabaseCount('score_revisions', 3);
    }

    public function test_request_replay_detects_changed_component_distribution_even_when_total_matches(): void
    {
        $fixture = $this->freestyle();
        $performance = $fixture['performance'];
        $this->startScoring($fixture, $performance);
        $url = route('judging.store', [$fixture['tournament'], $performance]);
        $payload = $this->payload($performance);
        $this->actingAs($fixture['judges'][0])->postJson($url, $payload)->assertRedirect();
        $this->postJson($url, $payload)->assertRedirect();
        $this->postJson($url, [...$payload, 'accuracy_components' => ['1.90', '1.90', '1.70']])->assertConflict();
        $this->assertDatabaseCount('score_sheets', 1);
        $this->assertDatabaseCount('score_revisions', 1);
    }

    public function test_proxy_score_keeps_both_criteria_for_confirmation(): void
    {
        $fixture = $this->freestyle();
        $performance = $fixture['performance'];
        $this->startScoring($fixture, $performance);
        $assignment = $performance->bout->judges()->orderBy('seat')->firstOrFail();
        $this->actingAs($fixture['admin'])->post(route('operations.proxy-score', [$fixture['tournament'], $performance]), [
            ...$this->payload($performance), 'judge_assignment_id' => $assignment->id, 'reason' => 'Judge tablet disconnected',
        ])->assertSessionHasNoErrors();
        $sheet = ScoreSheet::query()->firstOrFail();
        $this->assertSame('draft', $sheet->status);
        $this->assertSame([200, 175, 175], $sheet->breakdown['accuracy_components_hundredths']);
        $this->assertSame([30, 10], $sheet->breakdown['presentation_penalties_hundredths']);
    }

    public function test_upgrade_versions_only_unstarted_categories_and_invalidates_old_drafts(): void
    {
        $fixture = $this->freestyle();
        $this->legacyRule($fixture);
        $oldRule = $fixture['category']->scoringRuleSet;
        $performanceVersion = $fixture['performance']->version;
        $this->migration()->up();
        $category = $fixture['category']->fresh();
        $this->assertNotSame($oldRule->id, $category->scoring_rule_set_id);
        $this->assertSame('single_score_v1', $oldRule->fresh()->definition['input_method']);
        $this->assertSame('components_and_deductions_v1', $category->scoringRuleSet->definition['input_method']);
        $this->assertSame($oldRule->version + 1, $category->scoringRuleSet->version);
        $this->assertSame($performanceVersion + 1, $fixture['performance']->fresh()->version);
        $this->assertDatabaseHas('audit_logs', ['action' => 'category.scoring_upgraded', 'subject_id' => $category->id]);
        $this->migration()->up();
        $this->assertSame($category->scoring_rule_set_id, $category->fresh()->scoring_rule_set_id);
    }

    public function test_upgrade_preserves_started_legacy_category_and_its_single_scores(): void
    {
        $fixture = $this->freestyle();
        $this->legacyRule($fixture);
        $performance = $fixture['performance'];
        $this->startScoring($fixture, $performance);
        $this->actingAs($fixture['judges'][0])->post(route('judging.store', [$fixture['tournament'], $performance]), [
            'request_id' => (string) Str::uuid(), 'expected_version' => $performance->fresh()->version,
            'expected_revision' => 0, 'score' => '9.00',
        ])->assertSessionHasNoErrors();
        $this->migration()->up();
        $this->assertSame($fixture['category']->scoring_rule_set_id, $fixture['category']->fresh()->scoring_rule_set_id);
        $this->assertSame('single_score_v1', ScoreSheet::query()->firstOrFail()->breakdown['method']);
        $this->assertDatabaseHas('score_components', ['criterion' => 'accuracy', 'value_hundredths' => 900]);
        $this->assertDatabaseHas('score_components', ['criterion' => 'presentation', 'value_hundredths' => 0]);
    }
}
