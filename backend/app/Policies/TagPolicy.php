<?php

namespace App\Policies;

use App\Models\Tag;
use App\Models\User;

class TagPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Tag $tag): bool
    {
        return $this->owns($user, $tag);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Tag $tag): bool
    {
        return $this->owns($user, $tag);
    }

    public function delete(User $user, Tag $tag): bool
    {
        return $this->owns($user, $tag);
    }

    private function owns(User $user, Tag $tag): bool
    {
        return (int) $tag->user_id === (int) $user->getKey();
    }
}
