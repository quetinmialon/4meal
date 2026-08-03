<?php

namespace App\Services\Planning;

use App\Models\Cookbook;
use App\Models\PlannedMeal;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreatePlannedMealAction
{
    /** @param array<string, mixed> $attributes */
    public function execute(User $user, Recipe $recipe, ?Cookbook $cookbook, array $attributes): PlannedMeal
    {
        return DB::transaction(function () use ($user, $recipe, $cookbook, $attributes): PlannedMeal {
            $meal = new PlannedMeal([
                'recipe_id' => $recipe->getKey(),
                'date' => $attributes['date'],
                'meal_type' => $attributes['meal_type'],
                'note' => $attributes['note'] ?? null,
                'initial_servings' => $recipe->servings ?? 1,
                'servings' => $attributes['servings'] ?? ($user->default_servings ?? 1),
            ]);

            $cookbook !== null
                ? $meal->cookbook()->associate($cookbook)
                : $meal->user()->associate($user);

            return tap($meal, fn (PlannedMeal $plannedMeal) => $plannedMeal->save())
                ->load(['recipe.ingredients', 'cookbook']);
        });
    }
}
