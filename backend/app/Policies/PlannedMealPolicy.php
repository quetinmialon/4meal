<?php

namespace App\Policies;

use App\Models\Cookbook;
use App\Models\PlannedMeal;
use App\Models\User;
use App\Support\CookbookPermissions;

class PlannedMealPolicy
{
    public function create(User $user, ?Cookbook $cookbook = null): bool
    {
        if ($cookbook === null) {
            return true;
        }

        $role = $cookbook->members()->whereKey($user->getKey())->value('cookbook_members.role');

        return CookbookPermissions::allows(is_string($role) ? $role : null, CookbookPermissions::UPDATE);
    }

    public function view(User $user, PlannedMeal $meal): bool
    {
        return (int) $meal->user_id === (int) $user->getKey()
            || $this->create($user, $meal->cookbook);
    }
}
