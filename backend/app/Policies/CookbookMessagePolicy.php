<?php

namespace App\Policies;

use App\Models\Cookbook;
use App\Models\User;

class CookbookMessagePolicy
{
    public function viewAny(User $user, Cookbook $cookbook): bool
    {
        return $this->isMember($user, $cookbook);
    }

    public function create(User $user, Cookbook $cookbook): bool
    {
        return $this->isMember($user, $cookbook);
    }

    private function isMember(User $user, Cookbook $cookbook): bool
    {
        return $cookbook->members()->whereKey($user->getKey())->exists();
    }
}
