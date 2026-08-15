<?php

namespace App\Http\Controllers\Search;

use App\Http\Controllers\Controller;
use App\Http\Resources\RecipeResource;
use App\Models\Recipe;
use App\Models\SavedSearch;
use App\Models\User;
use App\Services\Recipe\AccessibleRecipesQuery;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class ExecuteSavedSearchController extends Controller
{
    public function __invoke(Request $request, SavedSearch $savedSearch, AccessibleRecipesQuery $recipesQuery): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless((int) $savedSearch->user_id === (int) $user->getKey(), 404);
        Gate::forUser($user)->authorize('viewAny', Recipe::class);

        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);
        $perPage = (int) ($validated['per_page'] ?? 15);
        $criteria = $savedSearch->getAttribute('criteria');
        $criteria = is_array($criteria) ? $criteria : [];
        $scope = is_string($criteria['scope'] ?? null) ? $criteria['scope'] : 'accessible';
        $search = is_string($criteria['q'] ?? null) ? $criteria['q'] : null;

        return RecipeResource::collection(
            $recipesQuery->for($user, $scope, $search, $criteria)
                ->paginate($perPage)
                ->withQueryString(),
        );
    }
}
