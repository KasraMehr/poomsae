<?php

namespace Database\Factories;

use App\Models\Tournament;
use Illuminate\Database\Eloquent\Factories\Factory;

class CategoryFactory extends Factory
{
    public function definition(): array
    {
        return ['tournament_id' => Tournament::factory(), 'name' => fake()->unique()->words(3, true), 'discipline' => 'recognized', 'entry_type' => 'individual', 'gender' => 'male', 'format' => 'knockout', 'execution_mode' => 'alternating', 'judge_count' => '5', 'forms_per_round' => 2, 'draw_timing' => 'day_start'];
    }
}
