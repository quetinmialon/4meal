<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

function changePasswordToken(User $user, string $password = 'password123'): string
{
    $response = test()->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => $password,
    ]);

    $response->assertOk();

    return $response->json('data.access_token');
}

it('changes the authenticated user password and invalidates existing sessions', function () {
    $user = User::factory()->create(['password' => 'password123']);
    $token = changePasswordToken($user);

    $response = $this->withToken($token)->putJson('/api/auth/password', [
        'current_password' => 'password123',
        'password' => 'new-password123',
        'password_confirmation' => 'new-password123',
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('success', true);

    expect(Hash::check('new-password123', $user->fresh()->password))->toBeTrue();

    $this->withToken($token)
        ->getJson('/api/auth/me')
        ->assertUnauthorized();

    $this->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'new-password123',
    ])->assertOk();
});

it('rejects a wrong current password', function () {
    $user = User::factory()->create(['password' => 'password123']);
    $token = changePasswordToken($user);

    $this->withToken($token)
        ->putJson('/api/auth/password', [
            'current_password' => 'wrong-password',
            'password' => 'new-password123',
            'password_confirmation' => 'new-password123',
        ])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'validation_error')
        ->assertJsonStructure(['error' => ['details' => ['fields' => ['current_password']]]]);
});

it('validates the new password', function () {
    $user = User::factory()->create(['password' => 'password123']);
    $token = changePasswordToken($user);

    $this->withToken($token)
        ->putJson('/api/auth/password', [
            'current_password' => 'password123',
            'password' => 'short',
            'password_confirmation' => 'different',
        ])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'validation_error')
        ->assertJsonStructure(['error' => ['details' => ['fields' => ['password']]]]);
});

it('rejects unauthenticated password changes', function () {
    $this->putJson('/api/auth/password', [
        'current_password' => 'password123',
        'password' => 'new-password123',
        'password_confirmation' => 'new-password123',
    ])
        ->assertUnauthorized()
        ->assertJsonPath('error.code', 'authentication_error');
});
