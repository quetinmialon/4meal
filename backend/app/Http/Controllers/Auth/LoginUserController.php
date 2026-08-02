<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\AuthenticateUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginUserRequest;
use App\Http\Resources\Auth\AuthenticatedSessionResource;
use App\Support\ApiResponse;
use App\Support\Jwt\AccessTokenCookie;
use App\Support\Jwt\AccessTokenIssuer;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class LoginUserController extends Controller
{
    public function __construct(
        private readonly AuthenticateUser $authenticateUser,
        private readonly AccessTokenIssuer $accessTokenIssuer,
        private readonly AccessTokenCookie $accessTokenCookie,
    ) {}

    public function __invoke(LoginUserRequest $request): JsonResponse
    {
        $validated = $request->safe();

        $user = $this->authenticateUser->handle(
            $validated->string('email')->toString(),
            $validated->string('password')->toString(),
        );

        if ($user === null) {
            return ApiResponse::error(
                $request,
                'authentication_error',
                __('auth.failed'),
                SymfonyResponse::HTTP_UNAUTHORIZED,
            );
        }

        $session = [
            ...$this->accessTokenIssuer->issue($user),
            'user' => $user,
        ];

        $response = ApiResponse::success(
            $request,
            AuthenticatedSessionResource::make($session)->resolve($request),
        );

        return $response->withCookie($this->accessTokenCookie->make(
            $session['access_token'],
            $session['expires_in'],
        ));
    }
}
