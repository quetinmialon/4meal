<?php

use App\Models\PlannedMeal;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function servingsToken(User $user): string
{
    config()->set('jwt.secret', Str::repeat('a', 64));
    config()->set('jwt.issuer', 'http://localhost');
    config()->set('jwt.ttl', 900);

    return test()->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'password123',
    ])->json('data.access_token');
}

it('returns scaled planned-meal ingredients without changing the recipe source', function (): void {
    $user = User::factory()->create(['password' => 'password123']);
    $recipe = Recipe::factory()->create(['user_id' => $user->id, 'servings' => 4]);
    $ingredient = $recipe->ingredients()->create([
        'position' => 1, 'name' => 'Farine', 'quantity' => 100, 'unit' => 'g',
    ]);

    $response = $this->withToken(servingsToken($user))->postJson('/api/planned-meals', [
        'recipe_id' => $recipe->public_id,
        'date' => '2026-08-03',
        'meal_type' => 'dinner',
        'servings' => 6,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.servings', 6)
        ->assertJsonPath('data.recipe.ingredients.0.quantity', 150)
        ->assertJsonPath('data.recipe.ingredients.0.unit', 'g');

    expect($ingredient->fresh()->quantity)->toBe('100.000')
        ->and(PlannedMeal::query()->firstOrFail()->initial_servings)->toBe(4);
});

it('updates portions and validates their bounds', function (): void {
    $user = User::factory()->create(['password' => 'password123']);
    $recipe = Recipe::factory()->create(['user_id' => $user->id, 'servings' => 4]);
    $meal = PlannedMeal::factory()->create([
        'user_id' => $user->id, 'recipe_id' => $recipe->id,
        'initial_servings' => 4, 'servings' => 4,
    ]);

    $this->withToken(servingsToken($user))
        ->patchJson('/api/planned-meals/'.$meal->public_id, ['servings' => 8])
        ->assertOk()
        ->assertJsonPath('data.servings', 8);

    $this->withToken(servingsToken($user))
        ->patchJson('/api/planned-meals/'.$meal->public_id, ['servings' => 0])
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'validation_error');
});
