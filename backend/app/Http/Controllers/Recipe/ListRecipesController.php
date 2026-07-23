<?php

namespace App\Http\Controllers\Recipe;

use App\Http\Controllers\Controller;
use App\Http\Resources\RecipeResource;
use App\Models\Recipe;
use App\Models\User;
use App\Services\Recipe\AccessibleRecipesQuery;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class ListRecipesController extends Controller
{
    public function __invoke(
        Request $request,
        AccessibleRecipesQuery $recipesQuery,
    ): AnonymousResourceCollection {
        /** @var User $user */
        $user = $request->user();
        Gate::forUser($user)->authorize('viewAny', Recipe::class);

        $perPage = min(max($request->integer('per_page', 15), 1), 100);

        return RecipeResource::collection(
            $recipesQuery->for($user)->paginate($perPage)->withQueryString(),
        );
    }
}
