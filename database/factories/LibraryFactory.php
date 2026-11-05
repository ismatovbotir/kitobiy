<?php

namespace Database\Factories;

use App\Enums\LibraryRole;
use App\Models\Library;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Library>
 */
class LibraryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->lastName().' family library';

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'is_public' => false,
        ];
    }

    public function public(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_public' => true,
        ]);
    }

    /**
     * A library whose gift page is on.
     */
    public function shared(): static
    {
        return $this->state(fn (array $attributes): array => [
            'share_token' => Str::random(Library::SHARE_TOKEN_LENGTH),
        ]);
    }

    /**
     * Attach the given (or a new) user as owner.
     */
    public function ownedBy(?User $user = null): static
    {
        return $this->withMember($user, LibraryRole::Owner);
    }

    /**
     * Attach the given (or a new) user with the given role.
     */
    public function withMember(?User $user = null, LibraryRole $role = LibraryRole::Member): static
    {
        return $this->hasAttached($user ?? User::factory(), ['role' => $role->value], 'members');
    }
}
