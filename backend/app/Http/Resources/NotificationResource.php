<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Notifications\DatabaseNotification;

/** @mixin DatabaseNotification */
class NotificationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var DatabaseNotification $notification */
        $notification = $this->resource;

        return [
            'id' => $notification->getKey(),
            'type' => $notification->data['type'] ?? $notification->type,
            'data' => $notification->data,
            'read_at' => $notification->read_at?->toJSON(),
            'created_at' => $notification->created_at?->toJSON(),
        ];
    }
}
