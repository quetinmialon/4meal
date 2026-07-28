<?php

namespace App\Http\Controllers\Planning;

use App\Http\Controllers\Controller;
use App\Http\Requests\Planning\ListPlannedMealsRequest;
use App\Http\Resources\PlannedMealResource;
use App\Models\PlannedMeal;
use App\Models\User;
use App\Services\Planning\AccessiblePlannedMealsQuery;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class ListPlannedMealsController extends Controller
{
    public function __invoke(
        ListPlannedMealsRequest $request,
        AccessiblePlannedMealsQuery $mealsQuery,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        Gate::forUser($user)->authorize('viewAny', PlannedMeal::class);

        return ApiResponse::success($request, PlannedMealResource::collection(
            $mealsQuery->for(
                $user,
                $request->string('from')->toString(),
                $request->string('to')->toString(),
                $request->filled('cookbook_id') ? $request->string('cookbook_id')->toString() : null,
            )->get(),
        )->resolve($request));
    }
}
