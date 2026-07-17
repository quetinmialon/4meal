<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('registers a user without authenticating them', function () {
    $response = $this->postJson('/api/auth/register', [
        'name' => 'Jane Doe',
        'email' => 'Jane.DOE@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.name', 'Jane Doe')
        ->assertJsonPath('data.email', 'jane.doe@example.com');

    $this->assertDatabaseHas('users', [
        'name' => 'Jane Doe',
        'email' => 'jane.doe@example.com',
    ]);

    $this->assertGuest();
});

it('validates the registration payload', function () {
    $response = $this->postJson('/api/auth/register', [
        'name' => ' ',
        'email' => 'not-an-email',
        'password' => 'short',
        'password_confirmation' => 'different',
    ]);

    $response
        ->assertStatus(422)
        ->assertJsonPath('success', false)
        ->assertJsonPath('error.code', 'validation_error')
        ->assertJsonStructure([
            'error' => [
                'details' => [
                    'fields' => ['name', 'email', 'password'],
                ],
            ],
        ]);
});

it('rejects duplicate emails regardless of case', function () {
    User::factory()->create([
        'email' => 'jane.doe@example.com',
    ]);

    $response = $this->postJson('/api/auth/register', [
        'name' => 'Jane Doe',
        'email' => 'Jane.Doe@Example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response
        ->assertStatus(422)
        ->assertJsonPath('success', false)
        ->assertJsonPath('error.code', 'validation_error')
        ->assertJsonStructure([
            'error' => [
                'details' => [
                    'fields' => ['email'],
                ],
            ],
        ]);
});

it('hashes the password before persisting the user', function () {
    $plainPassword = 'password123';

    $this->postJson('/api/auth/register', [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'password' => $plainPassword,
        'password_confirmation' => $plainPassword,
    ])->assertCreated();

    $user = User::query()->where('email', 'jane@example.com')->firstOrFail();

    expect($user->password)
        ->not->toBe($plainPassword)
        ->and(Hash::check($plainPassword, $user->password))->toBeTrue();
});
