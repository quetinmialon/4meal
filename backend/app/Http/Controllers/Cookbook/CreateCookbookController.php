<?php

namespace App\Http\Controllers\Cookbook;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cookbook\StoreCookbookRequest;
use App\Http\Resources\CookbookResource;
use App\Models\User;
use App\Services\Cookbook\CreateCookbookAction;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class CreateCookbookController extends Controller
{
    public function __invoke(StoreCookbookRequest $request, CreateCookbookAction $action): JsonResponse
    {
        /** @var User $owner */
        $owner = $request->user();
        $cookbook = $action->execute($owner, $request->safe()->only(['name']));

        Gate::forUser($owner)->authorize('view', $cookbook);

        return ApiResponse::success(
            $request,
            CookbookResource::make($cookbook)->resolve($request),
            201,
        );
    }
}
