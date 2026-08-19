<?php

namespace Database\Factories;

use App\Models\Cookbook;
use App\Models\PlannedMeal;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<PlannedMeal> */
class PlannedMealFactory extends Factory
{
    protected $model = PlannedMeal::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'cookbook_id' => null,
            'recipe_id' => Recipe::factory(),
            'date' => $this->faker->dateTimeBetween('now', '+30 days')->format('Y-m-d'),
            'meal_type' => $this->faker->randomElement(['breakfast', 'lunch', 'dinner', 'snack']),
            'note' => null,
            'initial_servings' => 1,
            'servings' => 1,
            'recurrence_id' => null,
            'recurrence_frequency' => null,
            'recurrence_until' => null,
        ];
    }

    public function inCookbook(?Cookbook $cookbook = null): static
    {
        return $this->state(['user_id' => null, 'cookbook_id' => $cookbook?->getKey() ?? Cookbook::factory()]);
    }

    public function recurring(string $frequency = 'weekly'): static
    {
        return $this->state(['recurrence_id' => (string) Str::uuid(), 'recurrence_frequency' => $frequency, 'recurrence_until' => now()->addMonth()->toDateString()]);
    }
}
