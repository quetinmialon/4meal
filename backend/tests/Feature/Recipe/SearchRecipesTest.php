<?php

use App\Models\Cookbook;
use App\Models\Recipe;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function searchRecipeToken(User $user): string
{
    config()->set('jwt.secret', Str::repeat('a', 64));
    config()->set('jwt.issuer', 'http://localhost');
    config()->set('jwt.ttl', 900);

    return test()->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'password123',
    ])->json('data.access_token');
}

it('searches title, ingredients, tags and step content and ranks title matches first', function (): void {
    $user = User::factory()->create(['password' => 'password123']);
    $titleMatch = Recipe::factory()->create([
        'user_id' => $user->id,
        'author_id' => $user->id,
        'title' => 'Curry de légumes',
    ]);
    $ingredientMatch = Recipe::factory()->create([
        'user_id' => $user->id,
        'author_id' => $user->id,
        'title' => 'Légumes rôtis',
    ]);
    $stepMatch = Recipe::factory()->create([
        'user_id' => $user->id,
        'author_id' => $user->id,
        'title' => 'Ragoût du soir',
    ]);

    $ingredientMatch->ingredients()->create(['position' => 1, 'name' => 'Curry doux']);
    $stepMatch->steps()->create(['position' => 1, 'instruction' => 'Ajouter le curry fumé.']);
    $tag = Tag::factory()->create(['user_id' => $user->id, 'name' => 'Curry', 'slug' => 'curry']);
    $stepMatch->tags()->attach($tag);

    $response = $this->withToken(searchRecipeToken($user))
        ->getJson('/api/recipes?q=curry&per_page=2');
    $response->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $titleMatch->public_id)
        ->assertJsonPath('meta.pagination.total', 3)
        ->assertJsonPath('meta.pagination.per_page', 2)
        ->assertJsonPath('meta.pagination.last_page', 2);
});

it('does not return recipes outside the authenticated user access scope', function (): void {
    $user = User::factory()->create(['password' => 'password123']);
    $foreignUser = User::factory()->create();
    $foreignCookbook = Cookbook::factory()->create();
    $foreignCookbook->members()->attach($foreignUser, ['role' => 'owner']);

    $personal = Recipe::factory()->create([
        'user_id' => $user->id,
        'author_id' => $user->id,
        'title' => 'Tarte isolée',
    ]);
    $hidden = Recipe::factory()->inCookbook($foreignCookbook)->create([
        'author_id' => $foreignUser->id,
        'title' => 'Tarte cachée',
    ]);

    $this->withToken(searchRecipeToken($user))
        ->getJson('/api/recipes?q=tarte')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $personal->public_id)
        ->assertJsonMissing(['id' => $hidden->public_id]);
});

it('combines all supplied filters with the text search', function (): void {
    $user = User::factory()->create(['password' => 'password123']);
    $cookbook = Cookbook::factory()->create(['owner_id' => $user->id]);
    $cookbook->members()->attach($user, ['role' => 'owner', 'joined_at' => now()]);
    $tag = Tag::factory()->create(['user_id' => $user->id, 'name' => 'Rapide', 'slug' => 'rapide']);

    $match = Recipe::factory()->inCookbook($cookbook)->create([
        'author_id' => $user->id,
        'title' => 'Poulet rapide',
        'prep_time_minutes' => 15,
        'cook_time_minutes' => 20,
    ]);
    $match->ingredients()->create(['position' => 1, 'name' => 'Poulet']);
    $match->tags()->attach($tag);

    Recipe::factory()->create([
        'user_id' => $user->id,
        'author_id' => $user->id,
        'title' => 'Poulet rapide mais long',
        'prep_time_minutes' => 45,
    ]);

    $this->withToken(searchRecipeToken($user))
        ->getJson('/api/recipes?q=poulet&cookbook_id='.$cookbook->public_id.'&tag=rapide&ingredient=poulet&max_prep_time=20')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $match->public_id);
});
