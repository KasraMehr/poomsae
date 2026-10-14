<?php

namespace Tests\Feature;

use App\Actions\CompetitionSetup;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OperatorLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_tournament_allows_adding_one_operator(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $tournament = Tournament::factory()->create(['created_by' => $admin->id]);
        $operator = User::factory()->create();

        $setup = app(CompetitionSetup::class);
        $setup->member($admin, $tournament, [
            'name' => $operator->name, 'email' => $operator->email, 'role' => 'operator',
        ]);

        $this->assertDatabaseHas('tournament_user', [
            'tournament_id' => $tournament->id, 'user_id' => $operator->id, 'role' => 'operator',
        ]);
    }

    public function test_tournament_rejects_second_operator(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $tournament = Tournament::factory()->create(['created_by' => $admin->id]);
        $firstOperator = User::factory()->create();
        $secondOperator = User::factory()->create();

        $setup = app(CompetitionSetup::class);
        $setup->member($admin, $tournament, [
            'name' => $firstOperator->name, 'email' => $firstOperator->email, 'role' => 'operator',
        ]);

        $this->expectException(ValidationException::class);

        $setup->member($admin, $tournament, [
            'name' => $secondOperator->name, 'email' => $secondOperator->email, 'role' => 'operator',
        ]);
    }

    public function test_tournament_allows_operator_and_other_roles(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $tournament = Tournament::factory()->create(['created_by' => $admin->id]);
        $operator = User::factory()->create();
        $judge = User::factory()->create();
        $display = User::factory()->create();

        $setup = app(CompetitionSetup::class);
        $setup->member($admin, $tournament, [
            'name' => $operator->name, 'email' => $operator->email, 'role' => 'operator',
        ]);
        $setup->member($admin, $tournament, [
            'name' => $judge->name, 'email' => $judge->email, 'role' => 'judge',
        ]);
        $setup->member($admin, $tournament, [
            'name' => $display->name, 'email' => $display->email, 'role' => 'display',
        ]);

        $this->assertDatabaseCount('tournament_user', 3); // operator + judge + display
    }

    public function test_different_tournaments_can_each_have_one_operator(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $tournament1 = Tournament::factory()->create(['created_by' => $admin->id]);
        $tournament2 = Tournament::factory()->create(['created_by' => $admin->id]);
        $operator1 = User::factory()->create();
        $operator2 = User::factory()->create();

        $setup = app(CompetitionSetup::class);
        $setup->member($admin, $tournament1, [
            'name' => $operator1->name, 'email' => $operator1->email, 'role' => 'operator',
        ]);
        $setup->member($admin, $tournament2, [
            'name' => $operator2->name, 'email' => $operator2->email, 'role' => 'operator',
        ]);

        $this->assertDatabaseHas('tournament_user', [
            'tournament_id' => $tournament1->id, 'user_id' => $operator1->id, 'role' => 'operator',
        ]);
        $this->assertDatabaseHas('tournament_user', [
            'tournament_id' => $tournament2->id, 'user_id' => $operator2->id, 'role' => 'operator',
        ]);
    }

    public function test_second_operator_rejected_via_http(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $tournament = Tournament::factory()->create(['created_by' => $admin->id]);
        $firstOperator = User::factory()->create();

        $setup = app(CompetitionSetup::class);
        $setup->member($admin, $tournament, [
            'name' => $firstOperator->name, 'email' => $firstOperator->email, 'role' => 'operator',
        ]);

        $this->actingAs($admin)->post('/tournaments/'.$tournament->id.'/members', [
            'name' => 'اپراتور دوم', 'email' => 'second-op@example.test',
            'password' => 'Long-Pilot-Password-2026', 'role' => 'operator',
        ])->assertSessionHasErrors('operation');
    }
}
