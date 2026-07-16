<?php

namespace App\Support;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

final class ApiResponse
{
    public const CORRELATION_ID_ATTRIBUTE = 'correlation_id';

    public const CORRELATION_ID_HEADER = 'X-Correlation-ID';

    public static function shouldHandle(Request $request): bool
    {
        return $request->is('api/*') || $request->expectsJson();
    }

    public static function correlationId(Request $request): ?string
    {
        $correlationId = $request->attributes->get(self::CORRELATION_ID_ATTRIBUTE);

        return is_string($correlationId) && $correlationId !== '' ? $correlationId : null;
    }

    public static function success(
        Request $request,
        mixed $data,
        int $status = 200,
        array $meta = [],
        array $headers = [],
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'data' => $data,
            'meta' => self::meta($request, $meta),
        ], $status, self::headers($request, $headers));
    }

    public static function error(
        Request $request,
        string $code,
        string $message,
        int $status,
        array $details = [],
        array $headers = [],
    ): JsonResponse {
        $payload = [
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
            'meta' => self::meta($request),
        ];

        if ($details !== []) {
            $payload['error']['details'] = $details;
        }

        return response()->json($payload, $status, self::headers($request, $headers));
    }

    public static function fromException(Request $request, Throwable $exception): JsonResponse
    {
        return match (true) {
            $exception instanceof ValidationException => self::error(
                $request,
                'validation_error',
                'The given data was invalid.',
                $exception->status,
                ['fields' => $exception->errors()],
            ),
            $exception instanceof AuthenticationException => self::error(
                $request,
                'authentication_error',
                'Authentication is required.',
                SymfonyResponse::HTTP_UNAUTHORIZED,
            ),
            $exception instanceof AuthorizationException => self::error(
                $request,
                'authorization_error',
                'You are not allowed to perform this action.',
                SymfonyResponse::HTTP_FORBIDDEN,
            ),
            $exception instanceof HttpExceptionInterface && $exception->getStatusCode() === SymfonyResponse::HTTP_FORBIDDEN => self::error(
                $request,
                'authorization_error',
                'You are not allowed to perform this action.',
                SymfonyResponse::HTTP_FORBIDDEN,
            ),
            $exception instanceof HttpExceptionInterface && $exception->getStatusCode() === SymfonyResponse::HTTP_NOT_FOUND => self::error(
                $request,
                'not_found',
                'Resource not found.',
                SymfonyResponse::HTTP_NOT_FOUND,
            ),
            $exception instanceof HttpExceptionInterface => self::error(
                $request,
                'http_error',
                $exception->getMessage() !== ''
                    ? $exception->getMessage()
                    : (SymfonyResponse::$statusTexts[$exception->getStatusCode()] ?? 'HTTP error.'),
                $exception->getStatusCode(),
            ),
            default => self::error(
                $request,
                'server_error',
                'An unexpected error occurred.',
                SymfonyResponse::HTTP_INTERNAL_SERVER_ERROR,
            ),
        };
    }

    public static function isFormatted(mixed $payload): bool
    {
        return is_array($payload)
            && array_key_exists('success', $payload)
            && array_key_exists('meta', $payload)
            && (array_key_exists('data', $payload) || array_key_exists('error', $payload));
    }

    public static function wrapPayload(
        Request $request,
        mixed $payload,
        int $status = 200,
        array $headers = [],
    ): JsonResponse {
        if (self::isLengthAwarePaginationPayload($payload)) {
            return self::success(
                $request,
                $payload['data'],
                $status,
                [
                    'pagination' => [
                        'current_page' => $payload['current_page'],
                        'per_page' => $payload['per_page'],
                        'total' => $payload['total'],
                        'last_page' => $payload['last_page'],
                        'from' => $payload['from'],
                        'to' => $payload['to'],
                        'path' => $payload['path'],
                        'has_more_pages' => ($payload['next_page_url'] ?? null) !== null,
                    ],
                ],
                $headers,
            );
        }

        if (self::isResourcePaginationPayload($payload)) {
            /** @var array<string, mixed> $meta */
            $meta = $payload['meta'];

            return self::success(
                $request,
                $payload['data'],
                $status,
                [
                    'pagination' => [
                        'current_page' => $meta['current_page'],
                        'per_page' => $meta['per_page'],
                        'total' => $meta['total'],
                        'last_page' => $meta['last_page'],
                        'from' => $meta['from'] ?? null,
                        'to' => $meta['to'] ?? null,
                        'path' => $meta['path'] ?? null,
                        'has_more_pages' => ($meta['current_page'] ?? 0) < ($meta['last_page'] ?? 0),
                    ],
                ],
                $headers,
            );
        }

        return self::success($request, $payload, $status, [], $headers);
    }

    private static function headers(Request $request, array $headers = []): array
    {
        $correlationId = self::correlationId($request);

        if ($correlationId === null) {
            return $headers;
        }

        $headers[self::CORRELATION_ID_HEADER] = $correlationId;

        return $headers;
    }

    private static function isLengthAwarePaginationPayload(mixed $payload): bool
    {
        return is_array($payload)
            && array_key_exists('data', $payload)
            && array_key_exists('current_page', $payload)
            && array_key_exists('per_page', $payload)
            && array_key_exists('total', $payload)
            && array_key_exists('last_page', $payload)
            && array_key_exists('path', $payload);
    }

    private static function isResourcePaginationPayload(mixed $payload): bool
    {
        if (! is_array($payload) || ! array_key_exists('data', $payload) || ! array_key_exists('meta', $payload)) {
            return false;
        }

        if (! is_array($payload['meta'])) {
            return false;
        }

        return array_key_exists('current_page', $payload['meta'])
            && array_key_exists('per_page', $payload['meta'])
            && array_key_exists('total', $payload['meta'])
            && array_key_exists('last_page', $payload['meta']);
    }

    private static function meta(Request $request, array $meta = []): array
    {
        $baseMeta = [];

        $correlationId = self::correlationId($request);

        if ($correlationId !== null) {
            $baseMeta['correlation_id'] = $correlationId;
        }

        return array_merge($baseMeta, $meta);
    }
}
