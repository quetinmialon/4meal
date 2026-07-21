<?php

namespace App\Support\Jwt;

final readonly class DecodedAccessToken
{
    public function __construct(
        public string $issuer,
        public string $subject,
        public string $tokenId,
        public int $issuedAt,
        public int $notBefore,
        public int $expiresAt,
    ) {}
}
