<?php

namespace App\Http\Controllers\Notification;

use App\Http\Controllers\Controller;
use App\Http\Requests\Notification\ListNotificationsRequest;
use App\Http\Resources\NotificationResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class ListNotificationsController extends Controller
{
    public function __invoke(ListNotificationsRequest $request): JsonResponse
    {
        $notifications = $request->user()->notifications()
            ->latest()
            ->paginate(min(max($request->integer('per_page', 20), 1), 100));

        return ApiResponse::success($request, NotificationResource::collection($notifications->items())->resolve($request), 200, [
            'pagination' => [
                'current_page' => $notifications->currentPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
                'last_page' => $notifications->lastPage(),
                'has_more_pages' => $notifications->hasMorePages(),
            ],
            'unread_count' => $request->user()->unreadNotifications()->count(),
        ]);
    }
}
