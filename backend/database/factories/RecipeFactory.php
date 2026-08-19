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
        $title = $this->faker->randomElement(['Gratin de courgettes au parmesan', 'Curry de pois chiches', 'Tarte fine aux tomates', 'Saumon roti au citron', 'Pates crèmeuses aux champignons', 'Salade de lentilles et feta', 'Soupe de potimarron', 'Clafoutis aux pommes']);

        return [
            'user_id' => User::factory(),
            'cookbook_id' => null,
            'title' => $title,
            'slug' => Str::slug($title).'-'.$this->faker->unique()->numberBetween(1, 999999),
            'description' => $this->faker->sentence(12),
            'prep_time_minutes' => $this->faker->numberBetween(1, 60),
            'cook_time_minutes' => $this->faker->numberBetween(1, 120),
            'rest_time_minutes' => null,
            'servings' => $this->faker->numberBetween(1, 8),
            'visibility' => 'private',
            'difficulty' => $this->faker->randomElement(['easy', 'medium', 'hard']),
            'notes' => $this->faker->optional(0.35)->sentence(10),
            'source' => $this->faker->optional(0.2)->url(),
        ];
    }

    public function inCookbook(?Cookbook $cookbook = null): static
    {
        return $this->state(fn (): array => [
            'user_id' => null,
            'cookbook_id' => $cookbook?->getKey() ?? Cookbook::factory(),
        ]);
    }

    public function published(): static
    {
        return $this->state(['visibility' => 'public']);
    }

    public function cookbookVisible(): static
    {
        return $this->state(['visibility' => 'cookbook']);
    }

    public function difficult(): static
    {
        return $this->state(['difficulty' => 'hard']);
    }
}
