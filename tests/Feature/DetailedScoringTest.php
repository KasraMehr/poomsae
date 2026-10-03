<?php

namespace Tests\Feature;

use App\Actions\CompetitionView;
use App\Models\Performance;
use App\Models\ScoreSheet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\CreatesCompetition;
use Tests\TestCase;

class DetailedScoringTest extends TestCase
{
    use CreatesCompetition, RefreshDatabase;

    public function test_new_category_uses_fixed_four_plus_six_rule_and_rejects_other_split(): void
    {
        $fixture = $this->competition();
        $definition = $fixture['category']->scoringRuleSet->definition;
        $this->assertSame(400, $definition['accuracy_max']);
        $this->assertSame(600, $definition['presentation_max']);
        $this->assertSame([10, 30], $definition['accuracy_deduction_options']);
        $this->assertSame(200, $definition['presentation_component_max']);
        $this->assertSame('deductions_and_components_v1', $definition['input_method']);

        $this->actingAs($fixture['admin'])->post(route('operations.category', $fixture['tournament']), [
            'name' => 'رده با قانون نادرست', 'gender' => 'open', 'format' => 'knockout',
            'judge_count' => 5, 'accuracy_max' => 300, 'discard_each_end' => 1,
            'rules_acknowledged' => true, 'form_names' => ['فرم سوم', 'فرم چهارم'],
        ])->assertSessionHasErrors('accuracy_max');
    }

    public function test_detailed_judge_score_and_correction_preserve_raw_breakdown_and_history(): void
    {
        $fixture = $this->competition();
        $this->schedule($fixture);
        $performance = Performance::query()->orderBy('id')->firstOrFail();
        $this->startScoring($fixture, $performance);
        $judge = $fixture['judges'][0];
        $url = route('judging.store', [$fixture['tournament'], $performance]);

        $this->actingAs($judge)->post($url, [
            'request_id' => (string) Str::uuid(), 'expected_version' => $performance->fresh()->version,
            'expected_revision' => 0, 'accuracy' => '3.60', 'presentation' => '5.25',
            'accuracy_penalties' => ['0.30', '0.10'],
            'presentation_components' => ['1.50', '1.75', '2.00'],
        ])->assertSessionHasNoErrors();

        $sheet = ScoreSheet::query()->firstOrFail();
        $this->assertSame('deductions_and_components_v1', $sheet->breakdown['method']);
        $this->assertSame([30, 10], $sheet->breakdown['accuracy_penalties_hundredths']);
        $this->assertSame([150, 175, 200], $sheet->breakdown['presentation_components_hundredths']);
        $this->assertDatabaseHas('score_components', ['score_sheet_id' => $sheet->id, 'criterion' => 'accuracy', 'value_hundredths' => 360]);
        $this->assertDatabaseHas('score_components', ['score_sheet_id' => $sheet->id, 'criterion' => 'presentation', 'value_hundredths' => 525]);

        $this->post($url, [
            'request_id' => (string) Str::uuid(), 'expected_version' => $performance->fresh()->version,
            'expected_revision' => 1, 'accuracy' => '3.70', 'presentation' => '5.25',
            'accuracy_penalties' => ['0.30'],
            'presentation_components' => ['1.50', '1.75', '2.00'],
            'reason' => 'اصلاح کسر اشتباه',
        ])->assertSessionHasNoErrors();

        $this->assertSame(2, $sheet->fresh()->revision);
        $this->assertDatabaseHas('score_revisions', ['score_sheet_id' => $sheet->id, 'revision' => 2, 'changed_by' => $judge->id, 'reason' => 'اصلاح کسر اشتباه']);
        $snapshot = app(CompetitionView::class)->snapshot($fixture['tournament'], $fixture['admin']);
        $score = $snapshot['categories'][0]['rounds'][0]['bouts'][0]['performances'][0]['scores'][0];
        $this->assertCount(2, $score['history']);
        $this->assertSame([30, 10], $score['history'][0]['snapshot']['breakdown']['accuracy_penalties_hundredths']);
        $this->assertSame([30], $score['history'][1]['snapshot']['breakdown']['accuracy_penalties_hundredths']);
        $this->assertNotNull($score['history'][1]['created_at']);

        $public = app(CompetitionView::class)->snapshot($fixture['tournament'], $fixture['admin'], true);
        $this->assertSame([], $public['categories'][0]['rounds'][0]['bouts'][0]['performances'][0]['scores']);
    }

