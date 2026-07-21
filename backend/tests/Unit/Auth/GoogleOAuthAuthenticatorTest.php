<?php

use App\Data\Auth\GoogleProfile;
use App\Exceptions\Auth\AmbiguousOAuthAccountException;
use App\Exceptions\Auth\GoogleOAuthException;
use App\Models\OAuthAccount;
use App\Models\User;
use App\Services\Auth\GoogleOAuthAuthenticator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('app.key', 'base64:'.base64_encode(Str::repeat('a', 32)));
});

function googleProfile(array $overrides = []): GoogleProfile
{
    return new GoogleProfile(
        providerId: $overrides['providerId'] ?? 'google-user-123',
        name: $overrides['name'] ?? 'Jane Doe',
        email: $overrides['email'] ?? 'jane@example.com',
        emailVerified: $overrides['emailVerified'] ?? true,
        accessToken: $overrides['accessToken'] ?? 'access-token',
        refreshToken: $overrides['refreshToken'] ?? 'refresh-token',
        expiresIn: $overrides['expiresIn'] ?? 3600,
    );
}

it('creates a user and stores the Google account for a verified profile', function () {
    $user = app(GoogleOAuthAuthenticator::class)->authenticate(googleProfile());

    expect($user->email)->toBe('jane@example.com')
        ->and($user->email_verified_at)->not->toBeNull()
        ->and(OAuthAccount::query()->where('provider_user_id', 'google-user-123')->first())
        ->not->toBeNull();
});

it('reuses a linked Google account and refreshes its stored credentials', function () {
    $user = User::factory()->create(['email' => 'jane@example.com']);
    $account = $user->oauthAccounts()->create([
        'provider' => 'google',
        'provider_user_id' => 'google-user-123',
        'provider_email' => 'jane@example.com',
        'access_token' => 'old-access-token',
        'refresh_token' => 'old-refresh-token',
    ]);

    $authenticatedUser = app(GoogleOAuthAuthenticator::class)->authenticate(googleProfile([
        'accessToken' => 'new-access-token',
        'refreshToken' => 'new-refresh-token',
    ]));

    expect($authenticatedUser->is($user))->toBeTrue()
        ->and($account->fresh()->access_token)->toBe('new-access-token')
        ->and(OAuthAccount::query()->count())->toBe(1);
});

it('refuses to attach Google implicitly to an existing email account', function () {
    User::factory()->create(['email' => 'jane@example.com']);

    expect(fn () => app(GoogleOAuthAuthenticator::class)->authenticate(googleProfile()))
        ->toThrow(AmbiguousOAuthAccountException::class);

    expect(OAuthAccount::query()->count())->toBe(0);
});

it('refuses profiles whose Google email is not verified', function () {
    expect(fn () => app(GoogleOAuthAuthenticator::class)->authenticate(googleProfile([
        'emailVerified' => false,
    ])))->toThrow(GoogleOAuthException::class);
});
