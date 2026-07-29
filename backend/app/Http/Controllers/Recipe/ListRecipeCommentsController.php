<?php

namespace App\Http\Controllers\Recipe;

use App\Http\Controllers\Controller;
use App\Http\Requests\Recipe\ListRecipeCommentsRequest;
use App\Http\Resources\RecipeCommentResource;
use App\Models\CookbookMember;
use App\Models\Recipe;
use App\Models\RecipeComment;
use App\Models\User;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class ListRecipeCommentsController extends Controller
{
    public function __invoke(ListRecipeCommentsRequest $request, Recipe $recipe): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();
        Gate::forUser($user)->authorize('viewAny', [RecipeComment::class, $recipe]);

        $perPage = min(max($request->integer('per_page', 20), 1), 100);
        $comments = $recipe->comments()
            ->addSelect([
                'member_role' => CookbookMember::query()
                    ->select('role')
                    ->where('cookbook_members.cookbook_id', $recipe->cookbook_id)
                    ->whereColumn('cookbook_members.user_id', 'recipe_comments.user_id')
                    ->limit(1),
            ])
            ->with('user')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        return RecipeCommentResource::collection($comments);
    }
}
