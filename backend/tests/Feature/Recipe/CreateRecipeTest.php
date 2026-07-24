<?php

use App\Models\Cookbook;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function recipeToken(User $user): string
{
    config()->set('jwt.secret', Str::repeat('a', 64));
    config()->set('jwt.issuer', 'http://localhost');
    config()->set('jwt.ttl', 900);

    return test()->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'password123',
    ])->json('data.access_token');
}

function recipePayload(): array
{
    return [
        'title' => 'Gratin de légumes',
        'prep_time_minutes' => 20,
        'cook_time_minutes' => 35,
        'servings' => 4,
        'source' => 'https://example.test/gratin',
        'ingredients' => [
            ['name' => 'Courgettes', 'quantity' => 2, 'unit' => 'pièces'],
            ['name' => 'Crème', 'quantity' => 20, 'unit' => 'cl', 'is_optional' => true],
        ],
        'steps' => [
            ['instruction' => 'Couper les légumes.'],
            ['instruction' => 'Cuire au four.', 'duration_minutes' => 35],
        ],
        'tags' => ['végétarien', 'rapide'],
    ];
}

it('creates a personal recipe with its nested content in one response', function (): void {
    $user = User::factory()->create(['password' => 'password123']);

    $response = $this->withToken(recipeToken($user))
        ->postJson('/api/recipes', recipePayload());

    $response->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.title', 'Gratin de légumes')
        ->assertJsonPath('data.prep_time_minutes', 20)
        ->assertJsonPath('data.cook_time_minutes', 35)
        ->assertJsonPath('data.servings', 4)
        ->assertJsonPath('data.source', 'https://example.test/gratin')
        ->assertJsonCount(2, 'data.ingredients')
        ->assertJsonPath('data.ingredients.0.position', 1)
        ->assertJsonPath('data.ingredients.1.position', 2)
        ->assertJsonCount(2, 'data.steps')
        ->assertJsonCount(2, 'data.tags');

    $recipe = Recipe::query()->where('title', 'Gratin de légumes')->firstOrFail();
    expect($recipe->user_id)->toBe($user->id)->and($recipe->cookbook_id)->toBeNull();
    $this->assertDatabaseCount('recipe_ingredients', 2);
    $this->assertDatabaseCount('recipe_steps', 2);
    $this->assertDatabaseCount('recipe_tag', 2);
});

it('creates a recipe in a cookbook when the user has update permission', function (): void {
    $user = User::factory()->create(['password' => 'password123']);
    $cookbook = Cookbook::factory()->create();
    $cookbook->members()->attach($user, ['role' => 'editor']);

    $payload = [...recipePayload(), 'cookbook_id' => $cookbook->public_id];
    $response = $this->withToken(recipeToken($user))->postJson('/api/recipes', $payload);

    $response->assertCreated();
    $recipe = Recipe::query()->where('title', 'Gratin de légumes')->firstOrFail();
    expect($recipe->cookbook_id)->toBe($cookbook->id)->and($recipe->user_id)->toBeNull();
});

it('rejects invalid nested recipe data', function (): void {
    $user = User::factory()->create(['password' => 'password123']);

    $this->withToken(recipeToken($user))
        ->postJson('/api/recipes', [
            'title' => ' ',
            'ingredients' => [['name' => '']],
            'steps' => [['instruction' => '']],
            'servings' => 0,
        ])
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'validation_error')
        ->assertJsonStructure(['error' => ['details' => ['fields' => [
            'title', 'ingredients.0.name', 'steps.0.instruction', 'servings',
        ]]]]);
});

it('rolls back the recipe and children when creation fails', function (): void {
    $user = User::factory()->create(['password' => 'password123']);
    $payload = recipePayload();
    $payload['ingredients'][1]['position'] = 1;

    $this->withToken(recipeToken($user))
        ->postJson('/api/recipes', $payload)
        ->assertServerError();

    $this->assertDatabaseCount('recipes', 0);
    $this->assertDatabaseCount('recipe_ingredients', 0);
    $this->assertDatabaseCount('recipe_steps', 0);
});

it('forbids a user without cookbook permission from creating there', function (): void {
    $owner = User::factory()->create(['password' => 'password123']);
    $otherUser = User::factory()->create(['password' => 'password123']);
    $cookbook = Cookbook::factory()->create(['owner_id' => $owner->id]);
    $cookbook->members()->attach($owner, ['role' => 'owner']);

    $this->withToken(recipeToken($otherUser))
        ->postJson('/api/recipes', [...recipePayload(), 'cookbook_id' => $cookbook->public_id])
        ->assertForbidden()
        ->assertJsonPath('error.code', 'authorization_error');

    $this->assertDatabaseCount('recipes', 0);
});
