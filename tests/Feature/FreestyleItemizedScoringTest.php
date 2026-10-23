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

class FreestyleItemizedScoringTest extends TestCase
{
    use CreatesCompetition, RefreshDatabase;

    private function freestyle(): array
    {
        Storage::fake('local');
        $fixture = $this->competition();
        $setup = app(CompetitionSetup::class);
        $setup->category($fixture['admin'], $fixture['tournament'], [
            'name' => 'ابداعی انفرادی مردان',
            'discipline' => 'freestyle',
            'entry_type' => 'individual',
            'gender' => 'male',
            'minimum_age' => 10,
            'maximum_age' => 40,
            'format' => 'knockout',
            'judge_count' => 5,
            'discard_each_end' => 1,
            'rules_acknowledged' => true,
        ]);
        $fixture['category'] = $fixture['tournament']->categories()->where('discipline', 'freestyle')->firstOrFail();
        foreach (range(1, 2) as $index) {
            $setup->entry($fixture['admin'], $fixture['tournament'], $fixture['category'], [
                'first_name' => 'ورزشکار '.$index,
                'last_name' => 'سلمانی',
                'birth_date' => '2005-01-01',
                'gender' => 'male',
                'music' => UploadedFile::fake()->create('track.mp3', 10, 'audio/mpeg'),
            ]);
        }
        foreach ($fixture['category']->entries()->get() as $entry) {
            $setup->entryStatus($fixture['admin'], $fixture['tournament'], $entry, 'checked_in');
        }
        $this->schedule($fixture);
        $fixture['performance'] = Performance::query()->orderBy('id')->firstOrFail();

        return $fixture;
    }

    /**
     * Payload with 6 accuracy/technical components (max 1.00 each = 6.00)
     * and 4 presentation components (max 1.00 each = 4.00).
     */
    private function itemizedPayload(Performance $performance, array $accuracy = ['0.80', '0.90', '0.80', '0.90', '0.70', '0.80'], array $presentation = ['0.80', '0.90', '0.80', '0.70']): array
    {
        return [
            'request_id' => (string) Str::uuid(),
            'expected_version' => $performance->fresh()->version,
            'expected_revision' => 0,
            'accuracy' => number_format(array_sum(array_map('floatval', $accuracy)), 2, '.', ''),
            'presentation' => number_format(array_sum(array_map('floatval', $presentation)), 2, '.', ''),
            'accuracy_components' => $accuracy,
            'presentation_components' => $presentation,
        ];
    }

    private function migration(): Migration
    {
        return require database_path('migrations/2026_10_22_165942_align_freestyle_scoring_rules_to_wt_standards.php');
    }

    public function test_freestyle_defaults_to_six_accuracy_plus_four_presentation_components(): void
    {
        $fixture = $this->freestyle();
        $definition = $fixture['category']->scoringRuleSet->definition;

        $this->assertSame(600, $definition['accuracy_max']);
        $this->assertSame(400, $definition['presentation_max']);
        $this->assertSame(100, $definition['accuracy_component_max']);
        $this->assertSame(6, $definition['accuracy_component_count']);
        $this->assertSame(100, $definition['presentation_component_max']);
        $this->assertSame(4, $definition['presentation_component_count']);
        $this->assertSame('freestyle_components_v1', $definition['input_method']);

        // Check technical components (6 items: S, F, G, S, A, B)
        $tech = $definition['technical_components'];
        $this->assertCount(6, $tech);
        $this->assertSame(['S', 'F', 'G', 'S', 'A', 'B'], array_column($tech, 'abbr'));
        $this->assertSame(['Side Kick', 'Front Kick', 'Spin Gradient', 'Sparring', 'Acrobatic', 'Basic Movements'], array_column($tech, 'name'));

        // Check presentation components (4 items: C, H, E, M)
        $pres = $definition['presentation_components'];
        $this->assertCount(4, $pres);
        $this->assertSame(['C', 'H', 'E', 'M'], array_column($pres, 'abbr'));
        $this->assertSame(['Creativity', 'Harmony', 'Expression of Energy', 'Music'], array_column($pres, 'name'));
    }

