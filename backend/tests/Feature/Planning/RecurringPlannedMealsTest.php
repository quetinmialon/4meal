<?php

use App\Models\PlannedMeal;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function recurringMealToken(User $user): string
{
    config()->set('jwt.secret', Str::repeat('a', 64));
    config()->set('jwt.issuer', 'http://localhost');
    config()->set('jwt.ttl', 900);

    return test()->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'password123',
    ])->json('data.access_token');
}

it('creates weekly occurrences through the explicit end date', function (): void {
    $user = User::factory()->create(['password' => 'password123']);
    $recipe = Recipe::factory()->create(['user_id' => $user->id]);

    $response = $this->withToken(recurringMealToken($user))->postJson('/api/planned-meals', [
        'recipe_id' => $recipe->public_id,
        'date' => '2026-08-03',
        'meal_type' => 'dinner',
        'recurrence' => ['frequency' => 'weekly', 'until' => '2026-08-24'],
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.date', '2026-08-03')
        ->assertJsonPath('data.recurrence.frequency', 'weekly')
        ->assertJsonPath('data.recurrence.until', '2026-08-24');

    expect(PlannedMeal::query()->count())->toBe(4)
        ->and(PlannedMeal::query()->pluck('date')->map(fn ($date) => $date->format('Y-m-d'))->all())
        ->toBe(['2026-08-03', '2026-08-10', '2026-08-17', '2026-08-24']);
});

it('rejects recurrence conflicts atomically', function (): void {
    $user = User::factory()->create(['password' => 'password123']);
    $recipe = Recipe::factory()->create(['user_id' => $user->id]);
    PlannedMeal::factory()->create([
        'user_id' => $user->id, 'cookbook_id' => null, 'recipe_id' => $recipe->id,
        'date' => '2026-08-17', 'meal_type' => 'dinner',
    ]);

    $this->withToken(recurringMealToken($user))->postJson('/api/planned-meals', [
        'recipe_id' => $recipe->public_id, 'date' => '2026-08-03', 'meal_type' => 'dinner',
        'recurrence' => ['frequency' => 'weekly', 'until' => '2026-08-24'],
    ])->assertStatus(409)->assertJsonPath('error.code', 'http_error');

    expect(PlannedMeal::query()->count())->toBe(1);
});

it('deletes one occurrence or the complete series', function (): void {
    $user = User::factory()->create(['password' => 'password123']);
    $recipe = Recipe::factory()->create(['user_id' => $user->id]);
    $token = recurringMealToken($user);

    $this->withToken($token)->postJson('/api/planned-meals', [
        'recipe_id' => $recipe->public_id, 'date' => '2026-08-03', 'meal_type' => 'dinner',
        'recurrence' => ['frequency' => 'weekly', 'until' => '2026-08-24'],
    ])->assertCreated();

    $occurrence = PlannedMeal::query()->whereDate('date', '2026-08-10')->firstOrFail();
    $this->withToken($token)->deleteJson('/api/planned-meals/'.$occurrence->public_id)->assertNoContent();
    expect(PlannedMeal::query()->count())->toBe(3);

    $remaining = PlannedMeal::query()->firstOrFail();
    $this->withToken($token)->deleteJson('/api/planned-meals/'.$remaining->public_id.'?scope=series')->assertNoContent();
    expect(PlannedMeal::query()->count())->toBe(0);
});

it('only accepts weekly recurrence with a bounded end date', function (): void {
    $user = User::factory()->create(['password' => 'password123']);
    $recipe = Recipe::factory()->create(['user_id' => $user->id]);

    $this->withToken(recurringMealToken($user))->postJson('/api/planned-meals', [
        'recipe_id' => $recipe->public_id, 'date' => '2026-08-03', 'meal_type' => 'dinner',
        'recurrence' => ['frequency' => 'daily', 'until' => '2026-08-24'],
    ])->assertUnprocessable();

    $this->withToken(recurringMealToken($user))->postJson('/api/planned-meals', [
        'recipe_id' => $recipe->public_id, 'date' => '2026-08-03', 'meal_type' => 'dinner',
        'recurrence' => ['frequency' => 'weekly', 'until' => '2027-09-01'],
    ])->assertUnprocessable();
});
