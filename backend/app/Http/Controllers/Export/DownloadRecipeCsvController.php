<?php

namespace App\Http\Controllers\Export;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Export\RecipeCsvExportStreamer;
use Illuminate\Http\Request;

final class DownloadRecipeCsvController extends Controller
{
    public function __invoke(Request $request, RecipeCsvExportStreamer $exporter)
    {
        /** @var User $user */
        $user = $request->user();

        return response()->streamDownload(
            fn () => $exporter->stream($user),
            '4meal-recipes-'.now('UTC')->format('Ymd-His').'.csv',
            ['Content-Type' => 'text/csv; charset=UTF-8'],
        );
    }
}
