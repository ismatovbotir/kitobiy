<?php

namespace Database\Factories;

use App\Models\Copy;
use App\Models\CopyTransfer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CopyTransfer>
 */
class CopyTransferFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'copy_id' => Copy::factory(),
            'offered_by' => User::factory(),
        ];
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes): array => [
            'expires_at' => now()->subDay(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'cancelled_at' => now(),
        ]);
    }
}
