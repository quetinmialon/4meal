<?php

namespace App\Http\Controllers\Search;

use App\Http\Controllers\Controller;
use App\Http\Requests\Search\StoreSavedSearchRequest;
use App\Http\Resources\SavedSearchResource;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class CreateSavedSearchController extends Controller
{
    public function __invoke(StoreSavedSearchRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $savedSearch = $user->savedSearches()->create($request->safe()->only(['name', 'criteria']));

        return ApiResponse::success($request, SavedSearchResource::make($savedSearch)->resolve($request), 201);
    }
}
