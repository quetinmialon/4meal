<?php

namespace App\Http\Controllers\Cookbook;

use App\Http\Resources\CookbookInvitationResource;
use App\Services\Cookbook\CookbookInvitationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ListCookbookInvitationsController
{
    public function __invoke(Request $request, CookbookInvitationService $service): JsonResponse
    {
        return ApiResponse::success(
            $request,
            CookbookInvitationResource::collection($service->pendingFor($request->user()))->resolve($request),
        );
    }
}
