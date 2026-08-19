<?php

namespace App\Listeners;

use App\Events\CookbookInvitationCreated;
use App\Models\User;
use App\Notifications\CookbookInvitationNotification;

final class NotifyUserOfCookbookInvitation
{
    public function handle(CookbookInvitationCreated $event): void
    {
        $recipient = User::query()->find($event->recipientId);

        if ($recipient instanceof User) {
            $recipient->notify(new CookbookInvitationNotification($event->notificationData()));
        }
    }
}
