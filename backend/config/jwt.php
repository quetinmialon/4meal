<?php

return [
    'secret' => getenv('JWT_SECRET') ?: env('JWT_SECRET') ?: (getenv('APP_KEY') ?: env('APP_KEY')),
    'issuer' => env('JWT_ISSUER', env('APP_URL', 'http://localhost')),
    'ttl' => (int) env('JWT_TTL', 3600),
    'algorithm' => 'HS256',
    'cookie' => [
        'name' => env('JWT_COOKIE_NAME', '4meal_access_token'),
        'secure' => filter_var(env('JWT_COOKIE_SECURE', env('APP_ENV') === 'production'), FILTER_VALIDATE_BOOLEAN),
        'http_only' => true,
        'same_site' => env('JWT_COOKIE_SAME_SITE', 'lax'),
        'path' => '/',
        'domain' => env('JWT_COOKIE_DOMAIN'),
    ],
];
