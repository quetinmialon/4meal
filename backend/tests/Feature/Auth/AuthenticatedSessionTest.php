<?php

use App\Models\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function authenticateUser(User $user, string $password = 'password123'): string
{
    $response = test()->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => $password,
    ]);

    $response->assertOk();

    return $response->json('data.access_token');
}

it('returns the current authenticated user', function () {
    config()->set('jwt.secret', Str::repeat('a', 64));
    config()->set('jwt.issuer', 'http://localhost');
    config()->set('jwt.ttl', 900);

    $user = User::factory()->create([
        'name' => 'Jane Doe',
        'email' => 'jane.doe@example.com',
        'password' => 'password123',
    ]);

    $token = authenticateUser($user);

    $this->withToken($token)
        ->getJson('/api/auth/me')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.email', 'jane.doe@example.com');
});

it('rejects an expired token when retrieving the current user', function () {
    Carbon::setTestNow('2026-07-20 09:00:00');
    JWT::$timestamp = Carbon::now()->timestamp;

    try {
        config()->set('jwt.secret', Str::repeat('a', 64));
        config()->set('jwt.issuer', 'http://localhost');
        config()->set('jwt.ttl', 60);

        $user = User::factory()->create([
            'email' => 'jane.doe@example.com',
            'password' => 'password123',
        ]);

        $token = authenticateUser($user);

        Carbon::setTestNow(Carbon::now()->addSeconds(61));
        JWT::$timestamp = Carbon::now()->timestamp;

        $this->withToken($token)
            ->getJson('/api/auth/me')
            ->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'authentication_error')
            ->assertJsonPath('error.message', 'Une authentification est requise.');
    } finally {
        JWT::$timestamp = null;
        Carbon::setTestNow();
    }
});

it('rejects an invalid token when retrieving the current user', function () {
    $this->withToken('invalid-token')
        ->getJson('/api/auth/me')
        ->assertStatus(401)
        ->assertJsonPath('success', false)
        ->assertJsonPath('error.code', 'authentication_error')
        ->assertJsonPath('error.message', 'Une authentification est requise.');
});

it('refreshes a token and invalidates the previous one', function () {
    Carbon::setTestNow('2026-07-20 10:15:00');
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

        $oldToken = authenticateUser($user);

        Carbon::setTestNow(Carbon::now()->addMinutes(5));
        JWT::$timestamp = Carbon::now()->timestamp;

        $refreshResponse = $this->withToken($oldToken)
            ->postJson('/api/auth/refresh');

        $newToken = $refreshResponse->json('data.access_token');

        $refreshResponse
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.expires_in', 900)
            ->assertJsonPath('data.user.id', $user->id);

        expect($newToken)->toBeString()->not->toBe($oldToken);

        $oldClaims = JWT::decode($oldToken, new Key(Str::repeat('a', 64), 'HS256'));
        $newClaims = JWT::decode($newToken, new Key(Str::repeat('a', 64), 'HS256'));

        expect($newClaims->jti)->not->toBe($oldClaims->jti)
            ->and($newClaims->sub)->toBe((string) $user->id)
            ->and($newClaims->iat)->toBe(Carbon::now()->timestamp)
            ->and($newClaims->exp)->toBe(Carbon::now()->addSeconds(900)->timestamp);

        $this->withToken($oldToken)
            ->getJson('/api/auth/me')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'authentication_error');

        $this->withToken($newToken)
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id);
    } finally {
        JWT::$timestamp = null;
        Carbon::setTestNow();
    }
});
