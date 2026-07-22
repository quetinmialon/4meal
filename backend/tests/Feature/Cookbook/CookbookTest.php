<?php

use App\Models\Cookbook;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function cookbookToken(User $user): string
{
    config()->set('jwt.secret', Str::repeat('a', 64));
    config()->set('jwt.issuer', 'http://localhost');
    config()->set('jwt.ttl', 900);

    $response = test()->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'password123',
    ]);

    $response->assertOk();

    return $response->json('data.access_token');
}

it('creates a cookbook with its owner membership in one operation', function () {
    $user = User::factory()->create(['password' => 'password123']);

    $response = $this->withToken(cookbookToken($user))
        ->postJson('/api/cookbooks', ['name' => 'Mes recettes']);

    $response
        ->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.name', 'Mes recettes')
        ->assertJsonPath('data.owner.id', $user->id);

    $publicId = $response->json('data.id');

    expect($publicId)->toBeString()->not->toBe((string) Cookbook::query()->firstOrFail()->id);
    $this->assertDatabaseHas('cookbooks', ['public_id' => $publicId, 'owner_id' => $user->id]);
    $this->assertDatabaseHas('cookbook_members', [
        'cookbook_id' => Cookbook::query()->where('public_id', $publicId)->value('id'),
        'user_id' => $user->id,
        'role' => 'owner',
    ]);
});

it('requires a non-empty cookbook name', function () {
    $user = User::factory()->create(['password' => 'password123']);

    $this->withToken(cookbookToken($user))
        ->postJson('/api/cookbooks', ['name' => ' '])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'validation_error')
        ->assertJsonStructure(['error' => ['details' => ['fields' => ['name']]]]);
});

it('allows a member to access a cookbook and denies unrelated users', function () {
    $owner = User::factory()->create(['password' => 'password123']);
    $otherUser = User::factory()->create(['password' => 'password123']);
    $cookbook = Cookbook::query()->create(['name' => 'Partagé', 'owner_id' => $owner->id]);
    $cookbook->members()->attach($owner, ['role' => 'owner']);

    $this->withToken(cookbookToken($owner))
        ->getJson('/api/cookbooks/'.$cookbook->public_id)
        ->assertOk()
        ->assertJsonPath('data.id', $cookbook->public_id);

    $this->withToken(cookbookToken($otherUser))
        ->getJson('/api/cookbooks/'.$cookbook->public_id)
        ->assertForbidden()
        ->assertJsonPath('error.code', 'authorization_error');
});
