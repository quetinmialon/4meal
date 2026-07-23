<?php

namespace App\Services\Recipe;

use App\Models\CookbookMember;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class AccessibleRecipesQuery
{
    public function for(User $user): Builder
    {
        return Recipe::query()
            ->with(['ingredients', 'steps', 'tags', 'author'])
            ->where(function (Builder $query) use ($user): void {
                $query
                    ->where('user_id', $user->getKey())
                    ->orWhereIn(
                        'cookbook_id',
                        CookbookMember::query()
                            ->where('user_id', $user->getKey())
                            ->select('cookbook_id'),
                    );
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }
}