    public function test_freestyle_itemized_scoring_and_detailed_scoreboard_display(): void
    {
        $fixture = $this->freestyle();
        $performance = $fixture['performance'];
        $this->startScoring($fixture, $performance);

        // Submit matching scores from all 5 judges (0.8, 0.9, 0.8, 0.9, 0.7, 0.8 for accuracy [4.90] and 0.8, 0.9, 0.8, 0.7 for presentation [3.20])
        foreach ($fixture['judges'] as $judge) {
            Sanctum::actingAs($judge, ['scores:write']);
            $this->postJson(
                '/api/v1/tournaments/'.$fixture['tournament']->id.'/performances/'.$performance->id.'/scores',
                $this->itemizedPayload($performance)
            )->assertOk();
        }

        // Verify ScoreSheet breakdown in database
        $sheet = ScoreSheet::query()->firstOrFail();
        $this->assertSame([80, 90, 80, 90, 70, 80], $sheet->breakdown['accuracy_components_hundredths']);
        $this->assertSame([80, 90, 80, 70], $sheet->breakdown['presentation_components_hundredths']);
        $this->assertSame(490, $sheet->breakdown['accuracy_hundredths']);
        $this->assertSame(320, $sheet->breakdown['presentation_hundredths']);

        // Verify public scoreboard snapshot before approval (preview state)
        $public = app(CompetitionView::class)->snapshot($fixture['tournament'], $fixture['admin'], true);
        $category = collect($public['categories'])->firstWhere('id', $fixture['category']->id);
        $preview = collect(collect(collect($category['rounds'])->first()['bouts'])->first()['performances'])->first();

        $this->assertSame('8.100000', $preview['preview_result']);
        $this->assertNull($preview['result']);
        $this->assertSame([], $preview['scores']); // Operator-only scores remain empty for public display

        // Check detailed_scores in preview
        $detailed = $preview['detailed_scores'];
        $this->assertNotNull($detailed);
        $this->assertSame('freestyle', $detailed['discipline']);
        $this->assertSame('freestyle_components_v1', $detailed['method']);
        $this->assertSame('4.90', $detailed['accuracy']);
        $this->assertSame('6.0', $detailed['accuracy_max']);
        $this->assertSame('3.20', $detailed['presentation']);
        $this->assertSame('4.0', $detailed['presentation_max']);
        $this->assertSame('8.10', $detailed['total']);
        $this->assertSame('10.0', $detailed['total_max']);

        $this->assertCount(5, $detailed['judges']);
        foreach ($detailed['judges'] as $index => $judgeRow) {
            $seatNumber = $index + 1;
            $this->assertSame('J'.$seatNumber, $judgeRow['label']);
            $this->assertSame($seatNumber, $judgeRow['seat']);
            $this->assertSame(['0.80', '0.90', '0.80', '0.90', '0.70', '0.80'], $judgeRow['accuracy_components']);
            $this->assertSame(['0.80', '0.90', '0.80', '0.70'], $judgeRow['presentation_components']);
            $this->assertSame('4.90', $judgeRow['accuracy']);
            $this->assertSame('3.20', $judgeRow['presentation']);
            $this->assertSame('8.10', $judgeRow['total']);
        }

        // Verify /display-data API endpoint delivers detailed_scores
        $displayData = $this->actingAs($fixture['admin'])->getJson(route('scoreboard.data', $fixture['tournament']))->assertOk();
        $courtPerf = $displayData->json('data.courts.0.current.performances.0');
        $this->assertNotNull($courtPerf['detailed_scores']);
        $this->assertCount(5, $courtPerf['detailed_scores']['judges']);
        $this->assertSame('4.90', $courtPerf['detailed_scores']['accuracy']);
        $this->assertSame('3.20', $courtPerf['detailed_scores']['presentation']);
        $this->assertSame('8.10', $courtPerf['detailed_scores']['total']);

        // Approve performance
        app(RunCompetition::class)->command($fixture['admin'], $fixture['tournament'], $performance, [
            'command' => 'approve',
            'expected_version' => $performance->fresh()->version,
        ]);

        // Verify approved result has detailed_scores
        $approvedSnapshot = app(CompetitionView::class)->snapshot($fixture['tournament'], $fixture['admin'], true);
        $approvedCategory = collect($approvedSnapshot['categories'])->firstWhere('id', $fixture['category']->id);
        $approved = collect(collect(collect($approvedCategory['rounds'])->first()['bouts'])->first()['performances'])->first();

        $this->assertSame('8.100000', $approved['result']);
        $this->assertNull($approved['preview_result']);
        $this->assertNotNull($approved['detailed_scores']);
        $this->assertSame('8.10', $approved['detailed_scores']['total']);
        $this->assertSame('4.90', $approved['detailed_scores']['accuracy']);
        $this->assertSame('3.20', $approved['detailed_scores']['presentation']);
        $this->assertCount(5, $approved['detailed_scores']['judges']);
    }

