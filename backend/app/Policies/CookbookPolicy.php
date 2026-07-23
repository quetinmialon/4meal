<?php

namespace App\Policies;

use App\Models\Cookbook;
use App\Models\User;

class CookbookPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Cookbook $cookbook): bool
    {
        return $cookbook->members()->whereKey($user->getKey())->exists();
    }

    public function update(User $user, Cookbook $cookbook): bool
    {
        return $cookbook->members()
            ->whereKey($user->getKey())
            ->wherePivotIn('role', ['owner', 'editor'])
            ->exists();
    }

    public function delete(User $user, Cookbook $cookbook): bool
    {
        return (int) $cookbook->owner_id === (int) $user->getKey();
    }
}
