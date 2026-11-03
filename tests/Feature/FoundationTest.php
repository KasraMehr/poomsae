<?php

namespace Tests\Feature;

use App\Events\TournamentCreated;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/login')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Auth/Login'));
    }

    public function test_user_can_login_and_logout(): void
    {
        $user = User::factory()->create();
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_admin_can_create_draft_with_membership_and_audit(): void
    {
        Event::fake([TournamentCreated::class]);
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->post('/tournaments', [
            'name' => 'مسابقه تهران', 'starts_on' => '2026-10-01', 'ends_on' => '2026-10-02',
            'timezone' => 'Asia/Tehran', 'status' => 'running', 'created_by' => 999,
        ])->assertSessionHasNoErrors()->assertRedirect();
        $tournament = Tournament::firstOrFail();
        $this->assertSame('draft', $tournament->status);
        $this->assertSame($admin->id, $tournament->created_by);
        $this->assertDatabaseHas('tournament_user', ['tournament_id' => $tournament->id, 'user_id' => $admin->id, 'role' => 'manager']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'tournament.created', 'subject_id' => $tournament->id]);
        Event::assertDispatched(TournamentCreated::class);
    }

    public function test_non_admin_cannot_create_tournament(): void
    {
        $this->actingAs(User::factory()->create())->post('/tournaments', [])->assertForbidden();
    }

    public function test_invalid_dates_do_not_create_tournament(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]))->post('/tournaments', [
            'name' => 'Invalid', 'starts_on' => '2026-10-02', 'ends_on' => '2026-10-01', 'timezone' => 'invalid',
        ])->assertSessionHasErrors(['ends_on', 'timezone']);
        $this->assertDatabaseCount('tournaments', 0);
    }

    public function test_members_only_see_their_tournaments(): void
    {
        $member = User::factory()->create();
        $allowed = Tournament::factory()->create();
        $hidden = Tournament::factory()->create();
        $allowed->users()->attach($member, ['role' => 'judge']);
        $this->actingAs($member)->get('/dashboard')->assertRedirect(route('judging.index', $allowed));
        $this->get('/tournaments/'.$allowed->id)->assertRedirect(route('judging.index', $allowed));
        $this->get('/tournaments/'.$hidden->id)->assertForbidden();
    }

    public function test_operator_with_multiple_tournaments_selects_an_authorized_competition(): void
    {
        $operator = User::factory()->create();
        $first = Tournament::factory()->create(['status' => 'running']);
        $second = Tournament::factory()->create(['status' => 'ready']);
        $hidden = Tournament::factory()->create(['status' => 'running']);
        $first->users()->attach($operator, ['role' => 'operator']);
        $second->users()->attach($operator, ['role' => 'operator']);

        $this->actingAs($operator)->get(route('dashboard'))->assertRedirect(route('tournaments.index'));
        $this->get(route('tournaments.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Tournaments/Index')
            ->where('isOperator', true)
            ->has('tournaments.data', 2));
        $this->get(route('tournaments.show', $first))->assertOk();
        $this->get(route('tournaments.show', $second))->assertOk();
        $this->get(route('tournaments.show', $hidden))->assertForbidden();
    }

    public function test_single_tournament_operator_still_enters_their_competition_directly(): void
    {
        $operator = User::factory()->create();
        $tournament = Tournament::factory()->create();
        $tournament->users()->attach($operator, ['role' => 'operator']);

        $this->actingAs($operator)->get(route('dashboard'))->assertRedirect(route('tournaments.show', $tournament));
        $this->get(route('tournaments.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('isOperator', true)
            ->has('tournaments.data', 1));
    }

    public function test_inactive_account_is_blocked_even_with_existing_session(): void
    {
        $user = User::factory()->create(['is_active' => false, 'is_admin' => true]);
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->actingAs($user)->get('/dashboard')->assertForbidden();
    }

    public function test_login_is_rate_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => 'invalid@example.com', 'password' => 'wrong'])->assertSessionHasErrors();
        }
        $this->post('/login', ['email' => 'invalid@example.com', 'password' => 'wrong'])->assertStatus(429);
    }
}
