<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\AuthenticateUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginUserRequest;
use App\Http\Resources\Auth\AuthenticatedSessionResource;
use App\Support\ApiResponse;
use App\Support\Jwt\AccessTokenIssuer;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class LoginUserController extends Controller
{
    public function __construct(
        private readonly AuthenticateUser $authenticateUser,
        private readonly AccessTokenIssuer $accessTokenIssuer,
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
                'Invalid credentials.',
                SymfonyResponse::HTTP_UNAUTHORIZED,
            );
        }

        return ApiResponse::success(
            $request,
            AuthenticatedSessionResource::make([
                ...$this->accessTokenIssuer->issue($user),
                'user' => $user,
            ])->resolve($request),
        );
    }
}
