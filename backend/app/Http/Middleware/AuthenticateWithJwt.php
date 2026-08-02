<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\Jwt\AccessTokenDecoder;
use App\Support\Jwt\AccessTokenRegistry;
use App\Support\Jwt\DecodedAccessToken;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateWithJwt
{
    public const TOKEN_ATTRIBUTE = 'auth.access_token';

    public function __construct(
        private readonly AccessTokenDecoder $accessTokenDecoder,
        private readonly AccessTokenRegistry $accessTokenRegistry,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken()
            ?: $request->cookie((string) config('jwt.cookie.name', '4meal_access_token'));

        if (! is_string($token) || $token === '') {
            throw new AuthenticationException;
        }

        $decodedToken = $this->accessTokenDecoder->decode($token);

        if (! $this->accessTokenRegistry->isActive($decodedToken->tokenId, $decodedToken->subject)) {
            throw new AuthenticationException;
        }

        $user = User::query()->find($decodedToken->subject);

        if (! $user instanceof User) {
            $this->accessTokenRegistry->forget($decodedToken->tokenId);

            throw new AuthenticationException;
        }

        $request->attributes->set(self::TOKEN_ATTRIBUTE, $decodedToken);
        $request->setUserResolver(static fn (): User => $user);

        return $next($request);
    }

    public static function accessToken(Request $request): ?DecodedAccessToken
    {
        $token = $request->attributes->get(self::TOKEN_ATTRIBUTE);

        return $token instanceof DecodedAccessToken ? $token : null;
    }
}
