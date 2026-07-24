<?php

namespace Database\Factories;

use App\Models\Recipe;
use App\Models\RecipeIngredient;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<RecipeIngredient> */
class RecipeIngredientFactory extends Factory
{
    public function definition(): array
    {
        return [
            'recipe_id' => Recipe::factory(),
            'position' => 1,
            'name' => fake()->word(),
            'quantity' => fake()->randomFloat(3, 0.1, 10),
            'unit' => fake()->randomElement(['g', 'ml', 'pièce']),
            'preparation' => null,
            'is_optional' => false,
            'group_name' => null,
        ];
    }
}
