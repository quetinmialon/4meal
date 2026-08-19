<?php

namespace Database\Factories;

use App\Models\Recipe;
use App\Models\RecipeIngredient;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<RecipeIngredient> */
class RecipeIngredientFactory extends Factory
{
    protected $model = RecipeIngredient::class;

    public function definition(): array
    {
        return [
            'recipe_id' => Recipe::factory(),
            'position' => $this->faker->unique()->numberBetween(1, 999999),
            'name' => $this->faker->randomElement(['oignon', 'tomates', 'courgette', 'huile d\'olive', 'pois chiches', 'riz basmati', 'farine', 'oeufs']),
            'quantity' => $this->faker->randomFloat(3, 0.1, 800),
            'unit' => $this->faker->randomElement(['g', 'ml', 'piece', 'c. a soupe', 'c. a cafe']),
            'preparation' => $this->faker->optional(0.55)->randomElement(['emince', 'coupe en des', 'rape']),
            'is_optional' => $this->faker->boolean(15),
            'group_name' => $this->faker->optional(0.5)->randomElement(['Legumes', 'Sauce', 'Pate', 'Assaisonnement']),
        ];
    }

    public function atPosition(int $position): static
    {
        return $this->state(['position' => $position]);
    }
}
