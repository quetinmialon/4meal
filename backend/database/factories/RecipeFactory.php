<?php

namespace Database\Factories;

use App\Models\Cookbook;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Recipe> */
class RecipeFactory extends Factory
{
    public function definition(): array
    {
        $title = fake()->sentence(3);

        return [
            'user_id' => User::factory(),
            'cookbook_id' => null,
            'title' => $title,
            'slug' => Str::slug($title).'-'.fake()->unique()->numberBetween(1, 999999),
            'description' => fake()->optional()->paragraph(),
            'prep_time_minutes' => fake()->numberBetween(1, 60),
            'cook_time_minutes' => fake()->numberBetween(1, 120),
            'rest_time_minutes' => null,
            'servings' => fake()->numberBetween(1, 8),
            'visibility' => 'private',
            'difficulty' => 'easy',
        ];
    }

    public function inCookbook(?Cookbook $cookbook = null): static
    {
        return $this->state(fn (): array => [
            'user_id' => null,
            'cookbook_id' => $cookbook?->getKey() ?? Cookbook::factory(),
        ]);
    }
}
