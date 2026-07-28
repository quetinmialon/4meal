<?php

namespace App\Policies;

use App\Models\Cookbook;
use App\Models\PlannedMeal;
use App\Models\User;
use App\Support\CookbookPermissions;

class PlannedMealPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

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
        $cookbook = $meal->getRelation('cookbook');

        return (int) $meal->user_id === (int) $user->getKey()
            || $this->canViewCookbook($user, $cookbook instanceof Cookbook ? $cookbook : null);
    }

    private function canViewCookbook(User $user, ?Cookbook $cookbook): bool
    {
        if ($cookbook === null) {
            return false;
        }

        $role = $cookbook->members()->whereKey($user->getKey())->value('cookbook_members.role');

        return CookbookPermissions::allows(is_string($role) ? $role : null, CookbookPermissions::VIEW);
    }
}
