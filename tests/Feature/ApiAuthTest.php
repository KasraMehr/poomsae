<?php

namespace Tests\Feature;

use App\Models\Tournament;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_token_login_and_revocation(): void
    {
        $user = User::factory()->create();
        $response = $this->postJson('/api/v1/auth/token', ['email' => $user->email, 'password' => 'password', 'device_name' => 'Judge tablet']);
        $response->assertCreated()->assertJsonPath('token_type', 'Bearer');
        $token = $response->json('token');
        $this->withToken($token)->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.id', $user->id)->assertJsonMissingPath('data.password');
        $this->withToken($token)->deleteJson('/api/v1/auth/token')->assertNoContent();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_api_requires_authentication(): void
    {
        $this->getJson('/api/v1/tournaments')->assertUnauthorized();
    }

    public function test_api_enforces_membership_and_token_ability(): void
    {
        $user = User::factory()->create();
        $allowed = Tournament::factory()->create();
        $denied = Tournament::factory()->create();
        $allowed->users()->attach($user, ['role' => 'judge']);
        Sanctum::actingAs($user, ['tournaments:read']);
        $this->getJson('/api/v1/tournaments')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $allowed->id);
        $this->getJson('/api/v1/tournaments/'.$denied->id)->assertForbidden();
        Sanctum::actingAs($user, []);
        $this->getJson('/api/v1/tournaments')->assertForbidden();
    }

    public function test_disabled_user_cannot_use_existing_token(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => false]), ['tournaments:read']);
        $this->getJson('/api/v1/me')->assertForbidden();
    }

    public function test_expired_token_is_rejected(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('expired', ['tournaments:read'], now()->subMinute());
        $this->withToken($token->plainTextToken)->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_mercure_batch_auth_denies_private_channel_for_non_member(): void
    {
        config([
            'broadcasting.default' => 'mercure',
            'broadcasting.connections.mercure.url' => 'http://localhost/.well-known/mercure',
            'broadcasting.connections.mercure.public_url' => 'http://localhost/.well-known/mercure',
            'broadcasting.connections.mercure.secret' => str_repeat('t', 32),
            'broadcasting.connections.mercure.cookie_name' => 'mercure_access_token',
        ]);
        $tournament = Tournament::factory()->create();
        Sanctum::actingAs(User::factory()->create(), ['tournaments:read']);
        $this->postJson('/api/v1/broadcasting/auth', ['channel_names' => ['private-tournaments.'.$tournament->id]])
            ->assertOk()
            ->assertJsonPath('channel_names.0.denied', true);
    }
}
