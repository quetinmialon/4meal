<?php

namespace App\Services\Planning;

use App\Models\PlannedMeal;
use App\Models\RecipeIngredient;

class PlannedMealIngredientCalculator
{
    public function quantity(PlannedMeal $meal, RecipeIngredient $ingredient): ?float
    {
        if ($ingredient->quantity === null) {
            return null;
        }

        // Keep the recipe immutable and expose at most the precision supported by
        // recipe quantities (three decimals), using conventional half-up rounding.
        return round(
            (float) $ingredient->quantity * $meal->servings / $meal->initial_servings,
            3,
            PHP_ROUND_HALF_UP,
        );
    }
}
