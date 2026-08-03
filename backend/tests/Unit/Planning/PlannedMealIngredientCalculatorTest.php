<?php

use App\Models\PlannedMeal;
use App\Models\RecipeIngredient;
use App\Services\Planning\PlannedMealIngredientCalculator;

it('scales quantities and rounds to three decimals with half-up rounding', function (): void {
    $meal = new PlannedMeal(['initial_servings' => 6, 'servings' => 5]);
    $calculator = new PlannedMealIngredientCalculator;

    expect($calculator->quantity($meal, new RecipeIngredient(['quantity' => 1])))->toBe(0.833)
        ->and($calculator->quantity($meal, new RecipeIngredient(['quantity' => 1.001])))->toBe(0.834);
});

it('keeps units unchanged and leaves unitless quantities absent', function (): void {
    $meal = new PlannedMeal(['initial_servings' => 4, 'servings' => 2]);
    $calculator = new PlannedMealIngredientCalculator;
    $ingredient = new RecipeIngredient(['quantity' => 250, 'unit' => 'g']);
    $unitless = new RecipeIngredient(['quantity' => null, 'unit' => null]);

    expect($calculator->quantity($meal, $ingredient))->toBe(125.0)
        ->and($ingredient->unit)->toBe('g')
        ->and($calculator->quantity($meal, $unitless))->toBeNull();
});
