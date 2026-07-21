<?php

namespace App\Services\Auth;

use App\Contracts\Auth\GoogleOAuthProvider;
use App\Data\Auth\GoogleProfile;
use App\Exceptions\Auth\GoogleOAuthException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

final class GoogleOAuthClient implements GoogleOAuthProvider
{
    public function authorizationUrl(string $state): string
    {
        return 'https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query([
            'client_id' => config('services.google.client_id'),
            'redirect_uri' => config('services.google.redirect'),
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
            'access_type' => 'offline',
            'prompt' => 'select_account',
        ]);
    }

    public function profileFromCode(string $code): GoogleProfile
    {
        $tokenResponse = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'code' => $code,
            'client_id' => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
            'redirect_uri' => config('services.google.redirect'),
            'grant_type' => 'authorization_code',
        ]);

        if (! $tokenResponse->successful() || ! is_string($tokenResponse->json('access_token'))) {
            throw new GoogleOAuthException('Google n’a pas pu valider le code OAuth.');
        }

        $accessToken = (string) $tokenResponse->json('access_token');
        $userResponse = Http::withToken($accessToken)->get('https://openidconnect.googleapis.com/v1/userinfo');

        if (! $userResponse->successful()) {
            throw new GoogleOAuthException('Google n’a pas pu fournir le profil utilisateur.');
        }

        return $this->profileFromResponse(
            $userResponse,
            $accessToken,
            $tokenResponse->json('refresh_token'),
            is_int($tokenResponse->json('expires_in')) ? $tokenResponse->json('expires_in') : null,
        );
    }

    private function profileFromResponse(Response $response, string $accessToken, mixed $refreshToken, ?int $expiresIn): GoogleProfile
    {
        $providerId = $response->json('sub');
        $email = $response->json('email');
        $name = $response->json('name');
        $emailVerified = $response->json('email_verified');

        if (! is_string($providerId) || $providerId === '' || ! is_string($email) || $email === '' || ! is_string($name) || $name === '' || $emailVerified !== true) {
            throw new GoogleOAuthException('Le profil Google est incomplet ou son adresse e-mail n’est pas vérifiée.');
        }

        return new GoogleProfile(
            providerId: $providerId,
            name: $name,
            email: mb_strtolower($email),
            emailVerified: true,
            accessToken: $accessToken,
            refreshToken: is_string($refreshToken) ? $refreshToken : null,
            expiresIn: $expiresIn,
        );
    }
}
