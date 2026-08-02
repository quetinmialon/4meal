<?php

use App\Contracts\Auth\MicrosoftOAuthProvider;
use App\Data\Auth\MicrosoftProfile;
use App\Models\OAuthAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

it('starts the Microsoft OAuth flow with a configured backend provider', function () {
    config()->set('services.microsoft.client_id', 'client-id');
    config()->set('services.microsoft.client_secret', 'client-secret');

    $this->get('/api/auth/microsoft/redirect')
        ->assertRedirect();
});

it('simulates the Microsoft callback and stores the oauth account', function () {
    config()->set('services.microsoft.frontend_url', 'http://frontend.test');
    config()->set('jwt.secret', str_repeat('a', 64));
    config()->set('jwt.issuer', 'http://localhost');
    config()->set('jwt.ttl', 900);

    $state = 'test-state';
    Cache::put('oauth:microsoft:state:'.$state, true, now()->addMinutes(10));
    $provider = new class implements MicrosoftOAuthProvider
    {
        public function authorizationUrl(string $state): string
        {
            return 'https://example.test/oauth?state='.$state;
        }

        public function profileFromCode(string $code): MicrosoftProfile
        {
            return new MicrosoftProfile('ms-user-123', 'Jane Doe', 'jane@example.com', true, 'access-token', 'refresh-token', 3600);
        }
    };
    $this->app->instance(MicrosoftOAuthProvider::class, $provider);

    $this->get('/api/auth/microsoft/callback?state='.$state.'&code=code')
        ->assertRedirect('http://frontend.test/connexion?oauth=success')
        ->assertCookie('4meal_access_token');

    expect(OAuthAccount::query()->where('provider', 'microsoft')->where('provider_user_id', 'ms-user-123')->exists())->toBeTrue();
});
