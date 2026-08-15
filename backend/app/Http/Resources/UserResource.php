<?php

namespace App\Http\Resources;

use App\Enums\Diet;
use App\Enums\Theme;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var User $user */
        $user = $this->resource;
        $lastLoginAt = $user->getAttribute('last_login_at');
        $diet = $user->getAttribute('diet');
        $theme = $user->getAttribute('theme');

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'email_verified' => $user->email_verified_at !== null,
            'diet' => $diet instanceof Diet ? $diet->value : $diet,
            'allergies' => $user->allergies ?? [],
            'default_servings' => $user->default_servings,
            'theme' => $theme instanceof Theme ? $theme->value : $theme,
            'avatar_path' => $user->avatar_path,
            'avatar_url' => is_string($user->avatar_path) && Storage::disk('public')->exists($user->avatar_path)
                ? Storage::disk('public')->url($user->avatar_path)
                : null,
            'last_login_at' => $lastLoginAt instanceof CarbonInterface
                ? $lastLoginAt->toJSON()
                : null,
            'created_at' => $user->created_at?->toJSON(),
        ];
    }
}
