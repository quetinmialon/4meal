<?php

namespace Database\Factories;

use App\Models\Recipe;
use App\Models\RecipeComment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<RecipeComment> */
class RecipeCommentFactory extends Factory
{
    protected $model = RecipeComment::class;

    public function definition(): array
    {
        return ['recipe_id' => Recipe::factory(), 'user_id' => User::factory(), 'parent_id' => null, 'content' => $this->faker->randomElement(['Très bonne recette, toute la famille a aimé !', 'Peut-on préparer la sauce la veille ?', 'La cuisson était parfaite, merci pour le partage.']), 'edited_at' => null];
    }

    public function reply(?RecipeComment $parent = null): static
    {
        return $this->state(['parent_id' => $parent?->getKey() ?? RecipeComment::factory()]);
    }

    public function edited(): static
    {
        return $this->state(['edited_at' => now()->subMinutes(5)]);
    }
}
