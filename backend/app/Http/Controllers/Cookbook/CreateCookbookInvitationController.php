<?php

namespace App\Http\Controllers\Cookbook;

use App\Events\CookbookInvitationCreated;
use App\Http\Requests\Cookbook\StoreCookbookInvitationRequest;
use App\Http\Resources\CookbookInvitationResource;
use App\Models\Cookbook;
use App\Models\User;
use App\Services\Cookbook\CookbookInvitationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class CreateCookbookInvitationController
{
    public function __invoke(StoreCookbookInvitationRequest $request, Cookbook $cookbook, CookbookInvitationService $service): JsonResponse
    {
        $invitation = $service->create(
            $cookbook,
            $request->user(),
            $request->validated('email'),
            $request->validated('role'),
        );

        $recipientId = User::query()->where('email', $invitation->email)->value('id');
        if ($recipientId !== null) {
            event(new CookbookInvitationCreated($invitation, (int) $recipientId));
        }

        return ApiResponse::success($request, CookbookInvitationResource::make($invitation)->resolve($request), 201);
    }
}
