<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RequestPasswordResetRequest;
use App\Mail\PasswordResetMail;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

final class RequestPasswordResetController extends Controller
{
    public function __invoke(RequestPasswordResetRequest $request): JsonResponse
    {
        $email = $request->string('email')->toString();
        $user = User::query()->where('email', $email)->first();

        if ($user instanceof User && is_string($user->getAttribute('password_hash'))) {
            $token = Str::random(64);
            $expiresInMinutes = (int) config('auth.passwords.users.expire', 60);

            DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $email],
                [
                    'token' => hash('sha256', $token),
                    'created_at' => now(),
                ],
            );

            Mail::to($user)->send(new PasswordResetMail($user, $token, $expiresInMinutes));
        }

        return ApiResponse::success($request, [
            'message' => 'Si cette adresse correspond à un compte, un email de réinitialisation a été envoyé.',
        ], 202);
    }
}
