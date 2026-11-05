<?php

namespace Database\Factories;

use App\Models\Copy;
use App\Models\Loan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Loan>
 */
class LoanFactory extends Factory
{
    /**
     * Define the model's default state: a book lent to a person.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'copy_id' => Copy::factory(),
            'borrower_name' => fake()->firstName(),
        ];
    }

    public function overdue(): static
    {
        return $this->state(fn (array $attributes): array => [
            'due_on' => now(Loan::TIMEZONE)->subDays(3)->toDateString(),
        ]);
    }

    public function expiredLink(): static
    {
        return $this->state(fn (array $attributes): array => [
            'borrower_name' => null,
            'token' => fake()->regexify('[A-Za-z0-9]{64}'),
            'expires_at' => now()->subDay(),
        ]);
    }
}