    public function test_detailed_score_rejects_mismatched_total_and_component_above_two(): void
    {
        $fixture = $this->competition();
        $this->schedule($fixture);
        $performance = Performance::query()->orderBy('id')->firstOrFail();
        $this->startScoring($fixture, $performance);
        $url = route('judging.store', [$fixture['tournament'], $performance]);
        $payload = [
            'request_id' => (string) Str::uuid(), 'expected_version' => $performance->fresh()->version,
            'expected_revision' => 0, 'accuracy' => '3.60', 'presentation' => '5.25',
            'accuracy_penalties' => ['0.30', '0.10'],
            'presentation_components' => ['1.50', '1.75', '2.00'],
        ];

        $this->actingAs($fixture['judges'][0])->post($url, array_diff_key($payload, array_flip(['accuracy_penalties', 'presentation_components'])))->assertSessionHasErrors('operation');
        $this->actingAs($fixture['judges'][0])->post($url, [...$payload, 'accuracy' => '3.70'])->assertSessionHasErrors('operation');
        $this->post($url, [...$payload, 'request_id' => (string) Str::uuid(), 'presentation_components' => ['2.01', '1.24', '2.00']])->assertSessionHasErrors('operation');
        $this->assertDatabaseCount('score_sheets', 0);
    }

    public function test_operator_proxy_score_records_the_same_detailed_inputs_for_review(): void
    {
        $fixture = $this->competition();
        $this->schedule($fixture);
        $performance = Performance::query()->orderBy('id')->firstOrFail();
        $this->startScoring($fixture, $performance);
        $assignment = $performance->bout->judges()->orderBy('seat')->firstOrFail();

        $this->actingAs($fixture['admin'])->post(route('operations.proxy-score', [$fixture['tournament'], $performance]), [
            'request_id' => (string) Str::uuid(), 'judge_assignment_id' => $assignment->id,
            'expected_version' => $performance->fresh()->version, 'expected_revision' => 0,
            'accuracy' => '4.00', 'presentation' => '5.50', 'accuracy_penalties' => [],
            'presentation_components' => ['2.00', '1.75', '1.75'],
            'reason' => 'ارتباط تبلت داور قطع شده است.',
        ])->assertSessionHasNoErrors();

        $sheet = ScoreSheet::query()->firstOrFail();
        $this->assertSame('draft', $sheet->status);
        $this->assertSame('operator_proxy', $sheet->submission_mode);
        $this->assertSame([], $sheet->breakdown['accuracy_penalties_hundredths']);
        $this->assertSame([200, 175, 175], $sheet->breakdown['presentation_components_hundredths']);
    }

    public function test_existing_rule_without_detailed_input_keeps_accepting_direct_scores(): void
    {
        $fixture = $this->competition();
        $rule = $fixture['category']->scoringRuleSet;
        $definition = $rule->definition;
        unset($definition['input_method'], $definition['accuracy_deduction_options'], $definition['presentation_component_max']);
        $rule->update(['definition' => [...$definition, 'accuracy_max' => 300, 'presentation_max' => 700]]);
        $this->schedule($fixture);
        $performance = Performance::query()->orderBy('id')->firstOrFail();
        $this->startScoring($fixture, $performance);

        $this->actingAs($fixture['judges'][0])->post(route('judging.store', [$fixture['tournament'], $performance]), [
            'request_id' => (string) Str::uuid(), 'expected_version' => $performance->fresh()->version,
            'expected_revision' => 0, 'accuracy' => '2.50', 'presentation' => '6.50',
        ])->assertSessionHasNoErrors();

        $this->assertSame('direct', ScoreSheet::query()->firstOrFail()->breakdown['method']);
    }
}
