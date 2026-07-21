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

        $tokenIds = Cache::get($this->subjectKey($subject), []);
        $tokenIds = is_array($tokenIds) ? $tokenIds : [];
        $tokenIds[] = $tokenId;

        Cache::put($this->subjectKey($subject), array_values(array_unique($tokenIds)), CarbonImmutable::createFromTimestampUTC($expiresAt));
    }

    public function isActive(string $tokenId, string $subject): bool
    {
        return Cache::get($this->key($tokenId)) === $subject;
    }

    public function forget(string $tokenId): void
    {
        $subject = Cache::get($this->key($tokenId));
        Cache::forget($this->key($tokenId));

        if (is_string($subject)) {
            $tokenIds = Cache::get($this->subjectKey($subject), []);
            $tokenIds = is_array($tokenIds) ? array_values(array_diff($tokenIds, [$tokenId])) : [];

            if ($tokenIds === []) {
                Cache::forget($this->subjectKey($subject));
            } else {
                Cache::put($this->subjectKey($subject), $tokenIds);
            }
        }
    }

    public function forgetForSubject(string $subject): void
    {
        $tokenIds = Cache::get($this->subjectKey($subject), []);
        $tokenIds = is_array($tokenIds) ? $tokenIds : [];

        foreach ($tokenIds as $tokenId) {
            if (is_string($tokenId)) {
                Cache::forget($this->key($tokenId));
            }
        }

        Cache::forget($this->subjectKey($subject));
    }

    private function key(string $tokenId): string
    {
        return "jwt:active:{$tokenId}";
    }

    private function subjectKey(string $subject): string
    {
        return "jwt:subject:{$subject}";
    }
}
