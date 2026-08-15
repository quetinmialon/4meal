<?php

namespace App\Http\Controllers\Recipe;

use App\Http\Controllers\Controller;
use App\Http\Requests\Recipe\StoreRecipeRatingRequest;
use App\Models\Recipe;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class UpsertRecipeRatingController extends Controller
{
    public function __invoke(StoreRecipeRatingRequest $request, Recipe $recipe): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        Gate::forUser($user)->authorize('view', $recipe);

        $rating = $recipe->ratings()->updateOrCreate(
            ['user_id' => $user->getKey()],
            ['rating' => $request->integer('rating')],
        );

        return ApiResponse::success($request, [
            'recipe_id' => $recipe->public_id,
            'rating' => $rating->rating,
        ]);
    }
}
