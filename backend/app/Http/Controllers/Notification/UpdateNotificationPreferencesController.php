<?php

namespace App\Http\Controllers\Notification;

use App\Enums\NotificationChannel;
use App\Enums\NotificationType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Notification\UpdateNotificationPreferencesRequest;
use App\Http\Resources\NotificationPreferenceResource;
use App\Models\NotificationPreference;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class UpdateNotificationPreferencesController extends Controller
{
    public function __invoke(UpdateNotificationPreferencesRequest $request): JsonResponse
    {
        foreach ($request->validated('preferences') as $preference) {
            $request->user()->notificationPreferences()->updateOrCreate(
                ['type' => $preference['type']],
                ['channel' => $preference['channel']],
            );
        }

        $stored = $request->user()->notificationPreferences()->get()->keyBy(fn (NotificationPreference $preference): string => $preference->type->value);
        $preferences = collect(NotificationType::current())->map(fn (NotificationType $type): array => [
            'type' => $type->value,
            'channel' => $this->channelValue($stored->get($type->value)),
        ])->all();

        return ApiResponse::success($request, NotificationPreferenceResource::collection($preferences)->resolve($request));
    }

    private function channelValue(?NotificationPreference $preference): string
    {
        return $preference?->channel->value ?? NotificationChannel::Both->value;
    }
}
