<?php

namespace Database\Factories;

use App\Models\Recipe;
use App\Models\RecipeAudit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<RecipeAudit> */
class RecipeAuditFactory extends Factory
{
    protected $model = RecipeAudit::class;

    public function definition(): array
    {
        return ['recipe_id' => Recipe::factory(), 'actor_id' => User::factory(), 'type' => RecipeAudit::CREATED, 'old_values' => null, 'new_values' => ['title' => 'Nouvelle recette'], 'created_at' => now()];
    }

    public function updated(): static
    {
        return $this->state(['type' => RecipeAudit::UPDATED, 'old_values' => ['title' => 'Ancien titre'], 'new_values' => ['title' => 'Nouveau titre']]);
    }

    public function deleted(): static
    {
        return $this->state(['type' => RecipeAudit::DELETED, 'new_values' => null]);
    }
}
