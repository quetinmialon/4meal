<?php

use App\Models\Cookbook;
use App\Models\PlannedMeal;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function plannedMealToken(User $user): string
{
    config()->set('jwt.secret', Str::repeat('a', 64));
    config()->set('jwt.issuer', 'http://localhost');
    config()->set('jwt.ttl', 900);

    return test()->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'password123',
    ])->json('data.access_token');
}

it('creates a personal planned meal using the user default servings', function (): void {
    $user = User::factory()->create(['password' => 'password123', 'default_servings' => 4]);
    $recipe = Recipe::factory()->create(['user_id' => $user->id, 'servings' => 4]);

    $response = $this->withToken(plannedMealToken($user))->postJson('/api/planned-meals', [
        'recipe_id' => $recipe->public_id,
        'date' => '2026-08-01',
        'meal_type' => 'dinner',
        'note' => 'Préparer la veille',
    ]);

    $response->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.date', '2026-08-01')
        ->assertJsonPath('data.meal_type', 'dinner')
        ->assertJsonPath('data.note', 'Préparer la veille')
        ->assertJsonPath('data.initial_servings', 4)
        ->assertJsonPath('data.servings', 4)
        ->assertJsonPath('data.recipe.id', $recipe->public_id);

    $this->assertDatabaseHas('planned_meals', [
        'user_id' => $user->id,
        'cookbook_id' => null,
        'recipe_id' => $recipe->id,
        'initial_servings' => 4,
    ]);
});

it('creates a cookbook planned meal only for a member with update permission', function (): void {
    $user = User::factory()->create(['password' => 'password123']);
    $cookbook = Cookbook::factory()->create();
    $cookbook->members()->attach($user, ['role' => 'editor']);
    $recipe = Recipe::factory()->inCookbook($cookbook)->create(['servings' => null]);

    $this->withToken(plannedMealToken($user))->postJson('/api/planned-meals', [
        'cookbook_id' => $cookbook->public_id,
        'recipe_id' => $recipe->public_id,
        'date' => '2026-08-02',
        'meal_type' => 'lunch',
    ])->assertCreated()->assertJsonPath('data.initial_servings', 1);

    expect(PlannedMeal::query()->firstOrFail()->cookbook_id)->toBe($cookbook->id);
});

it('rejects an invalid meal and unauthorized cookbook planning', function (): void {
    $owner = User::factory()->create(['password' => 'password123']);
    $other = User::factory()->create(['password' => 'password123']);
    $cookbook = Cookbook::factory()->create(['owner_id' => $owner->id]);
    $cookbook->members()->attach($owner, ['role' => 'owner']);
    $recipe = Recipe::factory()->create(['user_id' => $other->id]);

    $this->withToken(plannedMealToken($other))->postJson('/api/planned-meals', [
        'cookbook_id' => $cookbook->public_id,
        'recipe_id' => $recipe->public_id,
        'date' => 'not-a-date',
        'meal_type' => 'invalid',
    ])->assertUnprocessable();

    $this->withToken(plannedMealToken($other))->postJson('/api/planned-meals', [
        'cookbook_id' => $cookbook->public_id,
        'recipe_id' => $recipe->public_id,
        'date' => '2026-08-03',
        'meal_type' => 'dinner',
    ])->assertForbidden();

    $this->assertDatabaseCount('planned_meals', 0);
});
