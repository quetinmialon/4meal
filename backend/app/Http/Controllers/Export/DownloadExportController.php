<?php

namespace App\Http\Controllers\Export;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Export\SupmealExportStreamer;
use Illuminate\Http\Request;

final class DownloadExportController extends Controller
{
    public function __invoke(Request $request, SupmealExportStreamer $exporter)
    {
        /** @var User $user */
        $user = $request->user();
        $filename = '4meal-export-'.now('UTC')->format('Ymd-His').'.json';

        return response()->streamDownload(
            function () use ($exporter, $user): void {
                $exporter->stream($user);
            },
            $filename,
            [
                'Content-Type' => 'application/json; charset=UTF-8',
                'X-4Meal-Export-Warning' => 'Export en clair : protégez ce fichier comme des données sensibles.',
            ],
        );
    }
}
