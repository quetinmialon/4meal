<?php

use App\Http\Controllers\Auth\ChangePasswordController;
use App\Http\Controllers\Auth\GetCurrentUserController;
use App\Http\Controllers\Auth\GoogleOAuthCallbackController;
use App\Http\Controllers\Auth\GoogleOAuthRedirectController;
use App\Http\Controllers\Auth\LinkGoogleOAuthRedirectController;
use App\Http\Controllers\Auth\LinkMicrosoftOAuthRedirectController;
use App\Http\Controllers\Auth\ListOAuthAccountsController;
use App\Http\Controllers\Auth\LoginUserController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\MicrosoftOAuthCallbackController;
use App\Http\Controllers\Auth\MicrosoftOAuthRedirectController;
use App\Http\Controllers\Auth\RefreshAccessTokenController;
use App\Http\Controllers\Auth\RegisterUserController;
use App\Http\Controllers\Auth\RequestPasswordResetController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\Auth\SendEmailVerificationController;
use App\Http\Controllers\Auth\UnlinkOAuthAccountController;
use App\Http\Controllers\Auth\UpdateProfileController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\Cookbook\AcceptCookbookInvitationByIdController;
use App\Http\Controllers\Cookbook\AcceptCookbookInvitationController;
use App\Http\Controllers\Cookbook\AddCookbookRecipeController;
use App\Http\Controllers\Cookbook\CreateCookbookController;
use App\Http\Controllers\Cookbook\CreateCookbookInvitationController;
use App\Http\Controllers\Cookbook\CreateCookbookMessageController;
use App\Http\Controllers\Cookbook\DeclineCookbookInvitationController;
use App\Http\Controllers\Cookbook\DeleteCookbookController;
use App\Http\Controllers\Cookbook\DeleteCookbookMessageController;
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
use App\Http\Controllers\Cookbook\UpdateCookbookMessageController;
use App\Http\Controllers\Export\DownloadExportController;
use App\Http\Controllers\Export\DownloadRecipeCsvController;
use App\Http\Controllers\Import\ImportCsvController;
use App\Http\Controllers\Import\ImportJsonController;
use App\Http\Controllers\Import\ImportMealieController;
use App\Http\Controllers\Notification\ListNotificationPreferencesController;
use App\Http\Controllers\Notification\ListNotificationsController;
use App\Http\Controllers\Notification\MarkNotificationAsReadController;
use App\Http\Controllers\Notification\UpdateNotificationPreferencesController;
use App\Http\Controllers\Planning\CreatePlannedMealController;
use App\Http\Controllers\Planning\DeletePlannedMealController;
use App\Http\Controllers\Planning\GenerateShoppingListController;
use App\Http\Controllers\Planning\ListPlannedMealsController;
use App\Http\Controllers\Planning\UpdatePlannedMealController;
use App\Http\Controllers\Recipe\AddRecipeFavoriteController;
use App\Http\Controllers\Recipe\CreateRecipeCommentController;
use App\Http\Controllers\Recipe\CreateRecipeController;
use App\Http\Controllers\Recipe\DeleteRecipeCommentController;
use App\Http\Controllers\Recipe\DeleteRecipeController;
use App\Http\Controllers\Recipe\DuplicateRecipeController;
use App\Http\Controllers\Recipe\ListRecipeAuditsController;
use App\Http\Controllers\Recipe\ListRecipeCommentsController;
use App\Http\Controllers\Recipe\ListRecipesController;
use App\Http\Controllers\Recipe\RemoveRecipeFavoriteController;
use App\Http\Controllers\Recipe\ShowRecipeController;
use App\Http\Controllers\Recipe\UpdateRecipeCommentController;
use App\Http\Controllers\Recipe\UpdateRecipeController;
use App\Http\Middleware\AuthenticateWithJwt;
use App\Http\Middleware\RequireVerifiedEmail;
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

Route::post('auth/password/email', RequestPasswordResetController::class)
    ->middleware('throttle:auth.password.email')
    ->name('auth.password.email');

Route::post('auth/forgot-password', RequestPasswordResetController::class)
    ->middleware('throttle:auth.password.email');

Route::post('auth/password/reset', ResetPasswordController::class)
    ->middleware('throttle:auth.password.reset')
    ->name('auth.password.reset');

Route::post('auth/reset-password', ResetPasswordController::class)
    ->middleware('throttle:auth.password.reset');

Route::get('auth/email/verify/{id}/{token}', VerifyEmailController::class)
    ->whereNumber('id')
    ->name('auth.verification.verify');

Route::get('auth/google/redirect', GoogleOAuthRedirectController::class)
    ->name('auth.google.redirect');

Route::get('auth/google/callback', GoogleOAuthCallbackController::class)
    ->name('auth.google.callback');

