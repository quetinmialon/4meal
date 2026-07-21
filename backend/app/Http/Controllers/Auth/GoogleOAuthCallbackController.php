<?php

namespace App\Http\Controllers\Auth;

use App\Contracts\Auth\GoogleOAuthProvider;
use App\Exceptions\Auth\AmbiguousOAuthAccountException;
use App\Exceptions\Auth\GoogleOAuthException;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Services\Auth\GoogleOAuthAuthenticator;
use App\Support\Jwt\AccessTokenIssuer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use JsonException;
use Throwable;

final class GoogleOAuthCallbackController extends Controller
{
    public function __construct(
        private readonly GoogleOAuthAuthenticator $authenticator,
        private readonly AccessTokenIssuer $accessTokenIssuer,
    ) {}

    public function __invoke(Request $request, GoogleOAuthProvider $provider): RedirectResponse
    {
        $state = $request->string('state')->toString();

        if ($state === '' || Cache::pull('oauth:google:state:'.$state) !== true) {
            return $this->failure('La requête de connexion Google est invalide.');
        }

        if ($request->filled('error') || ! $request->filled('code')) {
            return $this->failure('La connexion avec Google a été refusée.');
        }

        try {
            $user = $this->authenticator->authenticate(
                $provider->profileFromCode($request->string('code')->toString()),
            );

            $session = [
                ...$this->accessTokenIssuer->issue($user),
                'user' => UserResource::make($user)->resolve($request),
            ];

            return redirect()->to($this->frontendUrl().'?'.http_build_query([
                'access_token' => $session['access_token'],
                'token_type' => $session['token_type'],
                'expires_in' => $session['expires_in'],
                'user' => $this->encodeUser($session['user']),
            ]));
        } catch (AmbiguousOAuthAccountException|GoogleOAuthException $exception) {
            return $this->failure($exception->getMessage());
        } catch (Throwable $exception) {
            Log::error('Google OAuth callback failed.', ['exception' => $exception]);

            return $this->failure('Impossible de finaliser la connexion avec Google.');
        }
    }

    private function frontendUrl(): string
    {
        return rtrim((string) config('services.google.frontend_url'), '/').'/connexion';
    }

    private function failure(string $message): RedirectResponse
    {
        return redirect()->to($this->frontendUrl().'?'.http_build_query([
            'oauth_error' => $message,
        ]));
    }

    /** @param array<string, mixed> $user */
    private function encodeUser(array $user): string
    {
        try {
            return json_encode($user, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return '{}';
        }
    }
}
