<?php

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AssignCorrelationId
{
    public function handle(Request $request, Closure $next): Response
    {
        $correlationId = trim((string) $request->headers->get(ApiResponse::CORRELATION_ID_HEADER, ''));

        if ($correlationId === '') {
            $correlationId = (string) Str::uuid();
        }

        $request->attributes->set(ApiResponse::CORRELATION_ID_ATTRIBUTE, $correlationId);

        $response = $next($request);

        $response->headers->set(ApiResponse::CORRELATION_ID_HEADER, $correlationId);

        return $response;
    }
}
