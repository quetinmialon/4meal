<?php

namespace App\Data\Auth;

class OAuthProfile
{
    public function __construct(
        public readonly string $providerId,
        public readonly string $name,
        public readonly string $email,
        public readonly bool $emailVerified,
        public readonly string $accessToken,
        public readonly ?string $refreshToken,
        public readonly ?int $expiresIn,
    ) {}
}
