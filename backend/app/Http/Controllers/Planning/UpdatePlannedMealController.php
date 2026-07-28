<?php

namespace App\Http\Controllers\Planning;

use App\Http\Controllers\Controller;
use App\Http\Requests\Planning\UpdatePlannedMealRequest;
use App\Http\Resources\PlannedMealResource;
use App\Models\PlannedMeal;
use App\Models\User;
use App\Services\Planning\UpdatePlannedMealAction;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class UpdatePlannedMealController extends Controller
{
    public function __invoke(
        UpdatePlannedMealRequest $request,
        PlannedMeal $plannedMeal,
        UpdatePlannedMealAction $action,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        Gate::forUser($user)->authorize('update', $plannedMeal);

        $plannedMeal = $action->execute($plannedMeal, $request->validated());

        return ApiResponse::success($request, PlannedMealResource::make($plannedMeal)->resolve($request));
    }
}
