<?php

namespace App\Support\Jwt;

use Symfony\Component\HttpFoundation\Cookie;

final class AccessTokenCookie
{
    public function make(string $token, int $ttl): Cookie
    {
        return new Cookie(
            name: (string) config('jwt.cookie.name', '4meal_access_token'),
            value: $token,
            expire: time() + max(1, $ttl),
            path: (string) config('jwt.cookie.path', '/'),
            domain: config('jwt.cookie.domain'),
            secure: (bool) config('jwt.cookie.secure', true),
            httpOnly: (bool) config('jwt.cookie.http_only', true),
            raw: false,
            sameSite: (string) config('jwt.cookie.same_site', 'lax'),
        );
    }

    public function forget(): Cookie
    {
        return Cookie::create(
            name: (string) config('jwt.cookie.name', '4meal_access_token'),
            expire: 1,
            path: (string) config('jwt.cookie.path', '/'),
            domain: config('jwt.cookie.domain'),
            secure: (bool) config('jwt.cookie.secure', true),
            httpOnly: (bool) config('jwt.cookie.http_only', true),
            sameSite: (string) config('jwt.cookie.same_site', 'lax'),
        );
    }
}
