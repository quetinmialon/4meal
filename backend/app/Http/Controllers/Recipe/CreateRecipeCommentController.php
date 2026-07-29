<?php

namespace App\Http\Controllers\Recipe;

use App\Http\Controllers\Controller;
use App\Http\Requests\Recipe\StoreRecipeCommentRequest;
use App\Http\Resources\RecipeCommentResource;
use App\Models\Cookbook;
use App\Models\Recipe;
use App\Models\RecipeComment;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class CreateRecipeCommentController extends Controller
{
    public function __invoke(StoreRecipeCommentRequest $request, Recipe $recipe): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        Gate::forUser($user)->authorize('create', [RecipeComment::class, $recipe]);

        $comment = $recipe->comments()->create([
            'user_id' => $user->getKey(),
            'content' => $request->string('content')->toString(),
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

        return ApiResponse::success($request, RecipeCommentResource::make($comment)->resolve($request), 201);
    }
}
