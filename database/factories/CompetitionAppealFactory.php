<?php

namespace Database\Factories;

use App\Actions\ManageCompetitionAppeals;
use App\Models\CompetitionAppeal;
use App\Models\Performance;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CompetitionAppeal>
 */
class CompetitionAppealFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'public_id' => (string) Str::uuid(),
            'performance_id' => fn (): int => Performance::where('status', 'approved')->whereNotIn('id', CompetitionAppeal::select('performance_id'))->firstOrFail()->id,
            'tournament_id' => fn (array $attributes): int => Performance::with('bout.category')->findOrFail($attributes['performance_id'])->bout->category->tournament_id,
            'category_id' => fn (array $attributes): int => Performance::with('bout')->findOrFail($attributes['performance_id'])->bout->category_id,
            'filed_by' => User::factory(),
            'representative_name' => fake()->name(), 'reason' => fake()->paragraph(), 'evidence' => null,
            'status' => 'submitted', 'version' => 1,
            'performance_version' => fn (array $attributes): int => Performance::findOrFail($attributes['performance_id'])->version,
            'original_snapshot' => fn (array $attributes): array => app(ManageCompetitionAppeals::class)->facts(Performance::findOrFail($attributes['performance_id'])),
        ];
    }
}
