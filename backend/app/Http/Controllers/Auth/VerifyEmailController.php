<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\Auth\EmailVerificationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class VerifyEmailController extends Controller
{
    public function __invoke(Request $request, int $id, string $token, EmailVerificationService $service): JsonResponse
    {
        $user = User::query()->find($id);
        if (! $user instanceof User || ! $service->verify($user, $token)) {
            return ApiResponse::error($request, 'email_verification_invalid', 'Le jeton de vérification est invalide ou expiré.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return ApiResponse::success($request, [
            'message' => 'Adresse email vérifiée.',
            'user' => UserResource::make($user->fresh())->resolve($request),
        ]);
    }
}
