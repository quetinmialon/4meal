<?php

use App\Models\Cookbook;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function recipeFavoriteToken(User $user): string
{
    config()->set('jwt.secret', Str::repeat('a', 64));
    config()->set('jwt.issuer', 'http://localhost');
    config()->set('jwt.ttl', 900);

    return test()->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'password123',
    ])->json('data.access_token');
}

it('adds and removes a recipe favorite idempotently', function (): void {
    $user = User::factory()->create(['password' => 'password123']);
    $recipe = Recipe::factory()->create(['user_id' => $user->id, 'author_id' => $user->id]);
    $token = recipeFavoriteToken($user);

    $this->withToken($token)->postJson('/api/recipes/'.$recipe->public_id.'/favorite')->assertNoContent();
    $this->withToken($token)->postJson('/api/recipes/'.$recipe->public_id.'/favorite')->assertNoContent();

    expect($user->favoriteRecipes()->whereKey($recipe->id)->count())->toBe(1);

    $this->withToken($token)->getJson('/api/recipes/'.$recipe->public_id)
        ->assertJsonPath('data.is_favorite', true);

    $this->withToken($token)->deleteJson('/api/recipes/'.$recipe->public_id.'/favorite')->assertNoContent();
    $this->withToken($token)->deleteJson('/api/recipes/'.$recipe->public_id.'/favorite')->assertNoContent();

    expect($user->favoriteRecipes()->whereKey($recipe->id)->count())->toBe(0);
});

it('isolates favorites and is_favorite between users', function (): void {
    $owner = User::factory()->create(['password' => 'password123']);
    $otherUser = User::factory()->create(['password' => 'password123']);
    $cookbook = Cookbook::factory()->create(['owner_id' => $owner->id]);
    $cookbook->members()->attach($owner, ['role' => 'owner']);
    $cookbook->members()->attach($otherUser, ['role' => 'reader']);
    $recipe = Recipe::factory()->inCookbook($cookbook)->create(['author_id' => $owner->id]);
    $ownerToken = recipeFavoriteToken($owner);
    $otherToken = recipeFavoriteToken($otherUser);

    $this->withToken($ownerToken)->postJson('/api/recipes/'.$recipe->public_id.'/favorite')->assertNoContent();

    $this->withToken($ownerToken)->getJson('/api/recipes/'.$recipe->public_id)
        ->assertJsonPath('data.is_favorite', true);

    $this->withToken($otherToken)->getJson('/api/recipes/'.$recipe->public_id)
        ->assertJsonPath('data.is_favorite', false);

    $this->withToken($otherToken)->postJson('/api/recipes/'.$recipe->public_id.'/favorite')->assertNoContent();
    $this->withToken($otherToken)->getJson('/api/recipes/'.$recipe->public_id)
        ->assertJsonPath('data.is_favorite', true);

    $this->withToken($otherToken)->deleteJson('/api/recipes/'.$recipe->public_id.'/favorite')->assertNoContent();
    $this->withToken($ownerToken)->getJson('/api/recipes/'.$recipe->public_id)
        ->assertJsonPath('data.is_favorite', true);
    expect($owner->favoriteRecipes()->whereKey($recipe->id)->count())->toBe(1);
});

it('returns false when a user has not favorited an accessible recipe', function (): void {
    $user = User::factory()->create(['password' => 'password123']);
    $recipe = Recipe::factory()->create(['user_id' => $user->id, 'author_id' => $user->id]);

    $this->withToken(recipeFavoriteToken($user))
        ->getJson('/api/recipes/'.$recipe->public_id)
        ->assertJsonPath('data.is_favorite', false);
});