Route::get('auth/microsoft/redirect', MicrosoftOAuthRedirectController::class)
    ->name('auth.microsoft.redirect');

Route::get('auth/microsoft/callback', MicrosoftOAuthCallbackController::class)
    ->name('auth.microsoft.callback');

Route::middleware([AuthenticateWithJwt::class, RequireVerifiedEmail::class])
    ->group(function () {
        Route::post('import', ImportJsonController::class)
            ->name('import.json');
        Route::post('import/mealie', ImportMealieController::class)
            ->name('import.mealie');

        Route::post('import/csv', ImportCsvController::class)
            ->name('import.csv');

        Route::get('export', DownloadExportController::class)
            ->name('export.download');

        Route::get('export/csv', DownloadRecipeCsvController::class)
            ->name('export.csv');

        Route::get('cookbooks', ListCookbooksController::class)
            ->name('cookbooks.index');

        Route::get('notifications', ListNotificationsController::class)
            ->name('notifications.index');

        Route::get('notifications/preferences', ListNotificationPreferencesController::class)
            ->name('notifications.preferences.index');

        Route::put('notifications/preferences', UpdateNotificationPreferencesController::class)
            ->name('notifications.preferences.update');

        Route::patch('notifications/{notification}/read', MarkNotificationAsReadController::class)
            ->name('notifications.read');

        Route::post('recipes', CreateRecipeController::class)
            ->name('recipes.store');

        Route::get('recipes', ListRecipesController::class)
            ->name('recipes.index');

        Route::get('recipes/{recipe}', ShowRecipeController::class)
            ->name('recipes.show');

        Route::get('recipes/{recipe}/history', ListRecipeAuditsController::class)
            ->withTrashed()
            ->name('recipes.history.index');

        Route::post('recipes/{recipe}/duplicate', DuplicateRecipeController::class)
            ->name('recipes.duplicate');

        Route::get('recipes/{recipe}/comments', ListRecipeCommentsController::class)
            ->name('recipes.comments.index');

        Route::post('recipes/{recipe}/comments', CreateRecipeCommentController::class)
            ->name('recipes.comments.store');

        Route::patch('recipes/{recipe}/comments/{comment}', UpdateRecipeCommentController::class)
            ->name('recipes.comments.update');

        Route::delete('recipes/{recipe}/comments/{comment}', DeleteRecipeCommentController::class)
            ->name('recipes.comments.destroy');

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

        Route::get('planned-meals/shopping-list', GenerateShoppingListController::class)
            ->name('planned-meals.shopping-list');

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

        Route::patch('cookbooks/{cookbook}/messages/{message}', UpdateCookbookMessageController::class)
            ->name('cookbooks.messages.update');

        Route::delete('cookbooks/{cookbook}/messages/{message}', DeleteCookbookMessageController::class)
            ->name('cookbooks.messages.destroy');

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

        Route::post('email/verification-notification', SendEmailVerificationController::class)
            ->middleware('throttle:auth.email.verification')
            ->name('auth.verification.send');

        Route::patch('me', UpdateProfileController::class)
            ->middleware(RequireVerifiedEmail::class)
            ->name('auth.me.update');

        Route::post('refresh', RefreshAccessTokenController::class)
            ->name('auth.refresh');

        Route::post('logout', LogoutController::class)
            ->name('auth.logout');

        Route::put('password', ChangePasswordController::class)
            ->middleware(RequireVerifiedEmail::class)
            ->name('auth.password.update');

        Route::get('oauth-accounts', ListOAuthAccountsController::class)
            ->middleware(RequireVerifiedEmail::class)
            ->name('auth.oauth-accounts.index');

        Route::get('oauth', ListOAuthAccountsController::class)
            ->middleware(RequireVerifiedEmail::class)
            ->name('auth.oauth.index');

        Route::get('oauth/accounts', ListOAuthAccountsController::class)->middleware(RequireVerifiedEmail::class);

        Route::get('oauth/google/link', LinkGoogleOAuthRedirectController::class)
            ->middleware(RequireVerifiedEmail::class)
            ->name('auth.oauth.google.link');

        Route::post('oauth/google/link', LinkGoogleOAuthRedirectController::class)
            ->middleware(RequireVerifiedEmail::class);

        Route::get('oauth/microsoft/link', LinkMicrosoftOAuthRedirectController::class)
            ->middleware(RequireVerifiedEmail::class)
            ->name('auth.oauth.microsoft.link');

        Route::post('oauth/microsoft/link', LinkMicrosoftOAuthRedirectController::class)
            ->middleware(RequireVerifiedEmail::class);

        Route::delete('oauth/{provider}', UnlinkOAuthAccountController::class)
            ->middleware(RequireVerifiedEmail::class)
            ->where('provider', 'google|microsoft')
            ->name('auth.oauth.destroy');
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
