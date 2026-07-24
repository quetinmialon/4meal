<?php

namespace App\Http\Controllers\Cookbook;

use App\Http\Controllers\Controller;
use App\Models\Cookbook;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class AddCookbookRecipeController extends Controller
{
    public function __invoke(Request $request, Cookbook $cookbook, Recipe $recipe): Response
    {
        /** @var User $user */
        $user = $request->user();
        Gate::forUser($user)->authorize('update', $cookbook);

        abort_unless($recipe->user_id !== null, 422, 'Seules les recettes personnelles peuvent être ajoutées à un cookbook.');

        $cookbook->linkedRecipes()->syncWithoutDetaching([$recipe->getKey()]);

        return response()->noContent();
    }
}
