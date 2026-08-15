<?php

namespace App\Http\Controllers\Import;

use App\Http\Controllers\Controller;
use App\Http\Requests\Import\ImportRequest;
use App\Models\User;
use App\Services\Import\SupmealImportService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class PreviewImportController extends Controller
{
    public function __invoke(ImportRequest $request, SupmealImportService $importer): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success($request, [
            'message' => 'Analyse d’import terminée.',
            'analysis' => $importer->analyze($user, $request->file('file')),
        ]);
    }
}
