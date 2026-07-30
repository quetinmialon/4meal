<?php

return [
    'secret' => getenv('JWT_SECRET') ?: env('JWT_SECRET') ?: (getenv('APP_KEY') ?: env('APP_KEY')),
    'issuer' => env('JWT_ISSUER', env('APP_URL', 'http://localhost')),
    'ttl' => (int) env('JWT_TTL', 3600),
    'algorithm' => 'HS256',
];
