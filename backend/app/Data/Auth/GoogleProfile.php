<?php

namespace App\Data\Auth;

final readonly class GoogleProfile
{
    public function __construct(
        public string $providerId,
        public string $name,
        public string $email,
        public bool $emailVerified,
        public string $accessToken,
        public ?string $refreshToken,
        public ?int $expiresIn,
    ) {}
}
