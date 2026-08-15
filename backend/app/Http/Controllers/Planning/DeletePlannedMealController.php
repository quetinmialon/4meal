<?php

namespace App\Http\Controllers\Planning;

use App\Http\Controllers\Controller;
use App\Http\Requests\Planning\DeletePlannedMealRequest;
use App\Models\PlannedMeal;
use App\Models\User;
use App\Services\Planning\DeletePlannedMealAction;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class DeletePlannedMealController extends Controller
{
    public function __invoke(DeletePlannedMealRequest $request, PlannedMeal $plannedMeal, DeletePlannedMealAction $action): Response
    {
        /** @var User $user */
        $user = $request->user();
        Gate::forUser($user)->authorize('delete', $plannedMeal);

        $action->execute($plannedMeal, $request->string('scope')->toString() === 'series');

        return response()->noContent();
    }
}
