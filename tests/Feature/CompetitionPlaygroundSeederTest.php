<?php

namespace Tests\Feature;

use App\Models\Tournament;
use App\Models\User;
use Database\Seeders\CompetitionPlaygroundSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompetitionPlaygroundSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_all_scenarios_on_two_courts_and_can_be_rerun(): void
    {
        $this->seed(CompetitionPlaygroundSeeder::class);

        $this->assertSame(13, Tournament::count());
        $this->assertSame(17, User::count());
        foreach (Tournament::with(['courts', 'categories.rounds.bouts'])->get() as $tournament) {
            $this->assertCount(2, $tournament->courts);
            $this->assertCount(1, $tournament->categories);
            $category = $tournament->categories->first();
            $this->assertSame(4, $category->entries()->count());
            $bouts = $category->rounds->first()->bouts;
            $this->assertCount($category->format === 'round_robin' ? 4 : 2, $bouts);
            $this->assertCount(2, $bouts->pluck('court_id')->unique());
            foreach ($bouts as $bout) {
                $this->assertSame((int) $category->judge_count, $bout->judges()->count());
            }
        }

        $this->seed(CompetitionPlaygroundSeeder::class);
        $this->assertSame(13, Tournament::count());
        $this->assertSame(17, User::count());
    }
}
