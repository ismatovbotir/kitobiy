<?php

namespace Database\Factories;

use App\Enums\LibraryRole;
use App\Models\Library;
use App\Models\LibraryInvitation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<LibraryInvitation>
 */
class LibraryInvitationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'library_id' => Library::factory(),
            'invited_by' => User::factory(),
            'email' => fake()->safeEmail(),
            'token' => Str::random(LibraryInvitation::TOKEN_LENGTH),
            'role' => LibraryRole::Member,
            'expires_at' => now()->addDays(LibraryInvitation::LIFETIME_DAYS),
            'revoked_at' => null,
            'accepted_at' => null,
            'accepted_by' => null,
        ];
    }

    /**
     * A shareable link invitation, not addressed to any email.
     */
    public function link(): static
    {
        return $this->state(fn (array $attributes): array => [
            'email' => null,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes): array => [
            'expires_at' => now()->subMinute(),
        ]);
    }

    public function revoked(): static
    {
        return $this->state(fn (array $attributes): array => [
            'revoked_at' => now(),
        ]);
    }

    public function accepted(?User $user = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'accepted_at' => now(),
            'accepted_by' => $user ?? User::factory(),
        ]);
    }
}
