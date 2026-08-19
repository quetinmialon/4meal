<?php

namespace App\Http\Controllers\Cookbook;

use App\Events\CookbookInvitationAccepted;
use App\Models\Cookbook;
use App\Models\CookbookInvitation;
use App\Services\Cookbook\CookbookInvitationService;
use App\Support\ApiResponse;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AcceptCookbookInvitationByIdController
{
    public function __invoke(Request $request, CookbookInvitation $cookbookInvitation, CookbookInvitationService $service): JsonResponse
    {
        $invitation = $service->acceptById($cookbookInvitation, $request->user());
        event(new CookbookInvitationAccepted($invitation));
        /** @var CarbonInterface|null $acceptedAt */
        $acceptedAt = $invitation->getAttribute('accepted_at');
        /** @var Cookbook $cookbook */
        $cookbook = $invitation->cookbook;

        return ApiResponse::success($request, [
            'invitation' => ['id' => $invitation->id, 'accepted_at' => $acceptedAt?->toJSON()],
            'cookbook' => ['id' => $cookbook->public_id, 'name' => $cookbook->name, 'role' => $invitation->role],
        ]);
    }
}
