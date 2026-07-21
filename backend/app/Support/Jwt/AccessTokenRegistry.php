<?php

namespace App\Support\Jwt;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

final class AccessTokenRegistry
{
    public function remember(string $tokenId, string $subject, int $expiresAt): void
    {
        Cache::put(
            $this->key($tokenId),
            $subject,
            CarbonImmutable::createFromTimestampUTC($expiresAt),
        );
    }

    public function isActive(string $tokenId, string $subject): bool
    {
        return Cache::get($this->key($tokenId)) === $subject;
    }

    public function forget(string $tokenId): void
    {
        Cache::forget($this->key($tokenId));
    }

    private function key(string $tokenId): string
    {
        return "jwt:active:{$tokenId}";
    }
}
