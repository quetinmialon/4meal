<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use App\Support\Jwt\AccessTokenDecoder;
use App\Support\Jwt\AccessTokenRegistry;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class LogoutUserController extends Controller
{
    public function __construct(
        private readonly AccessTokenDecoder $accessTokenDecoder,
        private readonly AccessTokenRegistry $accessTokenRegistry,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $token = $request->bearerToken();

        if (! is_string($token) || $token === '') {
            throw new AuthenticationException;
        }

        $decodedToken = $this->accessTokenDecoder->decode($token);

        // Cache::forget is idempotent: this also succeeds when the token
        // has already been revoked by an earlier logout request.
        $this->accessTokenRegistry->forget($decodedToken->tokenId);

        return ApiResponse::success($request, null);
    }
}
