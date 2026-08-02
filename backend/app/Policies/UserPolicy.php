<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function update(User $user, User $profile): bool
    {
        return (int) $user->getKey() === (int) $profile->getKey();
    }
}
