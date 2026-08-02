<?php

namespace App\Http\Controllers\Auth;

use App\Contracts\Auth\MicrosoftOAuthProvider;
use App\Exceptions\Auth\AmbiguousOAuthAccountException;
use App\Exceptions\Auth\MicrosoftOAuthException;
use App\Exceptions\Auth\OAuthAccountAlreadyLinkedException;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\Auth\MicrosoftOAuthAuthenticator;
use App\Services\Auth\OAuthAccountLinker;
use App\Support\Jwt\AccessTokenIssuer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use JsonException;
use Throwable;

final class MicrosoftOAuthCallbackController extends Controller
{
    public function __construct(
        private readonly MicrosoftOAuthAuthenticator $authenticator,
        private readonly OAuthAccountLinker $linker,
        private readonly AccessTokenIssuer $accessTokenIssuer,
    ) {}

    public function __invoke(Request $request, MicrosoftOAuthProvider $provider): RedirectResponse
    {
        $state = $request->string('state')->toString();
        $stateData = $state === '' ? null : Cache::pull('oauth:microsoft:state:'.$state);
        if ($stateData !== true && ! is_array($stateData)) {
            return $this->failure('La requête de connexion Microsoft est invalide.');
        }
        if ($request->filled('error') || ! $request->filled('code')) {
            return $this->failure('La connexion avec Microsoft a été refusée.');
        }

        try {
            $profile = $provider->profileFromCode($request->string('code')->toString());
            if (is_array($stateData) && ($stateData['mode'] ?? null) === 'link') {
                $userId = $stateData['user_id'] ?? null;
                if (! is_int($userId) && ! (is_string($userId) && ctype_digit($userId))) {
                    throw new MicrosoftOAuthException('La requête de liaison Microsoft est invalide.');
                }
                $user = User::query()->findOrFail((int) $userId);
                $this->linker->link($user, $profile, 'microsoft');

                return redirect()->to($this->frontendUrl(true).'?oauth_linked=microsoft');
            }
            $user = $this->authenticator->authenticate($profile);
            $session = [
                ...$this->accessTokenIssuer->issue($user),
                'user' => UserResource::make($user)->resolve($request),
            ];

            return redirect()->to($this->frontendUrl().'?'.http_build_query([
                'access_token' => $session['access_token'], 'token_type' => $session['token_type'],
                'expires_in' => $session['expires_in'], 'user' => $this->encodeUser($session['user']),
            ]));
        } catch (AmbiguousOAuthAccountException|MicrosoftOAuthException|OAuthAccountAlreadyLinkedException $exception) {
            return $this->failure($exception->getMessage());
        } catch (Throwable $exception) {
            Log::error('Microsoft OAuth callback failed.', ['exception' => $exception]);

            return $this->failure('Impossible de finaliser la connexion avec Microsoft.');
        }
    }

    private function frontendUrl(bool $linked = false): string
    {
        return rtrim((string) config('services.microsoft.frontend_url'), '/').'/'.($linked ? 'profil' : 'connexion');
    }

    private function failure(string $message): RedirectResponse
    {
        return redirect()->to($this->frontendUrl().'?'.http_build_query(['oauth_error' => $message]));
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
