<?php

namespace Tests\Feature;

use App\Actions\CompetitionDisplay;
use App\Actions\CompetitionSetup;
use App\Actions\CompetitionView;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\CreatesCompetition;
use Tests\TestCase;

class ScoringModeTest extends TestCase
{
    use CreatesCompetition, RefreshDatabase;

    public function test_category_scoring_mode_attribute_resolves_all_modes(): void
    {
        $fixture = $this->competition();
        $category = $fixture['category'];

        // Default recognized alternating knockout
        $this->assertSame(Category::SCORING_MODE_SINGLE, $category->scoring_mode);
        $this->assertSame('SINGLE', $category->scoring_mode);

        // Recognized simultaneous knockout -> DOUBLE
        $category->update(['execution_mode' => 'simultaneous']);
        $this->assertSame(Category::SCORING_MODE_DOUBLE, $category->fresh()->scoring_mode);
        $this->assertSame('DOUBLE', $category->fresh()->scoring_mode);

        // Recognized round robin -> SINGLE
        $category->update(['format' => 'round_robin', 'execution_mode' => 'alternating']);
        $this->assertSame(Category::SCORING_MODE_SINGLE, $category->fresh()->scoring_mode);

        // Freestyle knockout -> SINGLE-FREESTYLE
        $setup = app(CompetitionSetup::class);
        $setup->category($fixture['admin'], $fixture['tournament'], [
            'name' => 'ابداعی انفرادی',
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
        $freestyleCat = $fixture['tournament']->categories()->where('discipline', 'freestyle')->firstOrFail();
        $this->assertSame(Category::SCORING_MODE_FREESTYLE, $freestyleCat->scoring_mode);
        $this->assertSame('SINGLE-FREESTYLE', $freestyleCat->scoring_mode);
    }

    public function test_competition_view_snapshot_exposes_scoring_mode_on_categories_and_bouts(): void
    {
        $fixture = $this->competition();
        $this->schedule($fixture);

        $view = app(CompetitionView::class);
        $snapshot = $view->snapshot($fixture['tournament'], $fixture['admin'], true);

        $categoryData = $snapshot['categories'][0];
        $this->assertSame('SINGLE', $categoryData['scoring_mode']);
        $this->assertSame('SINGLE', $categoryData['scoring_table_mode']);

        $boutData = $categoryData['rounds'][0]['bouts'][0];
        $this->assertSame('SINGLE', $boutData['scoring_mode']);

        // Test with simultaneous knockout
        $fixture['category']->update(['execution_mode' => 'simultaneous']);
        $snapshotUpdated = $view->snapshot($fixture['tournament']->fresh(), $fixture['admin'], true);
        $this->assertSame('DOUBLE', $snapshotUpdated['categories'][0]['scoring_mode']);
        $this->assertSame('DOUBLE', $snapshotUpdated['categories'][0]['rounds'][0]['bouts'][0]['scoring_mode']);
    }

    public function test_competition_display_snapshot_exposes_scoring_mode(): void
    {
        $fixture = $this->competition();
        $fixture['category']->update(['execution_mode' => 'simultaneous']);
        $this->schedule($fixture);

        $display = app(CompetitionDisplay::class);
        $snapshot = $display->snapshot($fixture['tournament'], $fixture['admin']);

        $item = $snapshot['courts'][0]['upcoming'][0];
        $this->assertSame('DOUBLE', $item['scoring_mode']);
        $this->assertSame('DOUBLE', $item['scoring_table_mode']);
    }

    public function test_api_competition_endpoint_returns_scoring_mode(): void
    {
        $fixture = $this->competition();
        $this->schedule($fixture);

        Sanctum::actingAs($fixture['admin'], ['tournaments:read']);
        $response = $this->getJson('/api/v1/tournaments/'.$fixture['tournament']->id.'/competition')->assertOk();

        $category = $response->json('data.categories.0');
        $this->assertSame('SINGLE', $category['scoring_mode']);
        $this->assertSame('SINGLE', $category['scoring_table_mode']);
        $this->assertSame('SINGLE', $category['rounds'][0]['bouts'][0]['scoring_mode']);
    }
}
