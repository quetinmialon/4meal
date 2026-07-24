<?php

use App\Models\Cookbook;
use App\Models\Recipe;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function recipeFilterToken(User $user): string
{
    config()->set('jwt.secret', Str::repeat('a', 64));
    config()->set('jwt.issuer', 'http://localhost');
    config()->set('jwt.ttl', 900);

    return test()->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'password123',
    ])->json('data.access_token');
}

it('filters recipes by cookbook', function (): void {
    $user = User::factory()->create(['password' => 'password123']);
    $cookbook = Cookbook::factory()->create();
    $otherCookbook = Cookbook::factory()->create();
    $cookbook->members()->attach($user, ['role' => 'reader']);
    $selected = Recipe::factory()->inCookbook($cookbook)->create();
    Recipe::factory()->inCookbook($otherCookbook)->create();

    $this->withToken(recipeFilterToken($user))
        ->getJson('/api/recipes?cookbook_id='.$cookbook->public_id)
        ->assertOk()
        ->assertJsonPath('data.0.id', $selected->public_id)
        ->assertJsonCount(1, 'data');
});

it('filters recipes by tag slug or name', function (): void {
    $user = User::factory()->create(['password' => 'password123']);
    $tag = Tag::factory()->create(['user_id' => $user->id, 'name' => 'Petit déjeuner', 'slug' => 'petit-dejeuner']);
    $selected = Recipe::factory()->create(['user_id' => $user->id, 'author_id' => $user->id]);
    $selected->tags()->attach($tag);
    Recipe::factory()->create(['user_id' => $user->id, 'author_id' => $user->id]);

    $this->withToken(recipeFilterToken($user))
        ->getJson('/api/recipes?tag=PETIT-DEJEUNER')
        ->assertOk()
        ->assertJsonPath('data.0.id', $selected->public_id)
        ->assertJsonCount(1, 'data');
});

it('filters recipes by ingredient', function (): void {
    $user = User::factory()->create(['password' => 'password123']);
    $selected = Recipe::factory()->create(['user_id' => $user->id, 'author_id' => $user->id]);
    $selected->ingredients()->create(['position' => 1, 'name' => 'Tomates']);
    Recipe::factory()->create(['user_id' => $user->id, 'author_id' => $user->id])
        ->ingredients()->create(['position' => 1, 'name' => 'Courgettes']);

    $this->withToken(recipeFilterToken($user))
        ->getJson('/api/recipes?ingredient=tomates')
        ->assertOk()
        ->assertJsonPath('data.0.id', $selected->public_id)
        ->assertJsonCount(1, 'data');
});

it('filters recipes by maximum preparation time', function (): void {
    $user = User::factory()->create(['password' => 'password123']);
    $selected = Recipe::factory()->create(['user_id' => $user->id, 'author_id' => $user->id, 'prep_time_minutes' => 20]);
    Recipe::factory()->create(['user_id' => $user->id, 'author_id' => $user->id, 'prep_time_minutes' => 21]);

    $this->withToken(recipeFilterToken($user))
        ->getJson('/api/recipes?max_prep_time=20')
        ->assertOk()
        ->assertJsonPath('data.0.id', $selected->public_id)
        ->assertJsonCount(1, 'data');
});

it('filters recipes by maximum cooking time', function (): void {
    $user = User::factory()->create(['password' => 'password123']);
    $selected = Recipe::factory()->create(['user_id' => $user->id, 'author_id' => $user->id, 'cook_time_minutes' => 30]);
    Recipe::factory()->create(['user_id' => $user->id, 'author_id' => $user->id, 'cook_time_minutes' => 31]);

    $this->withToken(recipeFilterToken($user))
        ->getJson('/api/recipes?max_cook_time=30')
        ->assertOk()
        ->assertJsonPath('data.0.id', $selected->public_id)
        ->assertJsonCount(1, 'data');
});

it('filters recipes by the authenticated user favorites', function (): void {
    $user = User::factory()->create(['password' => 'password123']);
    $otherUser = User::factory()->create();
    $selected = Recipe::factory()->create(['user_id' => $user->id, 'author_id' => $user->id]);
    $notFavorite = Recipe::factory()->create(['user_id' => $user->id, 'author_id' => $user->id]);
    $user->favoriteRecipes()->attach($selected);
    $otherUser->favoriteRecipes()->attach($notFavorite);

    $this->withToken(recipeFilterToken($user))
        ->getJson('/api/recipes?favorites=true')
        ->assertOk()
        ->assertJsonPath('data.0.id', $selected->public_id)
        ->assertJsonCount(1, 'data');

    expect($notFavorite->fresh()->favoritedBy->contains($otherUser))->toBeTrue()
        ->and($notFavorite->fresh()->favoritedBy->contains($user))->toBeFalse();
});

it('does not bypass recipe authorization when filtering by an inaccessible cookbook', function (): void {
    $user = User::factory()->create(['password' => 'password123']);
    $owner = User::factory()->create();
    $foreignCookbook = Cookbook::factory()->create(['owner_id' => $owner->id]);
    Recipe::factory()->inCookbook($foreignCookbook)->create();

    $this->withToken(recipeFilterToken($user))
        ->getJson('/api/recipes?cookbook_id='.$foreignCookbook->public_id)
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('validates recipe list query parameters', function (string $query): void {
    $user = User::factory()->create(['password' => 'password123']);

    $this->withToken(recipeFilterToken($user))
        ->getJson('/api/recipes?'.$query)
        ->assertUnprocessable();
})->with([
    'invalid scope' => 'scope=unknown',
    'invalid cookbook id' => 'cookbook_id=not-a-uuid',
    'negative preparation time' => 'max_prep_time=-1',
    'too large cooking time' => 'max_cook_time=10081',
    'invalid favorites flag' => 'favorites=maybe',
    'empty tag' => 'tag=',
    'empty ingredient' => 'ingredient=',
]);

it('keeps recipe list relations eager loaded with filters', function (): void {
    $user = User::factory()->create(['password' => 'password123']);
    $tag = Tag::factory()->create(['user_id' => $user->id, 'name' => 'Rapide', 'slug' => 'rapide']);

    foreach (range(1, 5) as $position) {
        $recipe = Recipe::factory()->create(['user_id' => $user->id, 'author_id' => $user->id]);
        $recipe->tags()->attach($tag);
        $recipe->ingredients()->create(['position' => 1, 'name' => 'Tomates']);
        $recipe->steps()->create(['position' => 1, 'instruction' => 'Mélanger']);
    }

    $token = recipeFilterToken($user);
    $queries = 0;
    DB::listen(function () use (&$queries): void {
        $queries++;
    });

    $this->withToken($token)
        ->getJson('/api/recipes?tag=rapide&per_page=5')
        ->assertOk()
        ->assertJsonCount(5, 'data');

    expect($queries)->toBeLessThanOrEqual(9);
});
