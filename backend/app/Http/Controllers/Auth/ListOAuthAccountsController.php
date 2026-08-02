<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\OAuthAccount;
use App\Models\User;
use App\Support\ApiResponse;
use Carbon\CarbonInterface;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ListOAuthAccountsController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            throw new AuthenticationException;
        }

        /** @var Collection<int, OAuthAccount> $accounts */
        $accounts = $user->oauthAccounts()->latest('id')->get();
        $payload = [];
        foreach ($accounts as $account) {
            $expiresAt = $account->getAttribute('token_expires_at');
            $createdAt = $account->getAttribute('created_at');
            $payload[] = [
                'id' => $account->getKey(),
                'provider' => $account->getAttribute('provider'),
                'email' => $account->getAttribute('provider_email'),
                'token_expires_at' => $expiresAt instanceof CarbonInterface ? $expiresAt->toJSON() : null,
                'created_at' => $createdAt instanceof CarbonInterface ? $createdAt->toJSON() : null,
            ];
        }

        return ApiResponse::success($request, $payload);
    }
}
