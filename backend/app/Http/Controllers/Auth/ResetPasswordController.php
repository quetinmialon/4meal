<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Models\User;
use App\Support\ApiResponse;
use App\Support\Jwt\AccessTokenRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ResetPasswordController extends Controller
{
    public function __construct(private readonly AccessTokenRegistry $accessTokenRegistry) {}

    public function __invoke(ResetPasswordRequest $request): JsonResponse
    {
        $email = $request->string('email')->toString();
        $token = $request->string('token')->toString();
        $reset = DB::table('password_reset_tokens')->where('email', $email)->first();
        $expiresAt = now()->subMinutes((int) config('auth.passwords.users.expire', 60));

        if ($reset === null
            || ! is_string($reset->token)
            || ! is_string($reset->created_at)
            || now()->parse($reset->created_at)->lt($expiresAt)
            || ! hash_equals($reset->token, hash('sha256', $token))) {
            throw ValidationException::withMessages([
                'token' => ['Le lien de réinitialisation est invalide ou expiré.'],
            ]);
        }

        $user = User::query()->where('email', $email)->first();
        if (! $user instanceof User) {
            throw ValidationException::withMessages([
                'token' => ['Le lien de réinitialisation est invalide ou expiré.'],
            ]);
        }

        $newPassword = $request->string('password')->toString();

        DB::transaction(function () use ($email, $newPassword, $user): void {
            $user->update(['password' => $newPassword]);
            DB::table('password_reset_tokens')->where('email', $email)->delete();
            $this->accessTokenRegistry->forgetForSubject((string) $user->getAuthIdentifier());
        });

        return ApiResponse::success($request, [
            'message' => 'Mot de passe réinitialisé. Toutes les sessions ont été invalidées.',
        ]);
    }
}
