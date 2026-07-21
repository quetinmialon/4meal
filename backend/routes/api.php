<?php

use App\Http\Controllers\Auth\GetCurrentUserController;
use App\Http\Controllers\Auth\LoginUserController;
use App\Http\Controllers\Auth\LogoutUserController;
use App\Http\Controllers\Auth\RefreshAccessTokenController;
use App\Http\Controllers\Auth\RegisterUserController;
use App\Http\Middleware\AuthenticateWithJwt;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;

Route::get('health', function () {
    return response()->json(['status' => 'ok']);
});

Route::post('auth/register', RegisterUserController::class)
    ->middleware('throttle:auth.register')
    ->name('auth.register');

Route::post('auth/login', LoginUserController::class)
    ->middleware('throttle:auth.login')
    ->name('auth.login');

Route::post('auth/logout', LogoutUserController::class)
    ->name('auth.logout');

Route::middleware(AuthenticateWithJwt::class)
    ->prefix('auth')
    ->group(function () {
        Route::get('me', GetCurrentUserController::class)
            ->name('auth.me');

        Route::post('refresh', RefreshAccessTokenController::class)
            ->name('auth.refresh');
    });

if (app()->environment('testing')) {
    Route::prefix('_test')->group(function () {
        Route::get('success', fn () => response()->json([
            'status' => 'ok',
        ]));

        Route::get('validation', function () {
            throw ValidationException::withMessages([
                'email' => [__('validation.required', [
                    'attribute' => __('validation.attributes.email'),
                ])],
            ]);
        });

        Route::get('authentication', function () {
            throw new AuthenticationException;
        });

        Route::get('authorization', function () {
            throw new AuthorizationException;
        });

        Route::get('server-error', function () {
            throw new RuntimeException('Boom');
        });

        Route::get('paginated', function () {
            return response()->json(new LengthAwarePaginator(
                items: [
                    ['id' => 3, 'name' => 'Third'],
                    ['id' => 4, 'name' => 'Fourth'],
                ],
                total: 5,
                perPage: 2,
                currentPage: 2,
                options: [
                    'path' => url('/api/_test/paginated'),
                ],
            ));
        });
    });
}
