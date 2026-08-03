<?php

namespace App\Http\Controllers\Planning;

use App\Http\Controllers\Controller;
use App\Http\Requests\Planning\ListPlannedMealsRequest;
use App\Models\PlannedMeal;
use App\Models\User;
use App\Services\Planning\AccessiblePlannedMealsQuery;
use App\Services\Planning\ShoppingListGenerator;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class GenerateShoppingListController extends Controller
{
    public function __invoke(
        ListPlannedMealsRequest $request,
        AccessiblePlannedMealsQuery $mealsQuery,
        ShoppingListGenerator $generator,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        Gate::forUser($user)->authorize('viewAny', PlannedMeal::class);

        /** @var Collection<int, PlannedMeal> $meals */
        $meals = $mealsQuery->for(
            $user,
            $request->string('from')->toString(),
            $request->string('to')->toString(),
            $request->filled('cookbook_id') ? $request->string('cookbook_id')->toString() : null,
        )->get();

        return ApiResponse::success($request, $generator->generate($meals));
    }
}
