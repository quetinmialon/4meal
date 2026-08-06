<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\VerifyTwoFactorRequest;
use App\Http\Resources\Auth\AuthenticatedSessionResource;
use App\Services\Auth\TwoFactorService;
use App\Support\ApiResponse;
use App\Support\Jwt\AccessTokenCookie;
use App\Support\Jwt\AccessTokenIssuer;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

final class VerifyTwoFactorController extends Controller
{
    public function __construct(private readonly TwoFactorService $service, private readonly AccessTokenIssuer $issuer, private readonly AccessTokenCookie $cookie) {}

    public function __invoke(VerifyTwoFactorRequest $request): JsonResponse
    {
        $user = $this->service->verify($request->string('challenge')->toString(), $request->string('code')->toString());
        if ($user === null) {
            return ApiResponse::error($request, 'two_factor_invalid_code', 'Code invalide ou expiré.', SymfonyResponse::HTTP_UNAUTHORIZED);
        } $session = [...$this->issuer->issue($user), 'user' => $user];

        return ApiResponse::success($request, AuthenticatedSessionResource::make($session)->resolve($request))->withCookie($this->cookie->make($session['access_token'], $session['expires_in']));
    }
}
