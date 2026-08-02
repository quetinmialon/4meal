<?php

use App\Contracts\Auth\GoogleOAuthProvider;
use App\Data\Auth\GoogleProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function oauthManagementToken(User $user, string $password = 'password123'): string
{
    return test()->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => $password,
    ])->json('data.access_token');
}

it('lists only the authenticated user OAuth accounts without exposing secrets', function () {
    $user = User::factory()->create(['password' => 'password123']);
    $other = User::factory()->create(['password' => 'password123']);
    $user->oauthAccounts()->create([
        'provider' => 'google', 'provider_user_id' => 'user-google', 'provider_email' => 'user@example.com',
        'access_token' => 'secret-access', 'refresh_token' => 'secret-refresh',
    ]);
    $other->oauthAccounts()->create([
        'provider' => 'microsoft', 'provider_user_id' => 'other-ms', 'provider_email' => 'other@example.com',
        'access_token' => 'other-secret',
    ]);

    $this->withToken(oauthManagementToken($user))->getJson('/api/auth/oauth-accounts')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.provider', 'google')
        ->assertJsonMissing(['access_token' => 'secret-access'])
        ->assertJsonMissing(['refresh_token' => 'secret-refresh'])
        ->assertJsonMissing(['provider_user_id' => 'user-google']);
});

it('requires authentication for OAuth account management', function () {
    $this->getJson('/api/auth/oauth-accounts')->assertUnauthorized();
    $this->postJson('/api/auth/oauth/google/link')->assertUnauthorized();
    $this->deleteJson('/api/auth/oauth/google')->assertUnauthorized();
});

it('starts linking with an authenticated state bound to the current user', function () {
    config()->set('services.google.client_id', 'client-id');
    config()->set('services.google.client_secret', 'client-secret');
    $user = User::factory()->create(['password' => 'password123']);
    $token = oauthManagementToken($user);

    $response = $this->withToken($token)->get('/api/auth/oauth/google/link');

    $response->assertRedirect();
    expect($response->headers->get('Location'))->toContain('state=');
});

it('returns the provider authorization URL to authenticated JSON clients', function () {
    config()->set('services.microsoft.client_id', 'client-id');
    config()->set('services.microsoft.client_secret', 'client-secret');
    $user = User::factory()->create(['password' => 'password123']);

    $response = $this->withToken(oauthManagementToken($user))
        ->withHeader('Accept', 'application/json')
        ->getJson('/api/auth/oauth/microsoft/link')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->json('data.authorization_url');

    expect($response)->toContain('login.microsoftonline.com');
});

it('does not allow unlinking the only OAuth login method', function () {
    $user = User::factory()->create(['password' => 'password123']);
    $user->oauthAccounts()->create([
        'provider' => 'google', 'provider_user_id' => 'only-google', 'provider_email' => 'user@example.com',
        'access_token' => 'secret-access',
    ]);
    $token = oauthManagementToken($user);
    $user->update(['password_hash' => null]);

    $this->withToken($token)->deleteJson('/api/auth/oauth/google')
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'validation_error');

    expect($user->oauthAccounts()->count())->toBe(1);
});

it('allows unlinking OAuth when a password remains and prevents cross-user access', function () {
    $user = User::factory()->create(['password' => 'password123']);
    $other = User::factory()->create(['password' => 'password123']);
    $user->oauthAccounts()->create([
        'provider' => 'google', 'provider_user_id' => 'user-google', 'provider_email' => 'user@example.com',
        'access_token' => 'secret-access',
    ]);
    $other->oauthAccounts()->create([
        'provider' => 'microsoft', 'provider_user_id' => 'other-ms', 'provider_email' => 'other@example.com',
        'access_token' => 'other-secret',
    ]);

    $this->withToken(oauthManagementToken($user))->deleteJson('/api/auth/oauth/microsoft')->assertNotFound();
    $this->withToken(oauthManagementToken($user))->deleteJson('/api/auth/oauth/google')->assertOk();

    expect($user->oauthAccounts()->count())->toBe(0)
        ->and($other->oauthAccounts()->count())->toBe(1);
});

it('links a provider only to the authenticated user after a valid OAuth callback', function () {
    config()->set('services.google.frontend_url', 'http://frontend.test');
    config()->set('jwt.secret', str_repeat('a', 64));
    config()->set('jwt.issuer', 'http://localhost');
    config()->set('jwt.ttl', 900);
    $user = User::factory()->create(['password' => 'password123']);
    $token = oauthManagementToken($user);
    $redirect = $this->withToken($token)
        ->withHeader('Accept', 'text/html')
        ->get('/api/auth/oauth/google/link');
    parse_str((string) parse_url($redirect->headers->get('Location'), PHP_URL_QUERY), $query);

    $this->app->instance(GoogleOAuthProvider::class, new class implements GoogleOAuthProvider
    {
        public function authorizationUrl(string $state): string
        {
            return 'https://example.test/oauth?state='.$state;
        }

        public function profileFromCode(string $code): GoogleProfile
        {
            return new GoogleProfile('linked-google', 'Jane Doe', 'jane@example.com', true, 'access-token', 'refresh-token', 3600);
        }
    });

    $this->get('/api/auth/google/callback?state='.$query['state'].'&code=code')->assertRedirect();

    expect($user->oauthAccounts()->where('provider_user_id', 'linked-google')->exists())->toBeTrue();
    $this->assertDatabaseMissing('oauth_accounts', ['access_token' => 'access-token']);
});
