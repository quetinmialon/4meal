<?php

use App\Models\Recipe;
use App\Models\SavedSearch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function savedSearchToken(User $user): string
{
    config()->set('jwt.secret', Str::repeat('a', 64));
    config()->set('jwt.issuer', 'http://localhost');
    config()->set('jwt.ttl', 900);

    return test()->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'password123',
    ])->json('data.access_token');
}

it('saves and lists validated searches for the authenticated user', function (): void {
    $user = User::factory()->create(['password' => 'password123']);
    $token = savedSearchToken($user);

    $create = $this->withToken($token)->postJson('/api/saved-searches', [
        'name' => 'Mes recettes rapides',
        'criteria' => [
            'q' => 'poulet',
            'max_prep_time' => 20,
            'favorites' => 'true',
        ],
    ]);

    $create->assertCreated()
        ->assertJsonPath('data.name', 'Mes recettes rapides')
        ->assertJsonPath('data.criteria.q', 'poulet')
        ->assertJsonPath('data.criteria.favorites', true);

    $this->withToken($token)->getJson('/api/saved-searches')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Mes recettes rapides');

    expect(SavedSearch::query()->where('user_id', $user->id)->count())->toBe(1);
});

it('rejects invalid or duplicate criteria names per user', function (): void {
    $user = User::factory()->create(['password' => 'password123']);
    $token = savedSearchToken($user);
    $payload = ['name' => 'Favoris', 'criteria' => ['favorites' => true]];

    $this->withToken($token)->postJson('/api/saved-searches', $payload)->assertCreated();
    $this->withToken($token)->postJson('/api/saved-searches', $payload)->assertUnprocessable();

    $this->withToken($token)->postJson('/api/saved-searches', [
        'name' => 'Invalide',
        'criteria' => ['unknown' => 'value'],
    ])->assertUnprocessable();
});

it('executes a saved search with its stored criteria', function (): void {
    $user = User::factory()->create(['password' => 'password123']);
    $match = Recipe::factory()->create([
        'user_id' => $user->id,
        'author_id' => $user->id,
        'title' => 'Poulet rapide',
        'prep_time_minutes' => 15,
    ]);
    Recipe::factory()->create([
        'user_id' => $user->id,
        'author_id' => $user->id,
        'title' => 'Poulet long',
        'prep_time_minutes' => 45,
    ]);
    $savedSearch = $user->savedSearches()->create([
        'name' => 'Rapide',
        'criteria' => ['q' => 'poulet', 'max_prep_time' => 20],
    ]);

    $this->withToken(savedSearchToken($user))
        ->getJson('/api/saved-searches/'.$savedSearch->public_id.'/execute')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $match->public_id)
        ->assertJsonPath('meta.pagination.total', 1);
});

it('does not expose or delete another user saved search', function (): void {
    $owner = User::factory()->create(['password' => 'password123']);
    $otherUser = User::factory()->create(['password' => 'password123']);
    $savedSearch = $owner->savedSearches()->create(['name' => 'Privée', 'criteria' => []]);
    $token = savedSearchToken($otherUser);

    $this->withToken($token)->getJson('/api/saved-searches')->assertJsonMissing(['id' => $savedSearch->public_id]);
    $this->withToken($token)->getJson('/api/saved-searches/'.$savedSearch->public_id.'/execute')->assertNotFound();
    $this->withToken($token)->deleteJson('/api/saved-searches/'.$savedSearch->public_id)->assertNotFound();
    expect($savedSearch->fresh())->not->toBeNull();
});

it('deletes a saved search', function (): void {
    $user = User::factory()->create(['password' => 'password123']);
    $savedSearch = $user->savedSearches()->create(['name' => 'À supprimer', 'criteria' => []]);

    $this->withToken(savedSearchToken($user))
        ->deleteJson('/api/saved-searches/'.$savedSearch->public_id)
        ->assertNoContent();

    expect($savedSearch->fresh())->toBeNull();
});
