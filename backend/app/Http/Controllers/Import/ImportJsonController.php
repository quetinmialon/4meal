<?php

namespace App\Http\Controllers\Import;

use App\Exceptions\ImportException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Import\ImportRequest;
use App\Models\User;
use App\Services\Import\SupmealImportService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class ImportJsonController extends Controller
{
    public function __invoke(ImportRequest $request, SupmealImportService $importer): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        try {
            $report = $importer->import($user, $request->file('file'));
        } catch (ImportException $exception) {
            return ApiResponse::error($request, $exception->errorCode, $exception->getMessage(), 422, [
                'errors' => $exception->errors,
            ]);
        }

        return ApiResponse::success($request, [
            'message' => 'Import terminé.',
            'report' => $report,
        ], 201);
    }
}
