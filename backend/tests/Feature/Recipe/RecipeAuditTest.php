<?php

use App\Models\Recipe;
use App\Models\RecipeAudit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function auditToken(User $user): string
{
    config()->set('jwt.secret', Str::repeat('a', 64));
    config()->set('jwt.issuer', 'http://localhost');
    config()->set('jwt.ttl', 900);

    return test()->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'password123',
    ])->json('data.access_token');
}

it('records compact create and update snapshots and exposes them in reverse chronology', function (): void {
    $user = User::factory()->create(['password' => 'password123']);
    $token = auditToken($user);

    $created = $this->withToken($token)->postJson('/api/recipes', [
        'title' => 'Recette initiale',
        'ingredients' => [['name' => 'Farine']],
        'steps' => [['instruction' => 'Mélanger']],
    ])->assertCreated();
    $recipeId = $created->json('data.id');

    $this->withToken($token)->patchJson('/api/recipes/'.$recipeId, [
        'title' => 'Recette modifiée',
        'ingredients' => [['name' => 'Sucre']],
    ])->assertOk();

    $this->withToken($token)->getJson('/api/recipes/'.$recipeId.'/history')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.type', 'updated')
        ->assertJsonPath('data.0.author.id', $user->id)
        ->assertJsonPath('data.0.old_values.title', 'Recette initiale')
        ->assertJsonPath('data.0.new_values.title', 'Recette modifiée')
        ->assertJsonPath('data.0.old_values.ingredients_count', 1)
        ->assertJsonPath('data.0.new_values.ingredients_count', 1)
        ->assertJsonMissingPath('data.0.new_values.ingredients.0');
});

it('records deletion and keeps the history readable', function (): void {
    $user = User::factory()->create(['password' => 'password123']);
    $recipe = Recipe::factory()->create(['user_id' => $user->id, 'author_id' => $user->id]);
    $token = auditToken($user);

    $this->withToken($token)->deleteJson('/api/recipes/'.$recipe->public_id)->assertNoContent();

    $this->withToken($token)->getJson('/api/recipes/'.$recipe->public_id.'/history')
        ->assertOk()
        ->assertJsonPath('data.0.type', 'deleted')
        ->assertJsonPath('data.0.author.id', $user->id);

    expect(RecipeAudit::query()->where('recipe_id', $recipe->id)->count())->toBe(1);
});
