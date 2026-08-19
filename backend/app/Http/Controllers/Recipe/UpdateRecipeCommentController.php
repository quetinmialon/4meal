<?php

namespace App\Http\Controllers\Recipe;

use App\Events\RecipeCommentUpdated;
use App\Http\Controllers\Controller;
use App\Http\Requests\Recipe\UpdateRecipeCommentRequest;
use App\Http\Resources\RecipeCommentResource;
use App\Models\Cookbook;
use App\Models\Recipe;
use App\Models\RecipeComment;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class UpdateRecipeCommentController extends Controller
{
    public function __invoke(
        UpdateRecipeCommentRequest $request,
        Recipe $recipe,
        RecipeComment $comment,
    ): JsonResponse {
        abort_unless((int) $comment->recipe_id === (int) $recipe->getKey(), 404);

        /** @var User $user */
        $user = $request->user();
        Gate::forUser($user)->authorize('update', $comment);

        $comment->update([
            'content' => $request->string('content')->toString(),
            'edited_at' => now(),
        ]);
        $comment->load('user');

        /** @var Cookbook|null $cookbook */
        $cookbook = $recipe->cookbook;
        if ($cookbook !== null) {
            $comment->setAttribute(
                'member_role',
                $cookbook->members()->whereKey($user->getKey())->value('cookbook_members.role'),
            );
        }

        event(new RecipeCommentUpdated($comment));

        return ApiResponse::success($request, RecipeCommentResource::make($comment)->resolve($request));
    }
}
