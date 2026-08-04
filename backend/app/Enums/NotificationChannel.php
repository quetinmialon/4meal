<?php

namespace App\Enums;

enum NotificationChannel: string
{
    case None = 'none';
    case Web = 'web';
    case Mail = 'mail';
    case Both = 'both';

    /** @return list<string> */
    public function laravelChannels(): array
    {
        return match ($this) {
            self::None => [],
            self::Web => ['database'],
            self::Mail => ['mail'],
            self::Both => ['database', 'mail'],
        };
    }
}
