<?php

namespace App\Support\Jwt;

use Firebase\JWT\Key;
use Illuminate\Support\Str;
use RuntimeException;

final class JwtConfiguration
{
    public function issuer(): string
    {
        return (string) config('jwt.issuer');
    }

    public function ttl(): int
    {
        return max(1, (int) config('jwt.ttl', 3600));
    }

    public function algorithm(): string
    {
        return (string) config('jwt.algorithm', 'HS256');
    }

    public function key(): Key
    {
        return new Key($this->secret(), $this->algorithm());
    }

    public function secret(): string
    {
        $secret = (string) config('jwt.secret', '');

        if ($secret === '') {
            throw new RuntimeException('JWT secret is not configured.');
        }

        if (! str_starts_with($secret, 'base64:')) {
            return $secret;
        }

        $decoded = base64_decode(Str::after($secret, 'base64:'), true);

        if ($decoded === false || $decoded === '') {
            throw new RuntimeException('JWT secret is not a valid base64-encoded string.');
        }

        return $decoded;
    }
}
