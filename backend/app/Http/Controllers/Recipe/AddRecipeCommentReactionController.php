<?php

namespace App\Http\Controllers\Recipe;

use App\Http\Controllers\Controller;
use App\Http\Requests\Recipe\StoreRecipeCommentReactionRequest;
use App\Models\Recipe;
use App\Models\RecipeComment;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class AddRecipeCommentReactionController extends Controller
{
    public function __invoke(StoreRecipeCommentReactionRequest $request, Recipe $recipe, RecipeComment $comment): JsonResponse
    {
        abort_unless((int) $comment->recipe_id === (int) $recipe->getKey(), 404);
        /** @var User $user */
        $user = $request->user();
        Gate::forUser($user)->authorize('react', $comment);
        $reaction = $comment->reactions()->firstOrCreate([
            'user_id' => $user->getKey(),
            'emoji' => $request->string('emoji')->toString(),
        ]);

        return ApiResponse::success($request, ['comment_id' => $comment->public_id, 'emoji' => $reaction->emoji], $reaction->wasRecentlyCreated ? 201 : 200);
    }
}
