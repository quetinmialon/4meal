<?php

namespace App\Broadcasting;

use App\Models\User;

final class UserChannel
{
    public function join(User $user, string $channelUserId): bool
    {
        return (string) $user->getKey() === $channelUserId;
    }
}
