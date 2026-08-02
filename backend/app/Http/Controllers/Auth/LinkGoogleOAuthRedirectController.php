<?php

namespace App\Http\Controllers\Auth;

use App\Contracts\Auth\GoogleOAuthProvider;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

final class LinkGoogleOAuthRedirectController extends Controller
{
    public function __invoke(Request $request, GoogleOAuthProvider $provider): RedirectResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            throw new AuthenticationException;
        }
        abort_if(! config('services.google.client_id') || ! config('services.google.client_secret'), SymfonyResponse::HTTP_SERVICE_UNAVAILABLE);
        $state = Str::random(64);
        Cache::put('oauth:google:state:'.$state, ['user_id' => $user->getKey(), 'mode' => 'link'], now()->addMinutes(10));

        return redirect()->away($provider->authorizationUrl($state));
    }
}
