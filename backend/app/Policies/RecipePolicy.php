<?php

namespace App\Policies;

use App\Models\Cookbook;
use App\Models\Recipe;
use App\Models\User;
use App\Support\CookbookPermissions;

class RecipePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Recipe $recipe): bool
    {
        return $this->ownsPersonally($user, $recipe) || $this->canInCookbook($user, $recipe, CookbookPermissions::VIEW);
    }

    public function create(User $user, ?Cookbook $cookbook = null): bool
    {
        return $cookbook === null
            || CookbookPermissions::allows($this->role($user, $cookbook), CookbookPermissions::UPDATE);
    }

    public function update(User $user, Recipe $recipe): bool
    {
        return $this->ownsPersonally($user, $recipe) || $this->canInCookbook($user, $recipe, CookbookPermissions::UPDATE);
    }

    public function delete(User $user, Recipe $recipe): bool
    {
        return $this->ownsPersonally($user, $recipe)
            || $this->canInCookbook($user, $recipe, CookbookPermissions::DELETE);
    }

    private function ownsPersonally(User $user, Recipe $recipe): bool
    {
        return $recipe->cookbook_id === null && (int) $recipe->user_id === (int) $user->getKey();
    }

    private function canInCookbook(User $user, Recipe $recipe, string $permission): bool
    {
        $cookbook = $recipe->cookbook;

        return $cookbook instanceof Cookbook
            && CookbookPermissions::allows($this->role($user, $cookbook), $permission);
    }

    private function role(User $user, Cookbook $cookbook): ?string
    {
        $role = $cookbook->members()->whereKey($user->getKey())->value('cookbook_members.role');

        return is_string($role) ? $role : null;
    }
}
