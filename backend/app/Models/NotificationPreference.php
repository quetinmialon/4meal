<?php

namespace App\Models;

use App\Enums\NotificationChannel;
use App\Enums\NotificationType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property NotificationType $type
 * @property NotificationChannel $channel
 */
class NotificationPreference extends Model
{
    protected $fillable = ['user_id', 'type', 'channel'];

    protected function casts(): array
    {
        return [
            'type' => NotificationType::class,
            'channel' => NotificationChannel::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
