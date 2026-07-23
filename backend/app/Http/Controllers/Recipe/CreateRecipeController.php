<?php

namespace App\Http\Controllers\Recipe;

use App\Http\Controllers\Controller;
use App\Http\Requests\Recipe\StoreRecipeRequest;
use App\Http\Resources\RecipeResource;
use App\Models\Cookbook;
use App\Models\Recipe;
use App\Models\User;
use App\Services\Recipe\CreateRecipeAction;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class CreateRecipeController extends Controller
{
    public function __invoke(StoreRecipeRequest $request, CreateRecipeAction $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $cookbook = $request->filled('cookbook_id')
            ? Cookbook::query()->where('public_id', $request->string('cookbook_id')->toString())->firstOrFail()
            : null;

        Gate::forUser($user)->authorize(
            'create',
            $cookbook === null ? Recipe::class : [Recipe::class, $cookbook],
        );

        $recipe = $action->execute($user, $cookbook, $request->validated());

        return ApiResponse::success(
            $request,
            RecipeResource::make($recipe)->resolve($request),
            201,
        );
    }
}
