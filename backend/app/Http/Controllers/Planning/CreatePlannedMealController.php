<?php

namespace App\Http\Controllers\Planning;

use App\Http\Controllers\Controller;
use App\Http\Requests\Planning\StorePlannedMealRequest;
use App\Http\Resources\PlannedMealResource;
use App\Models\Cookbook;
use App\Models\Recipe;
use App\Models\User;
use App\Services\Planning\CreatePlannedMealAction;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class CreatePlannedMealController extends Controller
{
    public function __invoke(StorePlannedMealRequest $request, CreatePlannedMealAction $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $recipe = Recipe::query()->where('public_id', $request->string('recipe_id')->toString())->firstOrFail();
        $cookbook = $request->filled('cookbook_id')
            ? Cookbook::query()->where('public_id', $request->string('cookbook_id')->toString())->firstOrFail()
            : null;

        Gate::forUser($user)->authorize('create', [\App\Models\PlannedMeal::class, $cookbook]);
        Gate::forUser($user)->authorize('view', $recipe);

        $meal = $action->execute($user, $recipe, $cookbook, $request->validated());

        return ApiResponse::success($request, PlannedMealResource::make($meal)->resolve($request), 201);
    }
}