    public function test_invalid_freestyle_itemized_component_counts_and_limits_are_rejected(): void
    {
        $fixture = $this->freestyle();
        $performance = $fixture['performance'];
        $this->startScoring($fixture, $performance);
        $url = route('judging.store', [$fixture['tournament'], $performance]);
        $payload = $this->itemizedPayload($performance);
        $this->actingAs($fixture['judges'][0]);

        foreach ([
            // Wrong count for accuracy (requires exactly 6)
            ['accuracy_components' => ['0.80', '0.90', '0.80']],
            ['accuracy_components' => ['0.80', '0.90', '0.80', '0.70', '0.50']],
            ['accuracy_components' => ['0.80', '0.90', '0.80', '0.70', '0.50', '0.60', '0.70']],
            // Wrong count for presentation (requires exactly 4)
            ['presentation_components' => ['0.90', '0.80', '0.80']],
            ['presentation_components' => ['0.90', '0.80', '0.80', '0.90', '0.70']],
            // Individual component exceeding 1.00
            ['accuracy_components' => ['1.05', '0.90', '0.80', '0.70', '0.80', '0.80']],
            ['presentation_components' => ['0.90', '0.80', '1.10', '0.90']],
            // Negative component
            ['accuracy_components' => ['-0.10', '0.90', '0.80', '0.70', '0.80', '0.80']],
            // Sum mismatch
            ['accuracy' => '5.50'],
            ['presentation' => '3.00'],
            // Penalties prohibited in freestyle_components_v1
            ['presentation_penalties' => ['0.10']],
            ['accuracy_penalties' => ['0.10']],
        ] as $invalid) {
            $this->postJson($url, [...$payload, ...$invalid])->assertUnprocessable();
        }

        $this->assertDatabaseCount('score_sheets', 0);
    }

