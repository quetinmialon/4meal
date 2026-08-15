<?php

namespace App\Http\Controllers\Cookbook;

use App\Http\Controllers\Controller;
use App\Http\Resources\RecipeResource;
use App\Models\Cookbook;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class ListCookbookRecipesController extends Controller
{
    public function __invoke(Request $request, Cookbook $cookbook): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();
        Gate::forUser($user)->authorize('view', $cookbook);

        $perPage = min(max($request->integer('per_page', 15), 1), 100);
        $recipes = $cookbook->recipes()
            ->with(['ingredients', 'steps', 'tags', 'author'])
            ->withExists([
                'favoritedBy as is_favorite' => fn (Builder $query) => $query->whereKey($user->getKey()),
            ])
            ->withAggregate([
                'ratings as personal_rating' => fn (Builder $query) => $query->where('user_id', $user->getKey()),
            ], 'rating')
            ->withAvg('ratings as average_rating', 'rating')
            ->withCount('ratings')
            ->orWhereHas('cookbooks', fn (Builder $query) => $query->whereKey($cookbook->getKey()))
            ->orderByDesc('recipes.created_at')
            ->paginate($perPage)
            ->withQueryString();

        return RecipeResource::collection($recipes);
    }
}
