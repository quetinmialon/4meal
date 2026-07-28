<?php

namespace App\Http\Controllers\Planning;

use App\Http\Controllers\Controller;
use App\Models\PlannedMeal;
use App\Models\User;
use App\Services\Planning\DeletePlannedMealAction;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class DeletePlannedMealController extends Controller
{
    public function __invoke(Request $request, PlannedMeal $plannedMeal, DeletePlannedMealAction $action): Response
    {
        /** @var User $user */
        $user = $request->user();
        Gate::forUser($user)->authorize('delete', $plannedMeal);

        $action->execute($plannedMeal);

        return response()->noContent();
    }
}
