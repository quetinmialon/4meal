<?php

namespace App\Http\Controllers\Cookbook;

use App\Models\Cookbook;
use App\Services\Cookbook\CookbookInvitationService;
use App\Support\ApiResponse;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AcceptCookbookInvitationController
{
    public function __invoke(Request $request, string $token, CookbookInvitationService $service): JsonResponse
    {
        $invitation = $service->accept($token, $request->user());
        /** @var CarbonInterface|null $acceptedAt */
        $acceptedAt = $invitation->getAttribute('accepted_at');
        /** @var Cookbook $cookbook */
        $cookbook = $invitation->cookbook;

        return ApiResponse::success($request, [
            'invitation' => [
                'id' => $invitation->id,
                'accepted_at' => $acceptedAt?->toJSON(),
            ],
            'cookbook' => [
                'id' => $cookbook->public_id,
                'name' => $cookbook->name,
                'role' => $invitation->role,
            ],
        ]);
    }
}
