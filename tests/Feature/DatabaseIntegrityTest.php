<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Entry;
use App\Models\Tournament;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DatabaseIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_seeder_is_repeatable_and_has_no_approved_rules(): void
    {
        User::factory()->create(['is_admin' => true]);
        $this->seed(DemoSeeder::class);
        $this->seed(DemoSeeder::class);
        $this->assertDatabaseCount('tournaments', 1);
        $this->assertDatabaseCount('entries', 2);
        $this->assertDatabaseCount('entry_members', 2);
        $this->assertDatabaseCount('scoring_rule_sets', 0);
    }

    public function test_entry_cannot_include_member_of_another_category(): void
    {
        User::factory()->create(['is_admin' => true]);
        $this->seed(DemoSeeder::class);
        $category = Category::firstOrFail();
        $other = $category->replicate();
        $other->name = 'Other';
        $other->save();
        $this->expectException(QueryException::class);
        DB::table('entry_members')->insert(['entry_id' => Entry::firstOrFail()->id, 'category_id' => $other->id, 'athlete_id' => 1, 'position' => 2]);
    }

    public function test_duplicate_judge_seat_is_rejected(): void
    {
        User::factory()->create(['is_admin' => true]);
        $this->seed(DemoSeeder::class);
        $category = Category::firstOrFail();
        $round = $category->rounds()->create(['name' => 'Final', 'sequence' => 1]);
        $bout = $round->bouts()->create(['category_id' => $category->id, 'sequence' => 1]);
        $bout->judges()->create(['user_id' => User::firstOrFail()->id, 'seat' => 1]);
        $this->expectException(QueryException::class);
        $bout->judges()->create(['user_id' => User::factory()->create()->id, 'seat' => 1]);
    }

    public function test_tournament_with_history_cannot_be_deleted(): void
    {
        User::factory()->create(['is_admin' => true]);
        $this->seed(DemoSeeder::class);
        $this->expectException(QueryException::class);
        Tournament::firstOrFail()->delete();
    }

    public function test_unsupported_judge_counts_are_rejected(): void
    {
        User::factory()->create(['is_admin' => true]);
        $this->seed(DemoSeeder::class);
        $this->expectException(QueryException::class);
        Category::firstOrFail()->update(['judge_count' => '6']);
    }
}
