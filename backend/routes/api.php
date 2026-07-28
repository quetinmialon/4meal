<?php

use App\Http\Controllers\Auth\ChangePasswordController;
use App\Http\Controllers\Auth\GetCurrentUserController;
use App\Http\Controllers\Auth\GoogleOAuthCallbackController;
use App\Http\Controllers\Auth\GoogleOAuthRedirectController;
use App\Http\Controllers\Auth\LoginUserController;
use App\Http\Controllers\Auth\MicrosoftOAuthCallbackController;
use App\Http\Controllers\Auth\MicrosoftOAuthRedirectController;
use App\Http\Controllers\Auth\RefreshAccessTokenController;
use App\Http\Controllers\Auth\RegisterUserController;
use App\Http\Controllers\Cookbook\AcceptCookbookInvitationByIdController;
use App\Http\Controllers\Cookbook\AcceptCookbookInvitationController;
use App\Http\Controllers\Cookbook\AddCookbookRecipeController;
use App\Http\Controllers\Cookbook\CreateCookbookController;
use App\Http\Controllers\Cookbook\CreateCookbookInvitationController;
use App\Http\Controllers\Cookbook\CreateCookbookMessageController;
use App\Http\Controllers\Cookbook\DeclineCookbookInvitationController;
use App\Http\Controllers\Cookbook\DeleteCookbookController;
use App\Http\Controllers\Cookbook\LeaveCookbookController;
use App\Http\Controllers\Cookbook\ListCookbookInvitationsController;
use App\Http\Controllers\Cookbook\ListCookbookMembersController;
use App\Http\Controllers\Cookbook\ListCookbookMessagesController;
use App\Http\Controllers\Cookbook\ListCookbookRecipesController;
use App\Http\Controllers\Cookbook\ListCookbooksController;
use App\Http\Controllers\Cookbook\ListLatestCookbookMessagesController;
use App\Http\Controllers\Cookbook\RemoveCookbookMemberController;
use App\Http\Controllers\Cookbook\RemoveCookbookRecipeController;
use App\Http\Controllers\Cookbook\ShowCookbookController;
use App\Http\Controllers\Cookbook\ShowCookbookInvitationController;
use App\Http\Controllers\Cookbook\UpdateCookbookController;
use App\Http\Controllers\Cookbook\UpdateCookbookMemberRoleController;
use App\Http\Controllers\Planning\CreatePlannedMealController;
use App\Http\Controllers\Planning\DeletePlannedMealController;
use App\Http\Controllers\Planning\ListPlannedMealsController;
use App\Http\Controllers\Planning\UpdatePlannedMealController;
use App\Http\Controllers\Recipe\AddRecipeFavoriteController;
use App\Http\Controllers\Recipe\CreateRecipeController;
use App\Http\Controllers\Recipe\DeleteRecipeController;
use App\Http\Controllers\Recipe\ListRecipesController;
use App\Http\Controllers\Recipe\RemoveRecipeFavoriteController;
use App\Http\Controllers\Recipe\ShowRecipeController;
use App\Http\Controllers\Recipe\UpdateRecipeController;
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

Route::get('auth/google/redirect', GoogleOAuthRedirectController::class)
    ->name('auth.google.redirect');

Route::get('auth/google/callback', GoogleOAuthCallbackController::class)
    ->name('auth.google.callback');

Route::get('auth/microsoft/redirect', MicrosoftOAuthRedirectController::class)
    ->name('auth.microsoft.redirect');

Route::get('auth/microsoft/callback', MicrosoftOAuthCallbackController::class)
    ->name('auth.microsoft.callback');

