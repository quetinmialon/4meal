<?php

namespace App\Http\Controllers\Recipe;

use App\Http\Controllers\Controller;
use App\Http\Resources\RecipeResource;
use App\Models\Recipe;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ShowRecipeController extends Controller
{
    public function __invoke(Request $request, Recipe $recipe): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        Gate::forUser($user)->authorize('view', $recipe);

        $recipe->loadExists([
            'favoritedBy as is_favorite' => fn ($query) => $query->whereKey($user->getKey()),
        ]);
        $recipe->load(['ingredients', 'steps', 'tags', 'author']);

        return ApiResponse::success(
            $request,
            RecipeResource::make($recipe)->resolve($request),
        );
    }
}
