<?php

use App\Models\Cookbook;
use App\Models\PlannedMeal;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function listPlannedMealsToken(User $user): string
{
    config()->set('jwt.secret', Str::repeat('a', 64));
    config()->set('jwt.issuer', 'http://localhost');
    config()->set('jwt.ttl', 900);

    return test()->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'password123',
    ])->json('data.access_token');
}

it('returns only accessible meals inside the requested period with minimal recipe data', function (): void {
    $user = User::factory()->create(['password' => 'password123']);
    $other = User::factory()->create();
    $personalRecipe = Recipe::factory()->create(['user_id' => $user->id, 'title' => 'Soupe du lundi']);
    $otherRecipe = Recipe::factory()->create(['user_id' => $other->id, 'title' => 'Repas privé']);
    $cookbook = Cookbook::factory()->create();
    $cookbook->members()->attach($user, ['role' => 'reader']);
    $cookbookRecipe = Recipe::factory()->inCookbook($cookbook)->create(['title' => 'Gratin partagé']);

    PlannedMeal::factory()->create([
        'user_id' => $user->id, 'cookbook_id' => null, 'recipe_id' => $personalRecipe->id,
        'date' => '2026-08-10', 'meal_type' => 'dinner',
    ]);
    PlannedMeal::factory()->create([
        'user_id' => null, 'cookbook_id' => $cookbook->id, 'recipe_id' => $cookbookRecipe->id,
        'date' => '2026-08-11', 'meal_type' => 'lunch',
    ]);
    PlannedMeal::factory()->create([
        'user_id' => $other->id, 'cookbook_id' => null, 'recipe_id' => $otherRecipe->id,
        'date' => '2026-08-11', 'meal_type' => 'dinner',
    ]);
    PlannedMeal::factory()->create([
        'user_id' => $user->id, 'cookbook_id' => null, 'recipe_id' => $personalRecipe->id,
        'date' => '2026-09-01', 'meal_type' => 'dinner',
    ]);

    $this->withToken(listPlannedMealsToken($user))
        ->getJson('/api/planned-meals?from=2026-08-10&to=2026-08-11')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.recipe.title', 'Soupe du lundi')
        ->assertJsonPath('data.0.recipe.id', $personalRecipe->public_id)
        ->assertJsonPath('data.1.recipe.title', 'Gratin partagé')
        ->assertJsonMissingPath('data.0.recipe.ingredients');
});

it('rejects an invalid or overly long period', function (string $query): void {
    $user = User::factory()->create(['password' => 'password123']);

    $this->withToken(listPlannedMealsToken($user))
        ->getJson('/api/planned-meals?'.$query)
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'validation_error');
})->with([
    'reversed period' => 'from=2026-08-12&to=2026-08-10',
    'period over 31 days' => 'from=2026-08-01&to=2026-09-01',
    'malformed date' => 'from=2026-08-01&to=not-a-date',
]);

it('does not return a cookbook meal to a user who is not a member', function (): void {
    $owner = User::factory()->create();
    $outsider = User::factory()->create(['password' => 'password123']);
    $cookbook = Cookbook::factory()->create(['owner_id' => $owner->id]);
    $cookbook->members()->attach($owner, ['role' => 'owner']);
    $recipe = Recipe::factory()->inCookbook($cookbook)->create();
    PlannedMeal::factory()->create([
        'user_id' => null, 'cookbook_id' => $cookbook->id, 'recipe_id' => $recipe->id,
        'date' => '2026-08-10',
    ]);

    $this->withToken(listPlannedMealsToken($outsider))
        ->getJson('/api/planned-meals?from=2026-08-10&to=2026-08-10')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});
