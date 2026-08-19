<?php

namespace App\Http\Controllers\Notification;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MarkAllNotificationsAsReadController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $readAt = now();
        $markedCount = $request->user()->unreadNotifications()->update(['read_at' => $readAt]);

        return ApiResponse::success($request, [
            'marked_count' => $markedCount,
            'read_at' => $readAt->toJSON(),
            'unread_count' => 0,
        ]);
    }
}
