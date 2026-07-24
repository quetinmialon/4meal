<?php

namespace App\Http\Controllers\Cookbook;

use App\Http\Controllers\Controller;
use App\Models\Cookbook;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class RemoveCookbookRecipeController extends Controller
{
    public function __invoke(Request $request, Cookbook $cookbook, Recipe $recipe): Response
    {
        /** @var User $user */
        $user = $request->user();
        Gate::forUser($user)->authorize('update', $cookbook);

        $cookbook->linkedRecipes()->detach($recipe->getKey());

        return response()->noContent();
    }
}
