<?php

namespace Tests\Feature;

use App\Models\Tournament;
use App\Models\User;
use Database\Seeders\CompetitionPlaygroundSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CompetitionPlaygroundSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_production_requires_an_explicit_one_time_pilot_opt_in(): void
    {
        $this->app->detectEnvironment(fn (): string => 'production');

        try {
            $this->expectException(\RuntimeException::class);
            app(CompetitionPlaygroundSeeder::class)->run();
        } finally {
            $this->app->detectEnvironment(fn (): string => 'testing');
        }
    }

    public function test_opted_in_production_pilot_rotates_existing_account_passwords_on_rerun(): void
    {
        $this->app->detectEnvironment(fn (): string => 'production');
        putenv('POOMSAE_ALLOW_PLAYGROUND_SEED=1');

        try {
            app(CompetitionPlaygroundSeeder::class)->run();
            $this->assertSame(13, Tournament::count());
            $this->assertFalse(Hash::check('demoJudge123', User::where('email', 'playground.admin@example.test')->firstOrFail()->password));

            $oldHash = User::where('email', 'playground.admin@example.test')->firstOrFail()->password;
            app(CompetitionPlaygroundSeeder::class)->run();

            $this->assertSame(13, Tournament::count());
            $this->assertSame(17, User::count());
            $this->assertNotSame($oldHash, User::where('email', 'playground.admin@example.test')->firstOrFail()->password);
        } finally {
            putenv('POOMSAE_ALLOW_PLAYGROUND_SEED');
            $this->app->detectEnvironment(fn (): string => 'testing');
        }
    }

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

        User::where('email', 'playground.judge01@example.test')->firstOrFail()
            ->forceFill(['password' => 'outdated-password'])->save();
        $this->seed(CompetitionPlaygroundSeeder::class);
        $this->assertSame(13, Tournament::count());
        $this->assertSame(17, User::count());
        $this->assertTrue(Hash::check('demoJudge123', User::where('email', 'playground.judge01@example.test')->firstOrFail()->password));
    }
}
