<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('revokes the current token and expires the authentication cookie', function (): void {
    config()->set('jwt.secret', Str::repeat('a', 64));
    config()->set('jwt.issuer', 'http://localhost');

    $user = User::factory()->create(['password' => 'password123']);
    $login = $this->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'password123',
    ])->assertOk();
    $token = $login->json('data.access_token');

    $this->withToken($token)
        ->postJson('/api/auth/logout')
        ->assertNoContent()
        ->assertCookieExpired('4meal_access_token');

    $this->withToken($token)
        ->getJson('/api/auth/me')
        ->assertUnauthorized();
});
