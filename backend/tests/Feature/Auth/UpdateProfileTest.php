<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function profileToken(User $user, string $password = 'password123'): string
{
    return test()->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => $password,
    ])->assertOk()->json('data.access_token');
}

it('updates the authenticated user profile and returns the resource', function () {
    $user = User::factory()->create(['password' => 'password123']);

    $this->withToken(profileToken($user))->patchJson('/api/auth/me', [
        'name' => 'Jane Doe',
        'avatar_path' => 'avatars/jane.webp',
    ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Jane Doe')
        ->assertJsonPath('data.avatar_path', 'avatars/jane.webp')
        ->assertJsonMissingPath('data.password_hash');

    expect($user->fresh()->name)->toBe('Jane Doe');
});

it('requires the current password and resets verification when changing email', function () {
    $user = User::factory()->create([
        'email' => 'old@example.com',
        'password' => 'password123',
        'email_verified_at' => now(),
    ]);

    $this->withToken(profileToken($user))->patchJson('/api/auth/me', [
        'email' => 'New@Example.com',
    ])->assertStatus(422)
        ->assertJsonStructure(['error' => ['details' => ['fields' => ['current_password']]]]);

    $this->withToken(profileToken($user))->patchJson('/api/auth/me', [
        'email' => 'New@Example.com',
        'current_password' => 'password123',
    ])->assertOk()->assertJsonPath('data.email', 'new@example.com');

    expect($user->fresh()->email_verified_at)->toBeNull();
});

it('rejects an email already used by another user', function () {
    $user = User::factory()->create(['password' => 'password123']);
    User::factory()->create(['email' => 'taken@example.com']);

    $this->withToken(profileToken($user))->patchJson('/api/auth/me', [
        'email' => 'TAKEN@example.com',
        'current_password' => 'password123',
    ])->assertStatus(422)
        ->assertJsonPath('error.code', 'validation_error')
        ->assertJsonStructure(['error' => ['details' => ['fields' => ['email']]]]);
});

it('does not allow sensitive or unknown fields to be updated', function () {
    $user = User::factory()->create(['password' => 'password123']);

    $this->withToken(profileToken($user))->patchJson('/api/auth/me', [
        'password_hash' => 'compromised',
        'last_login_at' => now()->toISOString(),
    ])->assertOk();

    expect($user->fresh()->password_hash)->not->toBe('compromised')
        ->and($user->fresh()->last_login_at)->toBeNull();
});

it('rejects unauthenticated profile updates', function () {
    $this->patchJson('/api/auth/me', ['name' => 'Jane Doe'])
        ->assertUnauthorized()
        ->assertJsonPath('error.code', 'authentication_error');
});
