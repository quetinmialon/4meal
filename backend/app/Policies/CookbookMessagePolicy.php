<?php

namespace App\Policies;

use App\Models\Cookbook;
use App\Models\CookbookMessage;
use App\Models\User;
use App\Support\CookbookPermissions;

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

    public function update(User $user, CookbookMessage $message): bool
    {
        /** @var Cookbook $cookbook */
        $cookbook = $message->cookbook;

        return $message->deleted_at === null
            && (int) $message->user_id === (int) $user->getKey()
            && $this->isMember($user, $cookbook);
    }

    public function delete(User $user, CookbookMessage $message): bool
    {
        /** @var Cookbook $cookbook */
        $cookbook = $message->cookbook;
        if ((int) $message->user_id === (int) $user->getKey()) {
            return $this->isMember($user, $cookbook);
        }

        $role = $cookbook->members()->whereKey($user->getKey())->value('cookbook_members.role');

        return CookbookPermissions::allows(is_string($role) ? $role : null, CookbookPermissions::MODERATE_MESSAGES);
    }

    public function react(User $user, CookbookMessage $message): bool
    {
        /** @var Cookbook $cookbook */
        $cookbook = $message->cookbook;

        return $message->deleted_at === null
            && $this->isMember($user, $cookbook);
    }

    public function unreact(User $user, CookbookMessage $message): bool
    {
        return $this->react($user, $message);
    }

    private function isMember(User $user, Cookbook $cookbook): bool
    {
        return $cookbook->members()->whereKey($user->getKey())->exists();
    }
}
