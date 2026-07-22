<?php

namespace App\Http\Controllers\Auth;

use App\Contracts\Auth\MicrosoftOAuthProvider;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

final class MicrosoftOAuthRedirectController extends Controller
{
    public function __invoke(MicrosoftOAuthProvider $provider): RedirectResponse
    {
        abort_if(
            ! is_string(config('services.microsoft.client_id')) || config('services.microsoft.client_id') === '' ||
            ! is_string(config('services.microsoft.client_secret')) || config('services.microsoft.client_secret') === '',
            SymfonyResponse::HTTP_SERVICE_UNAVAILABLE,
            'La connexion Microsoft n’est pas configurée.',
        );

        $state = Str::random(64);
        Cache::put('oauth:microsoft:state:'.$state, true, now()->addMinutes(10));

        return redirect()->away($provider->authorizationUrl($state));
    }
}
