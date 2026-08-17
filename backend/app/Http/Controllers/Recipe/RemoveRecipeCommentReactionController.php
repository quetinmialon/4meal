<?php

namespace App\Http\Controllers\Recipe;

use App\Http\Controllers\Controller;
use App\Http\Requests\Recipe\StoreRecipeCommentReactionRequest;
use App\Models\Recipe;
use App\Models\RecipeComment;
use App\Models\User;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class RemoveRecipeCommentReactionController extends Controller
{
    public function __invoke(StoreRecipeCommentReactionRequest $request, Recipe $recipe, RecipeComment $comment): Response
    {
        abort_unless((int) $comment->recipe_id === (int) $recipe->getKey(), 404);
        /** @var User $user */
        $user = $request->user();
        Gate::forUser($user)->authorize('unreact', $comment);
        $comment->reactions()->where('user_id', $user->getKey())->where('emoji', $request->string('emoji')->toString())->delete();

        return response()->noContent();
    }
}
