<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Models\User;
use App\Support\ApiResponse;
use App\Support\Jwt\AccessTokenRegistry;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class ChangePasswordController extends Controller
{
    public function __construct(
        private readonly AccessTokenRegistry $accessTokenRegistry,
    ) {}

    public function __invoke(ChangePasswordRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new AuthenticationException;
        }

        if (! Hash::check($request->string('current_password')->toString(), $user->getAttribute('password_hash'))) {
            throw ValidationException::withMessages([
                'current_password' => [__('auth.password')],
            ]);
        }

        $newPassword = $request->string('password')->toString();

        DB::transaction(function () use ($user, $newPassword): void {
            $user->update(['password' => $newPassword]);
            $this->accessTokenRegistry->forgetForSubject((string) $user->getAuthIdentifier());
        });

        return ApiResponse::success($request, [
            'message' => 'Mot de passe modifie.',
        ]);
    }
}
