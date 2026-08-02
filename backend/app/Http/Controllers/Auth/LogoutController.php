<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Middleware\AuthenticateWithJwt;
use App\Support\Jwt\AccessTokenCookie;
use App\Support\Jwt\AccessTokenRegistry;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class LogoutController extends Controller
{
    public function __construct(
        private readonly AccessTokenRegistry $accessTokenRegistry,
        private readonly AccessTokenCookie $accessTokenCookie,
    ) {}

    public function __invoke(Request $request): Response
    {
        $token = AuthenticateWithJwt::accessToken($request);
        if ($token !== null) {
            $this->accessTokenRegistry->forget($token->tokenId);
        }

        return response()->noContent()->withCookie($this->accessTokenCookie->forget());
    }
}
