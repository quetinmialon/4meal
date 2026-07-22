<?php

namespace App\Policies;

use App\Models\Cookbook;
use App\Models\User;

class CookbookPolicy
{
    public function view(User $user, Cookbook $cookbook): bool
    {
        return $cookbook->members()->whereKey($user->getKey())->exists();
    }
}
