<?php

use App\Models\Cookbook;
use App\Models\PlannedMeal;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function managePlannedMealToken(User $user): string
{
    config()->set('jwt.secret', Str::repeat('a', 64));
    config()->set('jwt.issuer', 'http://localhost');
    config()->set('jwt.ttl', 900);

    return test()->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'password123',
    ])->json('data.access_token');
}

it('updates a personal planned meal', function (): void {
    $user = User::factory()->create(['password' => 'password123']);
    $recipe = Recipe::factory()->create(['user_id' => $user->id]);
    $meal = PlannedMeal::factory()->create([
        'user_id' => $user->id, 'cookbook_id' => null, 'recipe_id' => $recipe->id,
        'date' => '2026-08-10', 'meal_type' => 'dinner', 'note' => null,
    ]);

    $this->withToken(managePlannedMealToken($user))
        ->patchJson('/api/planned-meals/'.$meal->public_id, [
            'date' => '2026-08-12', 'meal_type' => 'lunch', 'note' => 'À préparer le matin',
        ])
        ->assertOk()
        ->assertJsonPath('data.date', '2026-08-12')
        ->assertJsonPath('data.meal_type', 'lunch')
        ->assertJsonPath('data.note', 'À préparer le matin');

    $meal->refresh();
    expect($meal->date->format('Y-m-d'))->toBe('2026-08-12')
        ->and($meal->meal_type)->toBe('lunch')
        ->and($meal->note)->toBe('À préparer le matin');
});

it('updates and deletes a cookbook planned meal for an editor', function (): void {
    $user = User::factory()->create(['password' => 'password123']);
    $cookbook = Cookbook::factory()->create();
    $cookbook->members()->attach($user, ['role' => 'editor']);
    $recipe = Recipe::factory()->inCookbook($cookbook)->create();
    $meal = PlannedMeal::factory()->create([
        'user_id' => null, 'cookbook_id' => $cookbook->id, 'recipe_id' => $recipe->id,
        'date' => '2026-08-10',
    ]);

    $token = managePlannedMealToken($user);
    $this->withToken($token)
        ->patchJson('/api/planned-meals/'.$meal->public_id, ['date' => '2026-08-11'])
        ->assertOk()
        ->assertJsonPath('data.date', '2026-08-11');

    $this->withToken($token)
        ->deleteJson('/api/planned-meals/'.$meal->public_id)
        ->assertNoContent();

    $this->assertDatabaseMissing('planned_meals', ['id' => $meal->id]);
});

it('validates dates and forbids another user from changing or deleting a meal', function (): void {
    $owner = User::factory()->create();
    $other = User::factory()->create(['password' => 'password123']);
    $recipe = Recipe::factory()->create(['user_id' => $owner->id]);
    $meal = PlannedMeal::factory()->create([
        'user_id' => $owner->id, 'cookbook_id' => null, 'recipe_id' => $recipe->id,
    ]);

    $this->withToken(managePlannedMealToken($other))
        ->patchJson('/api/planned-meals/'.$meal->public_id, ['date' => '12/08/2026'])
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'validation_error');

    $this->withToken(managePlannedMealToken($other))
        ->patchJson('/api/planned-meals/'.$meal->public_id, ['date' => '2026-08-12'])
        ->assertForbidden();

    $this->withToken(managePlannedMealToken($other))
        ->deleteJson('/api/planned-meals/'.$meal->public_id)
        ->assertForbidden();

    $this->assertDatabaseHas('planned_meals', ['id' => $meal->id]);
});
