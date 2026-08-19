<?php

namespace Database\Factories;

use App\Models\Recipe;
use App\Models\RecipeRating;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<RecipeRating> */
class RecipeRatingFactory extends Factory
{
    protected $model = RecipeRating::class;

    public function definition(): array
    {
        return ['user_id' => User::factory(), 'recipe_id' => Recipe::factory(), 'rating' => $this->faker->numberBetween(3, 5)];
    }

    public function low(): static
    {
        return $this->state(['rating' => $this->faker->numberBetween(1, 2)]);
    }
}
