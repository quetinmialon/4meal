<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class EnableTwoFactorController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    { /** @var User $user */ $user = $request->user();
        $user->forceFill(['two_factor_enabled' => true])->save();

        return ApiResponse::success($request, ['enabled' => true]);
    }
}
