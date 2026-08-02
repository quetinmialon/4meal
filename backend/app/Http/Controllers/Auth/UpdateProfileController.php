<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class UpdateProfileController extends Controller
{
    public function __invoke(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new AuthenticationException;
        }

        if ($request->has('email') && ! Hash::check(
            $request->string('current_password')->toString(),
            $user->getAttribute('password_hash'),
        )) {
            throw ValidationException::withMessages([
                'current_password' => [__('auth.password')],
            ]);
        }

        $attributes = $request->safe()->only(['name', 'email', 'avatar_path']);
        $emailChanged = array_key_exists('email', $attributes)
            && $attributes['email'] !== $user->email;

        if ($emailChanged) {
            $attributes['email_verified_at'] = null;
        }

        try {
            DB::transaction(function () use ($user, $attributes): void {
                $user->forceFill($attributes)->save();
            });
        } catch (QueryException $exception) {
            if ($this->isUniqueEmailViolation($exception)) {
                throw ValidationException::withMessages([
                    'email' => [__('validation.unique', ['attribute' => __('validation.attributes.email')])],
                ]);
            }

            throw $exception;
        }

        return ApiResponse::success(
            $request,
            UserResource::make($user->fresh())->resolve($request),
        );
    }

    private function isUniqueEmailViolation(QueryException $exception): bool
    {
        return in_array($exception->getCode(), ['23000', '23505'], true)
            && str_contains(mb_strtolower($exception->getMessage()), 'email');
    }
}
