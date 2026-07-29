<?php

namespace App\Policies;

use App\Models\Cookbook;
use App\Models\Recipe;
use App\Models\RecipeComment;
use App\Models\User;
use App\Support\CookbookPermissions;

class RecipeCommentPolicy
{
    public function viewAny(User $user, Recipe $recipe): bool
    {
        return $this->canComment($user, $recipe);
    }

    public function create(User $user, Recipe $recipe): bool
    {
        return $this->canComment($user, $recipe);
    }

    public function update(User $user, RecipeComment $comment): bool
    {
        return $this->canManageOwnComment($user, $comment);
    }

    public function delete(User $user, RecipeComment $comment): bool
    {
        return $this->canManageOwnComment($user, $comment);
    }

    private function canManageOwnComment(User $user, RecipeComment $comment): bool
    {
        if ((int) $comment->user_id !== (int) $user->getKey()) {
            return false;
        }

        /** @var Recipe $recipe */
        $recipe = $comment->recipe;

        return $this->canComment($user, $recipe);
    }

    private function canComment(User $user, Recipe $recipe): bool
    {
        /** @var Cookbook|null $cookbook */
        $cookbook = $recipe->cookbook;

        if ($cookbook === null) {
            // Personal recipes are commentable independently from cookbooks.
            return $recipe->user_id !== null;
        }

        $role = $cookbook->members()
            ->whereKey($user->getKey())
            ->value('cookbook_members.role');

        return CookbookPermissions::allows(
            is_string($role) ? $role : null,
            CookbookPermissions::COMMENT,
        );
    }
}
