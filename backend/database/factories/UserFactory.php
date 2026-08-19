<?php

namespace Database\Factories;

use App\Enums\Diet;
use App\Enums\Theme;
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
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'email_verified_at' => now(),
            'two_factor_enabled' => false,
            'diet' => null,
            'allergies' => [],
            'default_servings' => 2,
            'theme' => Theme::Light->value,
            'password' => static::$password ??= Hash::make('password'),
            'remember_token_hash' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function oauthOnly(): static
    {
        return $this->state(['password' => null, 'password_hash' => null]);
    }

    public function withTwoFactor(): static
    {
        return $this->state(['two_factor_enabled' => true]);
    }

    public function withFoodPreferences(?Diet $diet = null): static
    {
        return $this->state([
            'diet' => ($diet ?? $this->faker->randomElement(Diet::cases()))->value,
            'allergies' => $this->faker->randomElements(['gluten', 'lactose', 'arachides', 'fruits_a_coque'], $this->faker->numberBetween(1, 2)),
            'default_servings' => $this->faker->numberBetween(1, 6),
        ]);
    }

    public function darkMode(): static
    {
        return $this->state(['theme' => Theme::Dark->value]);
    }
}
