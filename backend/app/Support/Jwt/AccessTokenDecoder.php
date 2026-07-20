<?php

namespace App\Support\Jwt;

use Firebase\JWT\JWT;
use Illuminate\Auth\AuthenticationException;
use Throwable;

final class AccessTokenDecoder
{
    public function __construct(
        private readonly JwtConfiguration $jwtConfiguration,
    ) {}

    public function decode(string $token): DecodedAccessToken
    {
        $key = $this->jwtConfiguration->key();

        try {
            $decoded = JWT::decode($token, $key);
        } catch (Throwable) {
            throw new AuthenticationException;
        }

        $issuer = $decoded->iss ?? null;
        $subject = $decoded->sub ?? null;
        $tokenId = $decoded->jti ?? null;
        $issuedAt = $decoded->iat ?? null;
        $notBefore = $decoded->nbf ?? null;
        $expiresAt = $decoded->exp ?? null;

        if (
            ! is_string($issuer)
            || $issuer !== $this->jwtConfiguration->issuer()
            || ! is_string($subject)
            || $subject === ''
            || ! is_string($tokenId)
            || $tokenId === ''
            || ! is_int($issuedAt)
            || ! is_int($notBefore)
            || ! is_int($expiresAt)
        ) {
            throw new AuthenticationException;
        }

        return new DecodedAccessToken(
            issuer: $issuer,
            subject: $subject,
            tokenId: $tokenId,
            issuedAt: $issuedAt,
            notBefore: $notBefore,
            expiresAt: $expiresAt,
        );
    }
}