Route::middleware(AuthenticateWithJwt::class)
    ->group(function () {
        Route::get('cookbooks', ListCookbooksController::class)
            ->name('cookbooks.index');

        Route::post('recipes', CreateRecipeController::class)
            ->name('recipes.store');

        Route::get('recipes', ListRecipesController::class)
            ->name('recipes.index');

        Route::get('recipes/{recipe}', ShowRecipeController::class)
            ->name('recipes.show');

        Route::post('recipes/{recipe}/favorite', AddRecipeFavoriteController::class)
            ->name('recipes.favorite.store');

        Route::delete('recipes/{recipe}/favorite', RemoveRecipeFavoriteController::class)
            ->name('recipes.favorite.destroy');

        Route::patch('recipes/{recipe}', UpdateRecipeController::class)
            ->name('recipes.update');

        Route::delete('recipes/{recipe}', DeleteRecipeController::class)
            ->name('recipes.destroy');

        Route::post('planned-meals', CreatePlannedMealController::class)
            ->name('planned-meals.store');

        Route::get('planned-meals', ListPlannedMealsController::class)
            ->name('planned-meals.index');

        Route::patch('planned-meals/{plannedMeal}', UpdatePlannedMealController::class)
            ->name('planned-meals.update');

        Route::delete('planned-meals/{plannedMeal}', DeletePlannedMealController::class)
            ->name('planned-meals.destroy');

        Route::post('cookbooks', CreateCookbookController::class)
            ->name('cookbooks.store');

        Route::get('cookbooks/{cookbook}/recipes', ListCookbookRecipesController::class)
            ->name('cookbooks.recipes.index');

        Route::post('cookbooks/{cookbook}/recipes/{recipe}', AddCookbookRecipeController::class)
            ->name('cookbooks.recipes.store');

        Route::delete('cookbooks/{cookbook}/recipes/{recipe}', RemoveCookbookRecipeController::class)
            ->name('cookbooks.recipes.destroy');

        Route::get('cookbooks/{cookbook}/members', ListCookbookMembersController::class)
            ->name('cookbooks.members.index');

        Route::post('cookbooks/{cookbook}/messages', CreateCookbookMessageController::class)
            ->middleware('throttle:chat.messages')
            ->name('cookbooks.messages.store');

        Route::get('cookbooks/{cookbook}/messages', ListCookbookMessagesController::class)
            ->name('cookbooks.messages.index');

        Route::get('cookbooks/{cookbook}/messages/latest', ListLatestCookbookMessagesController::class)
            ->name('cookbooks.messages.latest');

        Route::delete('cookbooks/{cookbook}/members/me', LeaveCookbookController::class)
            ->name('cookbooks.members.leave');

        Route::delete('cookbooks/{cookbook}/members/{member}', RemoveCookbookMemberController::class)
            ->name('cookbooks.members.destroy');

        Route::patch('cookbooks/{cookbook}/members/{member}/role', UpdateCookbookMemberRoleController::class)
            ->name('cookbooks.members.role.update');

        Route::patch('cookbooks/{cookbook}', UpdateCookbookController::class)
            ->name('cookbooks.update');

        Route::delete('cookbooks/{cookbook}', DeleteCookbookController::class)
            ->name('cookbooks.destroy');

        Route::get('cookbooks/{cookbook}', ShowCookbookController::class)
            ->name('cookbooks.show');

        Route::post('cookbooks/{cookbook}/invitations', CreateCookbookInvitationController::class)
            ->name('cookbooks.invitations.store');

        Route::post('invitations/token/{token}/accept', AcceptCookbookInvitationController::class)
            ->name('invitations.accept');

        Route::get('invitations', ListCookbookInvitationsController::class)
            ->name('invitations.index');

        Route::post('invitations/{cookbookInvitation}/accept', AcceptCookbookInvitationByIdController::class)
            ->name('invitations.accept-by-id');

        Route::post('invitations/{cookbookInvitation}/decline', DeclineCookbookInvitationController::class)
            ->name('invitations.decline');
    });

Route::get('invitations/{token}', ShowCookbookInvitationController::class)
    ->name('invitations.show');

Route::middleware(AuthenticateWithJwt::class)
    ->prefix('auth')
    ->group(function () {
        Route::get('me', GetCurrentUserController::class)
            ->name('auth.me');

        Route::post('refresh', RefreshAccessTokenController::class)
            ->name('auth.refresh');

        Route::put('password', ChangePasswordController::class)
            ->name('auth.password.update');
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
