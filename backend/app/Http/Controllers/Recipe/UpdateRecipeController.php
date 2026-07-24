<?php

namespace App\Http\Controllers\Recipe;

use App\Http\Controllers\Controller;
use App\Http\Requests\Recipe\UpdateRecipeRequest;
use App\Http\Resources\RecipeResource;
use App\Models\Recipe;
use App\Models\User;
use App\Services\Recipe\UpdateRecipeAction;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class UpdateRecipeController extends Controller
{
    public function __invoke(
        UpdateRecipeRequest $request,
        Recipe $recipe,
        UpdateRecipeAction $action,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        Gate::forUser($user)->authorize('update', $recipe);

        $recipe = $action->execute($user, $recipe, $request->validated());

        return ApiResponse::success(
            $request,
            RecipeResource::make($recipe)->resolve($request),
        );
    }
}
