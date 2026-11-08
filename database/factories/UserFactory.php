<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'confirmed_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * A user who has not confirmed the account in the Telegram bot yet.
     */
    public function unconfirmed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'confirmed_at' => null,
        ]);
    }

    /**
     * A user with access to the /admin area.
     */
    public function superAdmin(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_super_admin' => true,
        ]);
    }
}
