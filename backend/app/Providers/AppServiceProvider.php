<?php

namespace App\Providers;

use App\Contracts\Auth\GoogleOAuthProvider;
use App\Contracts\Auth\MicrosoftOAuthProvider;
use App\Services\Auth\GoogleOAuthClient;
use App\Services\Auth\MicrosoftOAuthClient;
use App\Support\ApiResponse;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(GoogleOAuthProvider::class, GoogleOAuthClient::class);
        $this->app->bind(MicrosoftOAuthProvider::class, MicrosoftOAuthClient::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('auth.register', function (Request $request) {
            $email = Str::lower((string) $request->input('email', 'guest'));

            return Limit::perMinute(5)->by($email.'|'.$request->ip());
        });

        RateLimiter::for('auth.login', function (Request $request) {
            $email = Str::lower((string) $request->input('email', 'guest'));

            return Limit::perMinute(5)
                ->by($email.'|'.$request->ip())
                ->after(fn (SymfonyResponse $response): bool => $response->getStatusCode() === SymfonyResponse::HTTP_UNAUTHORIZED)
                ->response(fn (Request $request, array $headers) => ApiResponse::error(
                    $request,
                    'rate_limit_exceeded',
                    'Too many login attempts. Please try again later.',
                    SymfonyResponse::HTTP_TOO_MANY_REQUESTS,
                    [],
                    $headers,
                ));
        });

        RateLimiter::for('auth.password.email', function (Request $request) {
            $email = Str::lower((string) $request->input('email', 'guest'));

            return Limit::perMinute(3)->by($email.'|'.$request->ip());
        });

        RateLimiter::for('auth.password.reset', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        RateLimiter::for('chat.messages', function (Request $request) {
            $userId = $request->user()?->getAuthIdentifier() ?? 'guest';
            $cookbookId = (string) $request->route('cookbook');

            return Limit::perMinute(10)->by($userId.'|'.$cookbookId);
        });
    }
}
