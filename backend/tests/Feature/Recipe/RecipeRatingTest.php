<?php

use App\Models\Cookbook;
use App\Models\Recipe;
use App\Models\RecipeRating;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function recipeRatingToken(User $user): string
{
    config()->set('jwt.secret', Str::repeat('a', 64));
    config()->set('jwt.issuer', 'http://localhost');
    config()->set('jwt.ttl', 900);

    return test()->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'password123',
    ])->json('data.access_token');
}

it('stores one personal rating per user and keeps it independent from cookbooks', function (): void {
    $owner = User::factory()->create(['password' => 'password123']);
    $member = User::factory()->create(['password' => 'password123']);
    $cookbook = Cookbook::factory()->create(['owner_id' => $owner->id]);
    $cookbook->members()->attach($owner, ['role' => 'owner']);
    $cookbook->members()->attach($member, ['role' => 'reader']);
    $recipe = Recipe::factory()->inCookbook($cookbook)->create(['author_id' => $owner->id]);
    $ownerToken = recipeRatingToken($owner);
    $memberToken = recipeRatingToken($member);

    $this->withToken($ownerToken)
        ->postJson('/api/recipes/'.$recipe->public_id.'/rating', ['rating' => 4])
        ->assertOk()
        ->assertJsonPath('data.rating', 4);

    $this->withToken($ownerToken)
        ->putJson('/api/recipes/'.$recipe->public_id.'/rating', ['rating' => 2])
        ->assertOk()
        ->assertJsonPath('data.rating', 2);

    $this->withToken($memberToken)
        ->postJson('/api/recipes/'.$recipe->public_id.'/rating', ['rating' => 5])
        ->assertOk();

    expect($recipe->ratings()->count())->toBe(2)
        ->and($owner->recipeRatings()->where('recipe_id', $recipe->id)->value('rating'))->toBe(2)
        ->and($member->recipeRatings()->where('recipe_id', $recipe->id)->value('rating'))->toBe(5);

    $this->withToken($ownerToken)
        ->getJson('/api/recipes/'.$recipe->public_id)
        ->assertJsonPath('data.personal_rating', 2);

    $this->withToken($memberToken)
        ->getJson('/api/recipes/'.$recipe->public_id)
        ->assertJsonPath('data.personal_rating', 5);

    $this->withToken($ownerToken)
        ->deleteJson('/api/recipes/'.$recipe->public_id.'/rating')
        ->assertNoContent();

    $this->withToken($ownerToken)
        ->deleteJson('/api/recipes/'.$recipe->public_id.'/rating')
        ->assertNoContent();

    expect($recipe->ratings()->count())->toBe(1);
});

it('returns a personal rating in recipe listings', function (): void {
    $user = User::factory()->create(['password' => 'password123']);
    $recipe = Recipe::factory()->create(['user_id' => $user->id, 'author_id' => $user->id]);
    RecipeRating::create(['user_id' => $user->id, 'recipe_id' => $recipe->id, 'rating' => 3]);

    $this->withToken(recipeRatingToken($user))
        ->getJson('/api/recipes?scope=mine')
        ->assertOk()
        ->assertJsonPath('data.0.personal_rating', 3);
});

it('validates personal ratings from one to five', function (): void {
    $user = User::factory()->create(['password' => 'password123']);
    $recipe = Recipe::factory()->create(['user_id' => $user->id, 'author_id' => $user->id]);

    $this->withToken(recipeRatingToken($user))
        ->postJson('/api/recipes/'.$recipe->public_id.'/rating', ['rating' => 0])
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'validation_error');

    expect($recipe->ratings()->count())->toBe(0);
});

it('enforces the user and recipe unique constraint', function (): void {
    $user = User::factory()->create();
    $recipe = Recipe::factory()->create(['user_id' => $user->id, 'author_id' => $user->id]);
    RecipeRating::create(['user_id' => $user->id, 'recipe_id' => $recipe->id, 'rating' => 4]);

    expect(fn (): RecipeRating => RecipeRating::create([
        'user_id' => $user->id,
        'recipe_id' => $recipe->id,
        'rating' => 5,
    ]))->toThrow(QueryException::class);
});
