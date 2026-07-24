<?php

namespace App\Http\Controllers\Recipe;

use App\Http\Controllers\Controller;
use App\Http\Requests\Recipe\ListRecipesRequest;
use App\Http\Resources\RecipeResource;
use App\Models\Recipe;
use App\Models\User;
use App\Services\Recipe\AccessibleRecipesQuery;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class ListRecipesController extends Controller
{
    public function __invoke(
        ListRecipesRequest $request,
        AccessibleRecipesQuery $recipesQuery,
    ): AnonymousResourceCollection {
        /** @var User $user */
        $user = $request->user();
        Gate::forUser($user)->authorize('viewAny', Recipe::class);

        $perPage = $request->integer('per_page', 15);
        $scope = $request->string('scope', 'accessible')->toString();
        $search = $request->string('q')->toString();

        return RecipeResource::collection(
            $recipesQuery->for($user, $scope, $search !== '' ? $search : null, $request->validated())
                ->paginate($perPage)
                ->withQueryString(),
        );
    }
}
