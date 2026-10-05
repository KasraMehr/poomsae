<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\CreatesCompetition;
use Tests\TestCase;

class CompetitionConfigurationMigrationTest extends TestCase
{
    use CreatesCompetition, DatabaseMigrations;

    public function test_migration_numbers_existing_stages_and_preserves_registration(): void
    {
        $this->artisan('migrate:rollback', ['--step' => 1, '--no-interaction' => true])->assertSuccessful();
        $fixture = $this->competition(5, 2, 'knockout', 1, 1);
        $round = $fixture['category']->rounds()->firstOrFail();
        $round->update(['name' => 'فینال']);

        $this->artisan('migrate', ['--no-interaction' => true])->assertSuccessful();

        $this->assertSame('مرحله 1', $round->fresh()->name);
        $this->assertSame(5, $fixture['category']->fresh()->judge_count);
        $this->assertDatabaseCount('entries', 2);
        $this->assertDatabaseCount('entry_members', 2);
    }
}
