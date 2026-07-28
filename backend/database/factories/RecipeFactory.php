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
        $title = $this->faker->sentence(3);

        return [
            'user_id' => User::factory(),
            'cookbook_id' => null,
            'title' => $title,
            'slug' => Str::slug($title).'-'.$this->faker->unique()->numberBetween(1, 999999),
            'description' => $this->faker->optional()->paragraph(),
            'prep_time_minutes' => $this->faker->numberBetween(1, 60),
            'cook_time_minutes' => $this->faker->numberBetween(1, 120),
            'rest_time_minutes' => null,
            'servings' => $this->faker->numberBetween(1, 8),
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
