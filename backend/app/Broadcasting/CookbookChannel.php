<?php

namespace App\Broadcasting;

use App\Models\Cookbook;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

final class CookbookChannel
{
    public function join(User $user, string $cookbookPublicId): bool
    {
        if (! Str::isUuid($cookbookPublicId)) {
            return false;
        }

        $cookbook = Cookbook::query()->where('public_id', $cookbookPublicId)->first();

        return $cookbook instanceof Cookbook
            && Gate::forUser($user)->allows('view', $cookbook);
    }
}
