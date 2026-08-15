<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class UpdateProfileAction
{
    /** @param array<string, mixed> $attributes */
    public function execute(User $user, array $attributes): User
    {
        $newAvatarPath = null;
        $oldAvatarPath = $user->avatar_path;

        try {
            if (($attributes['avatar'] ?? null) instanceof UploadedFile) {
                $newAvatarPath = Storage::disk('public')->putFile('avatars', $attributes['avatar']);

                if (! is_string($newAvatarPath)) {
                    throw new RuntimeException('User avatar could not be stored.');
                }
            }

            $updatedUser = DB::transaction(function () use ($user, $attributes, $newAvatarPath): User {
                $user->forceFill(array_intersect_key($attributes, array_flip([
                    'name', 'email', 'diet', 'allergies', 'default_servings', 'theme', 'email_verified_at',
                ])));

                if (is_string($newAvatarPath)) {
                    $user->avatar_path = $newAvatarPath;
                }

                $user->save();

                return $user->fresh();
            });

            if (is_string($newAvatarPath) && is_string($oldAvatarPath) && $oldAvatarPath !== $newAvatarPath) {
                Storage::disk('public')->delete($oldAvatarPath);
            }

            return $updatedUser;
        } catch (Throwable $exception) {
            if (is_string($newAvatarPath)) {
                Storage::disk('public')->delete($newAvatarPath);
            }

            throw $exception;
        }
    }
}
