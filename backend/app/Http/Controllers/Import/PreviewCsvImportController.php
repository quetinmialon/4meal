<?php

namespace App\Http\Controllers\Import;

use App\Http\Controllers\Controller;
use App\Http\Requests\Import\ImportCsvRequest;
use App\Models\User;
use App\Services\Import\RecipeCsvImportService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class PreviewCsvImportController extends Controller
{
    public function __invoke(ImportCsvRequest $request, RecipeCsvImportService $importer): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success($request, ['message' => 'Analyse CSV terminée.', 'analysis' => $importer->analyze($user, $request->file('file'))]);
    }
}
