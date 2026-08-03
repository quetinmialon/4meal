<?php

namespace App\Http\Controllers\Recipe;

use App\Http\Controllers\Controller;
use App\Http\Requests\Recipe\DuplicateRecipeRequest;
use App\Http\Resources\RecipeResource;
use App\Models\Cookbook;
use App\Models\Recipe;
use App\Models\User;
use App\Services\Recipe\DuplicateRecipeAction;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class DuplicateRecipeController extends Controller
{
    public function __invoke(
        DuplicateRecipeRequest $request,
        Recipe $recipe,
        DuplicateRecipeAction $action,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        Gate::forUser($user)->authorize('view', $recipe);

        $cookbook = $request->filled('cookbook_id')
            ? Cookbook::query()->where('public_id', $request->string('cookbook_id')->toString())->firstOrFail()
            : null;
        Gate::forUser($user)->authorize(
            'create',
            $cookbook === null ? Recipe::class : [Recipe::class, $cookbook],
        );

        $copy = $action->execute($user, $recipe, $cookbook);

        return ApiResponse::success(
            $request,
            RecipeResource::make($copy)->resolve($request),
            201,
        );
    }
}
