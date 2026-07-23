<?php

namespace App\Http\Controllers\Cookbook;

use App\Http\Resources\CookbookInvitationResource;
use App\Services\Cookbook\CookbookInvitationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShowCookbookInvitationController
{
    public function __invoke(Request $request, string $token, CookbookInvitationService $service): JsonResponse
    {
        $invitation = $service->findByToken($token);

        return ApiResponse::success($request, CookbookInvitationResource::make($invitation)->resolve($request));
    }
}
