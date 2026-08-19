<?php

namespace App\Http\Controllers\Cookbook;

use App\Events\CookbookInvitationDeclined;
use App\Http\Resources\CookbookInvitationResource;
use App\Models\CookbookInvitation;
use App\Services\Cookbook\CookbookInvitationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeclineCookbookInvitationController
{
    public function __invoke(Request $request, CookbookInvitation $cookbookInvitation, CookbookInvitationService $service): JsonResponse
    {
        $invitation = $service->decline($cookbookInvitation, $request->user());
        event(new CookbookInvitationDeclined($invitation));

        return ApiResponse::success($request, CookbookInvitationResource::make($invitation)->resolve($request));
    }
}
