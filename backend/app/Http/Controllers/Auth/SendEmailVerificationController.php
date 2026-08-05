<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\EmailVerificationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class SendEmailVerificationController extends Controller
{
    public function __invoke(Request $request, EmailVerificationService $service): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            abort(401);
        }

        if ($user->email_verified_at === null) {
            $service->send($user);
        }

        return ApiResponse::success($request, [
            'message' => 'Si votre adresse n’est pas encore vérifiée, un email a été envoyé.',
        ], 202);
    }
}
