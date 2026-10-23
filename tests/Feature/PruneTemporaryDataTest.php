<?php

namespace Tests\Feature;

use App\Actions\PruneTemporaryData;
use App\Models\IdempotencyKey;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PruneTemporaryDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_prune_temporary_data_deletes_old_idempotency_keys_and_retains_recent(): void
    {
        $user = User::factory()->create();

        IdempotencyKey::factory()->create([
            'user_id' => $user->id,
            'request_id' => '11111111-1111-1111-1111-111111111111',
            'created_at' => now()->subHours(48),
        ]);
        IdempotencyKey::factory()->create([
            'user_id' => $user->id,
            'request_id' => '22222222-2222-2222-2222-222222222222',
            'created_at' => now()->subHours(25),
        ]);
        $recent = IdempotencyKey::factory()->create([
            'user_id' => $user->id,
            'request_id' => '33333333-3333-3333-3333-333333333333',
            'created_at' => now()->subHours(2),
        ]);
        $now = IdempotencyKey::factory()->create([
            'user_id' => $user->id,
            'request_id' => '44444444-4444-4444-4444-444444444444',
            'created_at' => now(),
        ]);

        $this->artisan('poomsae:prune-temporary-data', ['--hours' => 24])
            ->expectsOutputToContain('Pruned 2 idempotency key(s) older than 24 hour(s).')
            ->assertSuccessful();

        $this->assertDatabaseMissing('idempotency_keys', ['request_id' => '11111111-1111-1111-1111-111111111111']);
        $this->assertDatabaseMissing('idempotency_keys', ['request_id' => '22222222-2222-2222-2222-222222222222']);
        $this->assertDatabaseHas('idempotency_keys', ['id' => $recent->id]);
        $this->assertDatabaseHas('idempotency_keys', ['id' => $now->id]);
    }

    public function test_prune_temporary_data_deletes_expired_tokens_and_retains_valid_tokens(): void
    {
        $user = User::factory()->create();

        $expiredToken = $user->createToken('expired-1', ['tournaments:read'], now()->subHours(2));
        $validToken = $user->createToken('valid-1', ['tournaments:read'], now()->addHours(12));
        $unexpiringToken = $user->createToken('no-expiry', ['tournaments:read'], null);

        $this->artisan('poomsae:prune-temporary-data', ['--token-hours' => 0])
            ->expectsOutputToContain('Pruned 1 expired Sanctum token(s).')
            ->assertSuccessful();

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $expiredToken->accessToken->id]);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $validToken->accessToken->id]);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $unexpiringToken->accessToken->id]);
    }

    public function test_prune_temporary_data_respects_token_hours_grace_period(): void
    {
        $user = User::factory()->create();

        $expiredRecently = $user->createToken('expired-recently', ['tournaments:read'], now()->subHours(2));
        $expiredLongAgo = $user->createToken('expired-long-ago', ['tournaments:read'], now()->subHours(30));

        $this->artisan('poomsae:prune-temporary-data', ['--token-hours' => 24])
            ->expectsOutputToContain('Pruned 1 expired Sanctum token(s).')
            ->assertSuccessful();

        $this->assertDatabaseHas('personal_access_tokens', ['id' => $expiredRecently->accessToken->id]);
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $expiredLongAgo->accessToken->id]);
    }

    public function test_prune_temporary_data_flags_only_idempotency_and_only_tokens(): void
    {
        $user = User::factory()->create();

        $key = IdempotencyKey::factory()->create([
            'user_id' => $user->id,
            'created_at' => now()->subHours(48),
        ]);
        $token = $user->createToken('expired', ['tournaments:read'], now()->subHours(5));

        $this->artisan('poomsae:prune-temporary-data', ['--only-idempotency' => true, '--hours' => 24])
            ->expectsOutputToContain('Pruned 1 idempotency key(s)')
            ->doesntExpectOutputToContain('expired Sanctum token(s)')
            ->assertSuccessful();

        $this->assertDatabaseMissing('idempotency_keys', ['id' => $key->id]);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $token->accessToken->id]);

        $this->artisan('poomsae:prune-temporary-data', ['--only-tokens' => true, '--token-hours' => 0])
            ->expectsOutputToContain('Pruned 1 expired Sanctum token(s)')
            ->doesntExpectOutputToContain('idempotency key(s)')
            ->assertSuccessful();

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $token->accessToken->id]);
    }

    public function test_prune_temporary_data_overrides_hours_with_idempotency_hours(): void
    {
        $user = User::factory()->create();

        $key = IdempotencyKey::factory()->create([
            'user_id' => $user->id,
            'created_at' => now()->subHours(10),
        ]);

        $this->artisan('poomsae:prune-temporary-data', ['--hours' => 24, '--idempotency-hours' => 8])
            ->expectsOutputToContain('Pruned 1 idempotency key(s) older than 8 hour(s).')
            ->assertSuccessful();

        $this->assertDatabaseMissing('idempotency_keys', ['id' => $key->id]);
    }

    public function test_action_prune_temporary_data_returns_deleted_counts(): void
    {
        $user = User::factory()->create();

        IdempotencyKey::factory()->count(2)->create([
            'user_id' => $user->id,
            'created_at' => now()->subHours(30),
        ]);
        $user->createToken('expired-1', ['tournaments:read'], now()->subHours(2));

        $result = app(PruneTemporaryData::class)->handle(24, 0);

        $this->assertSame(2, $result['idempotency_keys']);
        $this->assertSame(1, $result['personal_access_tokens']);
    }

    public function test_scheduled_tasks_are_configured_in_console(): void
    {
        $events = collect(app(Schedule::class)->events());

        $commands = $events->map(fn ($event) => $event->command)->all();

        $hasTemporaryDataPruning = false;
        $hasSanctumPruning = false;

        foreach ($commands as $command) {
            if (is_string($command) && str_contains($command, 'poomsae:prune-temporary-data')) {
                $hasTemporaryDataPruning = true;
            }
            if (is_string($command) && str_contains($command, 'sanctum:prune-expired')) {
                $hasSanctumPruning = true;
            }
        }

        $this->assertTrue($hasTemporaryDataPruning, 'poomsae:prune-temporary-data should be registered in schedule.');
        $this->assertTrue($hasSanctumPruning, 'sanctum:prune-expired should be registered in schedule.');
    }
}
