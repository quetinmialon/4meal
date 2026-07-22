<?php

namespace App\Http\Controllers\Cookbook;

use App\Http\Controllers\Controller;
use App\Http\Resources\RecipeResource;
use App\Models\Cookbook;
use App\Models\User;
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
            ->orderByDesc('recipes.created_at')
            ->paginate($perPage)
            ->withQueryString();

        return RecipeResource::collection($recipes);
    }
}
