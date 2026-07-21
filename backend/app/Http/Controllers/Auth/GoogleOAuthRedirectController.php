<?php

namespace App\Http\Controllers\Auth;

use App\Contracts\Auth\GoogleOAuthProvider;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

final class GoogleOAuthRedirectController extends Controller
{
    public function __invoke(GoogleOAuthProvider $provider): RedirectResponse
    {
        abort_if(
            ! is_string(config('services.google.client_id')) || config('services.google.client_id') === '' ||
            ! is_string(config('services.google.client_secret')) || config('services.google.client_secret') === '',
            SymfonyResponse::HTTP_SERVICE_UNAVAILABLE,
            'La connexion Google n’est pas configurée.',
        );

        $state = Str::random(64);
        Cache::put('oauth:google:state:'.$state, true, now()->addMinutes(10));

        return redirect()->away($provider->authorizationUrl($state));
    }
}
