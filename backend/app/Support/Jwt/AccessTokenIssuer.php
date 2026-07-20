<?php

namespace App\Support\Jwt;

use App\Models\User;
use Firebase\JWT\JWT;
use Illuminate\Support\Str;

class AccessTokenIssuer
{
    public function __construct(
        private readonly JwtConfiguration $jwtConfiguration,
        private readonly AccessTokenRegistry $accessTokenRegistry,
    ) {}

    /**
     * @return array{access_token: string, token_type: string, expires_in: int}
     */
    public function issue(User $user): array
    {
        $issuedAt = now();
        $ttl = $this->jwtConfiguration->ttl();
        $expiresAt = $issuedAt->copy()->addSeconds($ttl);
        $tokenId = (string) Str::uuid();

        $token = JWT::encode([
            'iss' => $this->jwtConfiguration->issuer(),
            'sub' => (string) $user->getAuthIdentifier(),
            'iat' => $issuedAt->timestamp,
            'nbf' => $issuedAt->timestamp,
            'exp' => $expiresAt->timestamp,
            'jti' => $tokenId,
        ], $this->jwtConfiguration->secret(), $this->jwtConfiguration->algorithm());

        $this->accessTokenRegistry->remember(
            tokenId: $tokenId,
            subject: (string) $user->getAuthIdentifier(),
            expiresAt: $expiresAt->timestamp,
        );

        return [
            'access_token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => $ttl,
        ];
    }
}
