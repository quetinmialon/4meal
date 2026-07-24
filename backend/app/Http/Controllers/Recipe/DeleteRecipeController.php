<?php

namespace App\Http\Controllers\Recipe;

use App\Http\Controllers\Controller;
use App\Models\Recipe;
use App\Models\User;
use App\Services\Recipe\DeleteRecipeAction;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class DeleteRecipeController extends Controller
{
    public function __invoke(Request $request, Recipe $recipe, DeleteRecipeAction $action): Response
    {
        /** @var User $user */
        $user = $request->user();
        Gate::forUser($user)->authorize('delete', $recipe);

        $action->execute($recipe);

        return response()->noContent();
    }
}
