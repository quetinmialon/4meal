<?php

namespace App\Http\Controllers\Cookbook;

use App\Http\Controllers\Controller;
use App\Http\Resources\CookbookResource;
use App\Models\Cookbook;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ShowCookbookController extends Controller
{
    public function __invoke(Request $request, Cookbook $cookbook): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        Gate::forUser($user)->authorize('view', $cookbook);

        return ApiResponse::success(
            $request,
            CookbookResource::make($cookbook->load('owner'))->resolve($request),
        );
    }
}
