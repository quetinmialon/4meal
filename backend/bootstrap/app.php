<?php

use App\Http\Middleware\AssignCorrelationId;
use App\Http\Middleware\FormatApiResponse;
use App\Support\ApiResponse;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(AssignCorrelationId::class);
        $middleware->api(append: [FormatApiResponse::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(function (Request $request, Throwable $exception): bool {
            return ApiResponse::shouldHandle($request);
        });

        $exceptions->render(function (Throwable $exception, Request $request) {
            if (! ApiResponse::shouldHandle($request)) {
                return null;
            }

            return ApiResponse::fromException($request, $exception);
        });

        $exceptions->respond(function (SymfonyResponse $response, Throwable $exception, Request $request) {
            $correlationId = ApiResponse::correlationId($request);

            if ($correlationId !== null) {
                $response->headers->set(ApiResponse::CORRELATION_ID_HEADER, $correlationId);
            }

            return $response;
        });

        $exceptions->context(function () {
            $request = request();

            if (! $request instanceof Request) {
                return [];
            }

            $correlationId = ApiResponse::correlationId($request);

            return $correlationId === null ? [] : ['correlation_id' => $correlationId];
        });
    })->create();
