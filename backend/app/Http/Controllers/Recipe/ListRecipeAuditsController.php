<?php

namespace App\Http\Controllers\Recipe;

use App\Http\Controllers\Controller;
use App\Http\Resources\RecipeAuditResource;
use App\Models\Recipe;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ListRecipeAuditsController extends Controller
{
    public function __invoke(Request $request, Recipe $recipe): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        Gate::forUser($user)->authorize('view', $recipe);

        $perPage = min(max($request->integer('per_page', 20), 1), 100);
        $audits = $recipe->audits()
            ->with('actor')
            ->latest('created_at')
            ->latest('id')
            ->cursorPaginate($perPage, ['*'], 'cursor', $request->input('cursor'));

        return ApiResponse::success(
            $request,
            RecipeAuditResource::collection($audits->items())->resolve($request),
            200,
            [
                'pagination' => [
                    'per_page' => $audits->perPage(),
                    'next_cursor' => $audits->nextCursor()?->encode(),
                    'previous_cursor' => $audits->previousCursor()?->encode(),
                ],
            ],
        );
    }
}
