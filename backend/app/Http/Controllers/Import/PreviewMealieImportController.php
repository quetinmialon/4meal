<?php

namespace App\Http\Controllers\Import;

use App\Http\Controllers\Controller;
use App\Http\Requests\Import\ImportRequest;
use App\Models\User;
use App\Services\Import\MealieRecipeAdapter;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class PreviewMealieImportController extends Controller
{
    public function __invoke(ImportRequest $request, MealieRecipeAdapter $adapter): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success($request, ['message' => 'Analyse Mealie terminée.', 'analysis' => $adapter->analyze($user, $request->file('file'))]);
    }
}
