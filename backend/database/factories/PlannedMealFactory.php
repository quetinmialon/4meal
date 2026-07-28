<?php

namespace Database\Factories;

use App\Models\PlannedMeal;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

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
            'date' => $this->faker->date('Y-m-d'),
            'meal_type' => $this->faker->randomElement(['breakfast', 'lunch', 'dinner', 'snack']),
            'note' => null,
            'initial_servings' => 1,
        ];
    }
}
