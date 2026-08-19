<?php

namespace Database\Factories;

use App\Models\Recipe;
use App\Models\RecipeStep;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<RecipeStep> */
class RecipeStepFactory extends Factory
{
    protected $model = RecipeStep::class;

    public function definition(): array
    {
        return [
            'recipe_id' => Recipe::factory(),
            'position' => $this->faker->unique()->numberBetween(1, 999999),
            'instruction' => $this->faker->randomElement(['Prechauffer le four a 180 degres.', 'Faire revenir les legumes dans l’huile.', 'Ajouter les ingredients et melanger.', 'Laisser mijoter jusqu’a obtenir une texture tendre.']),
            'duration_minutes' => $this->faker->numberBetween(1, 45),
            'image_path' => null,
        ];
    }

    public function atPosition(int $position): static
    {
        return $this->state(['position' => $position]);
    }
}