    public function test_secretary_can_deduct_referee_penalty_in_tenths_which_affects_final_score(): void
    {
        $fixture = $this->freestyle();
        $performance = $fixture['performance'];
        $this->startScoring($fixture, $performance);

        foreach ($fixture['judges'] as $judge) {
            Sanctum::actingAs($judge, ['scores:write']);
            $this->postJson(
                '/api/v1/tournaments/'.$fixture['tournament']->id.'/performances/'.$performance->id.'/scores',
                $this->itemizedPayload($performance)
            )->assertOk();
        }

        // Referee asks secretary to deduct 0.5 (50 hundredths)
        $url = route('operations.performance.referee-penalty', [$fixture['tournament'], $performance]);
        $response = $this->actingAs($fixture['admin'])->post($url, [
            'penalty_hundredths' => 50,
            'reason' => 'کسر نمره به دستور داور وسط برای تخلف انضباطی',
            'expected_version' => $performance->fresh()->version,
        ]);
        $response->assertRedirect()->assertSessionHasNoErrors();

        $snapshot = $performance->fresh()->timing_snapshot;
        $this->assertSame(50, $snapshot['referee_penalty_hundredths']);

        // Check preview: gross 8.100000 - 0.500000 = 7.600000
        $public = app(CompetitionView::class)->snapshot($fixture['tournament'], $fixture['admin'], true);
        $category = collect($public['categories'])->firstWhere('id', $fixture['category']->id);
        $preview = collect(collect(collect($category['rounds'])->first()['bouts'])->first()['performances'])->first();
        $this->assertSame('7.600000', $preview['preview_result']);

        // Approve and verify published score has 0.50 deducted
        app(RunCompetition::class)->command($fixture['admin'], $fixture['tournament'], $performance, [
            'command' => 'approve',
            'expected_version' => $performance->fresh()->version,
        ]);

        $result = $performance->result()->firstOrFail();
        $this->assertSame('7.600000', $result->score);
        $this->assertSame('8.100000', $result->calculation_snapshot['gross_score']);
        $this->assertSame(50, $result->calculation_snapshot['timing']['referee_penalty_hundredths']);

        // Secretary adjusts referee penalty after approval to 0.20 (20 hundredths, e.g. 0.2)
        $response = $this->actingAs($fixture['admin'])->post($url, [
            'penalty' => '0.20',
            'reason' => 'تقلیل کسر نمره پس از بررسی بازبینی ویدیویی',
            'expected_version' => $performance->fresh()->version,
        ]);
        $response->assertRedirect()->assertSessionHasNoErrors();

        // Result is automatically recalculated: 8.100000 - 0.200000 = 7.900000
        $this->assertSame('7.900000', $performance->result()->firstOrFail()->score);
        $this->assertSame(20, $performance->result()->firstOrFail()->calculation_snapshot['timing']['referee_penalty_hundredths']);
    }

    public function test_migration_upgrades_unstarted_categories_to_freestyle_wt_standards(): void
    {
        $fixture = $this->freestyle();
        // Artificially set to old 4 accuracy / 6 presentation definition
        $rule = $fixture['category']->scoringRuleSet;
        $definition = $rule->definition;
        $rule->update([
            'definition' => [
                ...$definition,
                'accuracy_max' => 400,
                'presentation_max' => 600,
                'accuracy_component_count' => 4,
                'presentation_component_count' => 6,
                'input_method' => 'freestyle_components_v1',
            ],
        ]);

        $performanceVersion = $fixture['performance']->version;
        $this->migration()->up();

        $category = $fixture['category']->fresh();
        $this->assertNotSame($rule->id, $category->scoring_rule_set_id);
        $this->assertSame('freestyle_components_v1', $category->scoringRuleSet->definition['input_method']);
        $this->assertSame(600, $category->scoringRuleSet->definition['accuracy_max']);
        $this->assertSame(400, $category->scoringRuleSet->definition['presentation_max']);
        $this->assertSame(6, $category->scoringRuleSet->definition['accuracy_component_count']);
        $this->assertSame($performanceVersion + 1, $fixture['performance']->fresh()->version);
    }

    public function test_operator_can_adjust_referee_penalty_via_rest_api(): void
    {
        $fixture = $this->freestyle();
        $performance = $fixture['performance'];
        $this->startScoring($fixture, $performance);

        foreach ($fixture['judges'] as $judge) {
            Sanctum::actingAs($judge, ['scores:write']);
            $this->postJson(
                '/api/v1/tournaments/'.$fixture['tournament']->id.'/performances/'.$performance->id.'/scores',
                $this->itemizedPayload($performance)
            )->assertOk();
        }

        Sanctum::actingAs($fixture['admin'], ['competition:operate']);
        $url = '/api/v1/tournaments/'.$fixture['tournament']->id.'/performances/'.$performance->id.'/referee-penalty';
        $response = $this->postJson($url, [
            'penalty_hundredths' => 50,
            'reason' => 'کسر نمره ۵ دهم از طریق وب‌سرویس',
            'expected_version' => $performance->fresh()->version,
        ])->assertOk();

        $this->assertSame(50, $response->json('data.timing.referee_penalty_hundredths'));
        $this->assertSame(50, $performance->fresh()->timing_snapshot['referee_penalty_hundredths']);
    }
}
