<?php

use App\Exceptions\Auth\MicrosoftOAuthException;
use App\Services\Auth\MicrosoftOAuthClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

it('exchanges the authorization code and maps the Microsoft Graph profile', function () {
    config()->set('services.microsoft.client_id', 'client-id');
    config()->set('services.microsoft.client_secret', 'client-secret');
    config()->set('services.microsoft.tenant', 'common');
    config()->set('services.microsoft.redirect', 'http://localhost/api/auth/microsoft/callback');

    Http::fake([
        'https://login.microsoftonline.com/common/oauth2/v2.0/token' => Http::response([
            'access_token' => 'ms-access-token', 'refresh_token' => 'ms-refresh-token', 'expires_in' => 3600,
        ]),
        'https://graph.microsoft.com/v1.0/me*' => Http::response([
            'id' => 'ms-user-123', 'displayName' => 'Jane Doe', 'mail' => 'jane@example.com',
        ]),
    ]);

    $profile = app(MicrosoftOAuthClient::class)->profileFromCode('authorization-code');

    expect($profile->providerId)->toBe('ms-user-123')
        ->and($profile->email)->toBe('jane@example.com')
        ->and($profile->accessToken)->toBe('ms-access-token')
        ->and($profile->refreshToken)->toBe('ms-refresh-token');

    Http::assertSent(fn ($request) => $request->url() === 'https://login.microsoftonline.com/common/oauth2/v2.0/token'
        && $request['code'] === 'authorization-code'
        && $request['client_secret'] === 'client-secret');
});

it('uses the Microsoft user principal name when Graph has no mail', function () {
    Http::fake([
        'https://login.microsoftonline.com/common/oauth2/v2.0/token' => Http::response(['access_token' => 'token']),
        'https://graph.microsoft.com/v1.0/me*' => Http::response([
            'id' => 'ms-user-123', 'displayName' => 'Jane Doe', 'userPrincipalName' => 'jane@tenant.example',
        ]),
    ]);

    expect(app(MicrosoftOAuthClient::class)->profileFromCode('code')->email)->toBe('jane@tenant.example');
});

it('rejects an incomplete Microsoft profile', function () {
    Http::fake([
        'https://login.microsoftonline.com/common/oauth2/v2.0/token' => Http::response(['access_token' => 'token']),
        'https://graph.microsoft.com/v1.0/me*' => Http::response(['id' => 'ms-user-123']),
    ]);

    expect(fn () => app(MicrosoftOAuthClient::class)->profileFromCode('code'))
        ->toThrow(MicrosoftOAuthException::class);
});
