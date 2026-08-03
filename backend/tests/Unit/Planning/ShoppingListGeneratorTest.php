<?php

use App\Models\PlannedMeal;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use App\Services\Planning\PlannedMealIngredientCalculator;
use App\Services\Planning\ShoppingListGenerator;

function shoppingMeal(array $ingredients, int $initialServings = 4, int $servings = 4): PlannedMeal
{
    $recipe = new Recipe(['title' => 'Test']);
    $recipe->setRelation('ingredients', collect($ingredients));

    $meal = new PlannedMeal(['initial_servings' => $initialServings, 'servings' => $servings]);
    $meal->setRelation('recipe', $recipe);

    return $meal;
}

it('collects ingredients, applies portions and aggregates compatible units', function (): void {
    $generator = new ShoppingListGenerator(new PlannedMealIngredientCalculator);
    $meals = [
        shoppingMeal([new RecipeIngredient(['name' => 'Farine', 'quantity' => 200, 'unit' => 'g'])]),
        shoppingMeal([new RecipeIngredient(['name' => 'farine', 'quantity' => 0.5, 'unit' => 'kg'])], 2, 4),
    ];

    expect($generator->generate($meals))->toBe([
        ['name' => 'Farine', 'quantity' => 1200.0, 'unit' => 'g', 'preparation' => null, 'is_optional' => false],
    ]);
});

it('keeps same ingredients separate when their units are incompatible', function (): void {
    $generator = new ShoppingListGenerator(new PlannedMealIngredientCalculator);
    $meal = shoppingMeal([
        new RecipeIngredient(['name' => 'Lait', 'quantity' => 200, 'unit' => 'ml']),
        new RecipeIngredient(['name' => 'Lait', 'quantity' => 1, 'unit' => 'pièce']),
    ]);

    expect($generator->generate([$meal]))->toHaveCount(2)
        ->and($generator->generate([$meal])[0]['quantity'])->toBe(200.0)
        ->and($generator->generate([$meal])[1]['quantity'])->toBe(1.0);
});

it('returns independent mutable items and does not merge unknown quantities', function (): void {
    $generator = new ShoppingListGenerator(new PlannedMealIngredientCalculator);
    $items = $generator->generate([shoppingMeal([
        new RecipeIngredient(['name' => 'Sel', 'quantity' => null, 'unit' => null]),
        new RecipeIngredient(['name' => 'Sel', 'quantity' => null, 'unit' => null]),
    ])]);

    $items[0]['quantity'] = 1.0;

    expect($items)->toHaveCount(2)
        ->and($items[1]['quantity'])->toBeNull();
});
