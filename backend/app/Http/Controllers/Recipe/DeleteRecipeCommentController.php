<?php

namespace App\Http\Controllers\Recipe;

use App\Events\RecipeCommentDeleted;
use App\Http\Controllers\Controller;
use App\Models\Recipe;
use App\Models\RecipeComment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class DeleteRecipeCommentController extends Controller
{
    public function __invoke(Request $request, Recipe $recipe, RecipeComment $comment): Response
    {
        abort_unless((int) $comment->recipe_id === (int) $recipe->getKey(), 404);

        /** @var User $user */
        $user = $request->user();
        Gate::forUser($user)->authorize('delete', $comment);
        $comment->delete();
        event(new RecipeCommentDeleted($comment));

        return response()->noContent();
    }
}
