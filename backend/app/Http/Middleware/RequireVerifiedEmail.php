<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RequireVerifiedEmail
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user instanceof User && $user->email_verified_at === null) {
            return ApiResponse::error(
                $request,
                'email_not_verified',
                'Vérifiez votre adresse email avant d’utiliser cette fonctionnalité.',
                Response::HTTP_FORBIDDEN,
                ['forbidden_until_verification' => true],
            );
        }

        return $next($request);
    }
}
