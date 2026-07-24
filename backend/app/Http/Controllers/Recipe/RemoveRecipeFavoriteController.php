<?php

namespace App\Http\Controllers\Recipe;

use App\Http\Controllers\Controller;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class RemoveRecipeFavoriteController extends Controller
{
    public function __invoke(Request $request, Recipe $recipe): Response
    {
        /** @var User $user */
        $user = $request->user();
        Gate::forUser($user)->authorize('view', $recipe);

        $user->favoriteRecipes()->detach($recipe->getKey());

        return response()->noContent();
    }
}
