<?php

use App\Models\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('authenticates a user and returns a jwt access token', function () {
    Carbon::setTestNow('2026-07-17 08:30:00');
    JWT::$timestamp = Carbon::now()->timestamp;

    try {
        config()->set('jwt.secret', Str::repeat('a', 64));
        config()->set('jwt.issuer', 'http://localhost');
        config()->set('jwt.ttl', 900);

        $user = User::factory()->create([
            'name' => 'Jane Doe',
            'email' => 'jane.doe@example.com',
            'password' => 'password123',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'Jane.DOE@example.com',
            'password' => 'password123',
        ]);

        $token = $response->json('data.access_token');

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.expires_in', 900)
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.user.email', 'jane.doe@example.com');

        expect($token)->toBeString()->not->toBeEmpty();

        $decoded = JWT::decode($token, new Key(Str::repeat('a', 64), 'HS256'));

        expect($decoded->sub)->toBe((string) $user->id)
            ->and($decoded->iss)->toBe('http://localhost')
            ->and($decoded->iat)->toBe(Carbon::now()->timestamp)
            ->and($decoded->nbf)->toBe(Carbon::now()->timestamp)
            ->and($decoded->exp)->toBe(Carbon::now()->addSeconds(900)->timestamp);
    } finally {
        JWT::$timestamp = null;
        Carbon::setTestNow();
    }
});

it('returns a generic error message for a wrong password', function () {
    User::factory()->create([
        'email' => 'jane.doe@example.com',
        'password' => 'password123',
    ]);

    $response = $this->postJson('/api/auth/login', [
        'email' => 'jane.doe@example.com',
        'password' => 'wrong-password',
    ]);

    $response
        ->assertStatus(401)
        ->assertJsonPath('success', false)
        ->assertJsonPath('error.code', 'authentication_error')
        ->assertJsonPath('error.message', 'Identifiants invalides.');
});

it('returns the same generic error message for an unknown user', function () {
    $response = $this->postJson('/api/auth/login', [
        'email' => 'unknown@example.com',
        'password' => 'password123',
    ]);

    $response
        ->assertStatus(401)
        ->assertJsonPath('success', false)
        ->assertJsonPath('error.code', 'authentication_error')
        ->assertJsonPath('error.message', 'Identifiants invalides.');
});

it('validates the login payload', function () {
    $response = $this->postJson('/api/auth/login', [
        'email' => 'not-an-email',
        'password' => '',
    ]);

    $response
        ->assertStatus(422)
        ->assertJsonPath('success', false)
        ->assertJsonPath('error.code', 'validation_error')
        ->assertJsonStructure([
            'error' => [
                'details' => [
                    'fields' => ['email', 'password'],
                ],
            ],
        ]);
});
