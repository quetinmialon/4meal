<?php

namespace App\Http\Controllers\Import;

use App\Exceptions\ImportException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Import\ImportCsvRequest;
use App\Models\User;
use App\Services\Import\RecipeCsvImportService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class ImportCsvController extends Controller
{
    public function __invoke(ImportCsvRequest $request, RecipeCsvImportService $importer): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        try {
            $report = $importer->import($user, $request->file('file'));
        } catch (ImportException $exception) {
            return ApiResponse::error($request, $exception->errorCode, $exception->getMessage(), 422, ['errors' => $exception->errors]);
        }

        return ApiResponse::success($request, ['message' => 'Import CSV terminé.', 'report' => $report], 201);
    }
}
