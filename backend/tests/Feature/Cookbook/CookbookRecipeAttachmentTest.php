<?php

use App\Models\Cookbook;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function cookbookRecipeToken(User $user): string
{
    config()->set('jwt.secret', Str::repeat('a', 64));
    config()->set('jwt.issuer', 'http://localhost');
    config()->set('jwt.ttl', 900);

    return test()->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'password123',
    ])->json('data.access_token');
}

it('adds an existing personal recipe to a cookbook idempotently', function (): void {
    $owner = User::factory()->create(['password' => 'password123']);
    $cookbook = Cookbook::factory()->create(['owner_id' => $owner->id]);
    $cookbook->members()->attach($owner, ['role' => 'owner']);
    $recipe = Recipe::factory()->create(['user_id' => $owner->id, 'author_id' => $owner->id]);
    $token = cookbookRecipeToken($owner);

    $this->withToken($token)
        ->postJson('/api/cookbooks/'.$cookbook->public_id.'/recipes/'.$recipe->public_id)
        ->assertNoContent();
    $this->withToken($token)
        ->postJson('/api/cookbooks/'.$cookbook->public_id.'/recipes/'.$recipe->public_id)
        ->assertNoContent();

    $this->assertDatabaseCount('cookbook_recipe', 1);
    $this->withToken($token)
        ->getJson('/api/cookbooks/'.$cookbook->public_id.'/recipes')
        ->assertOk()
        ->assertJsonPath('data.0.id', $recipe->public_id);
});

it('removes an existing recipe from a cookbook idempotently', function (): void {
    $owner = User::factory()->create(['password' => 'password123']);
    $cookbook = Cookbook::factory()->create(['owner_id' => $owner->id]);
    $cookbook->members()->attach($owner, ['role' => 'owner']);
    $recipe = Recipe::factory()->create(['user_id' => $owner->id, 'author_id' => $owner->id]);
    $cookbook->linkedRecipes()->attach($recipe);
    $token = cookbookRecipeToken($owner);

    $this->withToken($token)
        ->deleteJson('/api/cookbooks/'.$cookbook->public_id.'/recipes/'.$recipe->public_id)
        ->assertNoContent();
    $this->withToken($token)
        ->deleteJson('/api/cookbooks/'.$cookbook->public_id.'/recipes/'.$recipe->public_id)
        ->assertNoContent();

    $this->assertDatabaseMissing('cookbook_recipe', [
        'cookbook_id' => $cookbook->id,
        'recipe_id' => $recipe->id,
    ]);
    $this->withToken($token)
        ->getJson('/api/recipes/'.$recipe->public_id)
        ->assertOk();
});
