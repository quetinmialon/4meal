<?php

use App\Models\Cookbook;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function browseRecipeToken(User $user): string
{
    config()->set('jwt.secret', Str::repeat('a', 64));
    config()->set('jwt.issuer', 'http://localhost');
    config()->set('jwt.ttl', 900);

    return test()->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'password123',
    ])->json('data.access_token');
}

it('lists only personal recipes and recipes from accessible cookbooks', function (): void {
    $user = User::factory()->create(['password' => 'password123']);
    $otherUser = User::factory()->create();
    $accessibleCookbook = Cookbook::factory()->create();
    $foreignCookbook = Cookbook::factory()->create();
    $accessibleCookbook->members()->attach($user, ['role' => 'reader']);

    Recipe::factory()->create(['user_id' => $user->id, 'author_id' => $user->id]);
    Recipe::factory()->inCookbook($accessibleCookbook)->create(['author_id' => $otherUser->id]);
    Recipe::factory()->create(['user_id' => $otherUser->id, 'author_id' => $otherUser->id]);
    Recipe::factory()->inCookbook($foreignCookbook)->create(['author_id' => $otherUser->id]);

    $response = $this->withToken(browseRecipeToken($user))
        ->getJson('/api/recipes?per_page=10');

    $response->assertOk()->assertJsonCount(2, 'data');
    expect(collect($response->json('data'))->pluck('id'))->toHaveCount(2);
});

it('paginates accessible recipes', function (): void {
    $user = User::factory()->create(['password' => 'password123']);
    Recipe::factory()->count(3)->create(['user_id' => $user->id, 'author_id' => $user->id]);

    $response = $this->withToken(browseRecipeToken($user))
        ->getJson('/api/recipes?per_page=2&page=2');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('meta.pagination.current_page', 2)
        ->assertJsonPath('meta.pagination.per_page', 2)
        ->assertJsonPath('meta.pagination.total', 3)
        ->assertJsonPath('meta.pagination.last_page', 2);
});

it('returns a recipe detail with all requested relations and its author', function (): void {
    $user = User::factory()->create(['password' => 'password123']);
    $recipe = Recipe::factory()->create(['user_id' => $user->id, 'author_id' => $user->id]);
    $recipe->ingredients()->create(['position' => 1, 'name' => 'Farine']);
    $recipe->steps()->create(['position' => 1, 'instruction' => 'Mélanger.']);
    $tag = $user->tags()->create(['name' => 'Dessert', 'slug' => 'dessert']);
    $recipe->tags()->attach($tag);

    $this->withToken(browseRecipeToken($user))
        ->getJson('/api/recipes/'.$recipe->public_id)
        ->assertOk()
        ->assertJsonPath('data.id', $recipe->public_id)
        ->assertJsonPath('data.author.id', $user->id)
        ->assertJsonCount(1, 'data.ingredients')
        ->assertJsonCount(1, 'data.steps')
        ->assertJsonPath('data.tags.0.slug', 'dessert');
});

it('forbids an external user from viewing a personal or cookbook recipe', function (): void {
    $owner = User::factory()->create(['password' => 'password123']);
    $external = User::factory()->create(['password' => 'password123']);
    $cookbook = Cookbook::factory()->create(['owner_id' => $owner->id]);
    $cookbook->members()->attach($owner, ['role' => 'owner']);
    $personal = Recipe::factory()->create(['user_id' => $owner->id, 'author_id' => $owner->id]);
    $shared = Recipe::factory()->inCookbook($cookbook)->create(['author_id' => $owner->id]);

    $token = browseRecipeToken($external);

    $this->withToken($token)->getJson('/api/recipes/'.$personal->public_id)->assertForbidden();
    $this->withToken($token)->getJson('/api/recipes/'.$shared->public_id)->assertForbidden();
    $this->withToken($token)->getJson('/api/recipes')->assertJsonCount(0, 'data');
});

it('loads recipe relations without one query per recipe', function (): void {
    $user = User::factory()->create(['password' => 'password123']);
    Recipe::factory()->count(5)->create(['user_id' => $user->id, 'author_id' => $user->id]);
    $token = browseRecipeToken($user);
    $queries = 0;

    DB::listen(function () use (&$queries): void {
        $queries++;
    });

    $this->withToken($token)->getJson('/api/recipes?per_page=5')->assertOk();

    expect($queries)->toBeLessThanOrEqual(8);
});
