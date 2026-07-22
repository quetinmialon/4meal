<?php

namespace App\Services\Auth;

use App\Contracts\Auth\MicrosoftOAuthProvider;
use App\Data\Auth\MicrosoftProfile;
use App\Exceptions\Auth\MicrosoftOAuthException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

final class MicrosoftOAuthClient implements MicrosoftOAuthProvider
{
    private function endpoint(string $path): string
    {
        return 'https://login.microsoftonline.com/'.rawurlencode((string) config('services.microsoft.tenant')).$path;
    }

    public function authorizationUrl(string $state): string
    {
        return $this->endpoint('/oauth2/v2.0/authorize?').http_build_query([
            'client_id' => config('services.microsoft.client_id'),
            'redirect_uri' => config('services.microsoft.redirect'),
            'response_type' => 'code',
            'scope' => 'openid profile email offline_access User.Read',
            'response_mode' => 'query',
            'state' => $state,
        ]);
    }

    public function profileFromCode(string $code): MicrosoftProfile
    {
        $token = Http::asForm()->post($this->endpoint('/oauth2/v2.0/token'), [
            'code' => $code,
            'client_id' => config('services.microsoft.client_id'),
            'client_secret' => config('services.microsoft.client_secret'),
            'redirect_uri' => config('services.microsoft.redirect'),
            'grant_type' => 'authorization_code',
            'scope' => 'openid profile email offline_access User.Read',
        ]);

        if (! $token->successful() || ! is_string($token->json('access_token'))) {
            throw new MicrosoftOAuthException('Microsoft n’a pas pu valider le code OAuth.');
        }

        $accessToken = (string) $token->json('access_token');
        $profile = Http::withToken($accessToken)->get('https://graph.microsoft.com/v1.0/me', [
            '$select' => 'id,displayName,mail,userPrincipalName',
        ]);

        if (! $profile->successful()) {
            throw new MicrosoftOAuthException('Microsoft n’a pas pu fournir le profil utilisateur.');
        }

        return $this->profileFromResponse(
            $profile,
            $accessToken,
            $token->json('refresh_token'),
            is_int($token->json('expires_in')) ? $token->json('expires_in') : null,
        );
    }

    private function profileFromResponse(Response $response, string $accessToken, mixed $refreshToken, ?int $expiresIn): MicrosoftProfile
    {
        $id = $response->json('id');
        $name = $response->json('displayName');
        $email = $response->json('mail') ?: $response->json('userPrincipalName');
        if (! is_string($id) || $id === '' || ! is_string($name) || $name === '' || ! is_string($email) || $email === '') {
            throw new MicrosoftOAuthException('Le profil Microsoft est incomplet.');
        }

        return new MicrosoftProfile($id, $name, mb_strtolower($email), true, $accessToken, is_string($refreshToken) ? $refreshToken : null, $expiresIn);
    }
}
