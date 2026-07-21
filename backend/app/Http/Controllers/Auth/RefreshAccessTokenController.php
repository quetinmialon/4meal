<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Middleware\AuthenticateWithJwt;
use App\Http\Resources\Auth\AuthenticatedSessionResource;
use App\Models\User;
use App\Support\ApiResponse;
use App\Support\Jwt\AccessTokenIssuer;
use App\Support\Jwt\AccessTokenRegistry;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RefreshAccessTokenController extends Controller
{
    public function __construct(
        private readonly AccessTokenIssuer $accessTokenIssuer,
        private readonly AccessTokenRegistry $accessTokenRegistry,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        $currentToken = AuthenticateWithJwt::accessToken($request);

        if (! $user instanceof User || $currentToken === null) {
            throw new AuthenticationException;
        }

        $this->accessTokenRegistry->forget($currentToken->tokenId);

        return ApiResponse::success(
            $request,
            AuthenticatedSessionResource::make([
                ...$this->accessTokenIssuer->issue($user),
                'user' => $user,
            ])->resolve($request),
        );
    }
}
