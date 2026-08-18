<?php

namespace App\Policies;

use App\Models\Cookbook;
use App\Models\User;
use App\Support\CookbookPermissions;

class CookbookPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Cookbook $cookbook): bool
    {
        return $this->hasPermission($user, $cookbook, CookbookPermissions::VIEW);
    }

    public function update(User $user, Cookbook $cookbook): bool
    {
        return $this->hasPermission($user, $cookbook, CookbookPermissions::UPDATE);
    }

    public function remove_recipes(User $user, Cookbook $cookbook): bool
    {
        return $this->hasPermission($user, $cookbook, CookbookPermissions::REMOVE_RECIPES);
    }

    public function manage_members(User $user, Cookbook $cookbook): bool
    {
        return $this->hasPermission($user, $cookbook, CookbookPermissions::MANAGE_MEMBERS);
    }

    public function invite_members(User $user, Cookbook $cookbook): bool
    {
        return $this->hasPermission($user, $cookbook, CookbookPermissions::INVITE_MEMBERS);
    }

    public function leave(User $user, Cookbook $cookbook): bool
    {
        return $this->hasPermission($user, $cookbook, CookbookPermissions::LEAVE);
    }

    public function remove_members(User $user, Cookbook $cookbook): bool
    {
        return $this->hasPermission($user, $cookbook, CookbookPermissions::REMOVE_MEMBERS);
    }

    public function delete(User $user, Cookbook $cookbook): bool
    {
        return $this->hasPermission($user, $cookbook, CookbookPermissions::DELETE)
            && (int) $cookbook->owner_id === (int) $user->getKey();
    }

    private function hasPermission(User $user, Cookbook $cookbook, string $permission): bool
    {
        $role = $cookbook->members()
            ->whereKey($user->getKey())
            ->value('cookbook_members.role');

        return CookbookPermissions::allows(is_string($role) ? $role : null, $permission);
    }
}
