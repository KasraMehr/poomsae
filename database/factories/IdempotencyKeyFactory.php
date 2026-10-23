<?php

namespace Database\Factories;

use App\Models\IdempotencyKey;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<IdempotencyKey>
 */
class IdempotencyKeyFactory extends Factory
{
    protected $model = IdempotencyKey::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'request_id' => (string) Str::uuid(),
            'payload_hash' => hash('sha256', Str::random(32)),
            'response_status' => 200,
            'response_body' => ['score_sheet_id' => 1, 'revision' => 1, 'requires_confirmation' => false],
            'created_at' => now(),
        ];
    }
}
