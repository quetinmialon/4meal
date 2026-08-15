<?php

namespace App\Http\Controllers\Search;

use App\Http\Controllers\Controller;
use App\Models\SavedSearch;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class DeleteSavedSearchController extends Controller
{
    public function __invoke(Request $request, SavedSearch $savedSearch): Response
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless((int) $savedSearch->user_id === (int) $user->getKey(), 404);
        $savedSearch->delete();

        return response()->noContent();
    }
}
