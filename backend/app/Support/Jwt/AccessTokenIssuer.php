<?php

namespace App\Support\Jwt;

use App\Models\User;
use Firebase\JWT\JWT;
use Illuminate\Support\Str;
use RuntimeException;

class AccessTokenIssuer
{
    /**
     * @return array{access_token: string, token_type: string, expires_in: int}
     */
    public function issue(User $user): array
    {
        $issuedAt = now();
        $ttl = max(1, (int) config('jwt.ttl', 3600));
        $expiresAt = $issuedAt->copy()->addSeconds($ttl);

        $token = JWT::encode([
            'iss' => (string) config('jwt.issuer'),
            'sub' => (string) $user->getAuthIdentifier(),
            'iat' => $issuedAt->timestamp,
            'nbf' => $issuedAt->timestamp,
            'exp' => $expiresAt->timestamp,
            'jti' => (string) Str::uuid(),
        ], $this->secret(), (string) config('jwt.algorithm', 'HS256'));

        return [
            'access_token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => $ttl,
        ];
    }

    private function secret(): string
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
