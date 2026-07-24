<?php

namespace App\Services\Recipe;

use App\Models\CookbookMember;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class AccessibleRecipesQuery
{
    public function for(User $user, string $scope = 'accessible'): Builder
    {
        $query = Recipe::query()
            ->with(['ingredients', 'steps', 'tags', 'author'])
            ->withExists([
                'favoritedBy as is_favorite' => fn (Builder $query) => $query->whereKey($user->getKey()),
            ]);

        if ($scope === 'mine' || $scope === 'public') {
            $query->whereNotNull('user_id');
        }

        if ($scope === 'mine') {
            $query->where('user_id', $user->getKey());
        } elseif ($scope !== 'public') {
            $query->where(function (Builder $query) use ($user): void {
                $query
                    ->whereNotNull('user_id')
                    ->orWhereIn(
                        'cookbook_id',
                        CookbookMember::query()
                            ->where('user_id', $user->getKey())
                            ->select('cookbook_id'),
                    )
                    ->orWhereExists(function ($query) use ($user): void {
                        $query->selectRaw('1')
                            ->from('cookbook_recipe')
                            ->whereColumn('cookbook_recipe.recipe_id', 'recipes.id')
                            ->whereIn('cookbook_recipe.cookbook_id', CookbookMember::query()
                                ->where('user_id', $user->getKey())
                                ->select('cookbook_id'));
                    });
            });
        }

        return $query
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }
}
