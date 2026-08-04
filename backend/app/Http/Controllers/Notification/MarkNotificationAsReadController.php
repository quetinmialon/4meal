<?php

namespace App\Http\Controllers\Notification;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MarkNotificationAsReadController extends Controller
{
    public function __invoke(Request $request, string $notification): JsonResponse
    {
        $model = $request->user()->notifications()->whereKey($notification)->firstOrFail();
        $model->markAsRead();

        return ApiResponse::success($request, NotificationResource::make($model)->resolve($request));
    }
}
