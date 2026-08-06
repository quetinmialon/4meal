<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\DisableTwoFactorRequest;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

final class DisableTwoFactorController extends Controller
{
    public function __invoke(DisableTwoFactorRequest $request): JsonResponse
    { /** @var User $user */ $user = $request->user();
        if (! Hash::check($request->string('current_password')->toString(), $user->getAttribute('password_hash'))) {
            throw ValidationException::withMessages(['current_password' => [__('auth.password')]]);
        } $user->forceFill(['two_factor_enabled' => false])->save();

        return ApiResponse::success($request, ['enabled' => false]);
    }
}
