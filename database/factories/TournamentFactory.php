<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TournamentFactory extends Factory
{
    public function definition(): array
    {
        return ['name' => fake()->unique()->sentence(3), 'venue' => fake()->city(), 'starts_on' => '2026-10-01', 'timezone' => 'Asia/Tehran', 'status' => 'draft', 'created_by' => User::factory()];
    }
}
