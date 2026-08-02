<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class UnlinkOAuthAccountController extends Controller
{
    public function __invoke(Request $request, string $provider): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            throw new AuthenticationException;
        }

        $account = $user->oauthAccounts()->where('provider', $provider)->first();
        abort_if($account === null, 404, 'Compte OAuth introuvable.');

        $hasPassword = is_string($user->getAttribute('password_hash')) && $user->getAttribute('password_hash') !== '';
        if (! $hasPassword && $user->oauthAccounts()->count() === 1) {
            throw ValidationException::withMessages([
                'provider' => ['Impossible de supprimer votre dernier moyen de connexion.'],
            ]);
        }

        $account->delete();

        return ApiResponse::success($request, ['message' => 'Compte OAuth dissocié.']);
    }
}
