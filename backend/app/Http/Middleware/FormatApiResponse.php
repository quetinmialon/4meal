<?php

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class FormatApiResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! ApiResponse::shouldHandle($request) || ! $response instanceof JsonResponse) {
            return $response;
        }

        if (in_array($response->getStatusCode(), [204, 304], true)) {
            return $response;
        }

        $payload = $response->getData(true);

        if (ApiResponse::isFormatted($payload)) {
            return $response;
        }

        $formatted = ApiResponse::wrapPayload(
            $request,
            $payload,
            $response->getStatusCode(),
            $response->headers->allPreserveCaseWithoutCookies(),
        );

        foreach ($response->headers->getCookies() as $cookie) {
            $formatted->headers->setCookie($cookie);
        }

        return $formatted;
    }
}
