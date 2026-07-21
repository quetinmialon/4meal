<?php

use App\Exceptions\Auth\GoogleOAuthException;
use App\Services\Auth\GoogleOAuthClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

it('exchanges the authorization code and maps the verified Google profile', function () {
    config()->set('services.google.client_id', 'client-id');
    config()->set('services.google.client_secret', 'client-secret');
    config()->set('services.google.redirect', 'http://localhost/api/auth/google/callback');

    Http::fake([
        'https://oauth2.googleapis.com/token' => Http::response([
            'access_token' => 'google-access-token',
            'refresh_token' => 'google-refresh-token',
            'expires_in' => 3600,
        ]),
        'https://openidconnect.googleapis.com/v1/userinfo' => Http::response([
            'sub' => 'google-user-123',
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'email_verified' => true,
        ]),
    ]);

    $profile = app(GoogleOAuthClient::class)->profileFromCode('authorization-code');

    expect($profile->providerId)->toBe('google-user-123')
        ->and($profile->email)->toBe('jane@example.com')
        ->and($profile->accessToken)->toBe('google-access-token')
        ->and($profile->refreshToken)->toBe('google-refresh-token');

    Http::assertSent(fn ($request) => $request->url() === 'https://oauth2.googleapis.com/token'
        && $request['code'] === 'authorization-code'
        && $request['client_secret'] === 'client-secret');
});

it('rejects an unverified Google profile', function () {
    Http::fake([
        'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'token']),
        'https://openidconnect.googleapis.com/v1/userinfo' => Http::response([
            'sub' => 'google-user-123',
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'email_verified' => false,
        ]),
    ]);

    expect(fn () => app(GoogleOAuthClient::class)->profileFromCode('authorization-code'))
        ->toThrow(GoogleOAuthException::class);
});
