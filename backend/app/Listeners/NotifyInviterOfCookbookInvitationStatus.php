<?php

namespace App\Listeners;

use App\Events\CookbookInvitationAccepted;
use App\Models\User;
use App\Notifications\CookbookInvitationNotification;

final class NotifyInviterOfCookbookInvitationStatus
{
    public function handle(CookbookInvitationAccepted $event): void
    {
        $inviter = User::query()->find($event->inviterId);

        if ($inviter instanceof User) {
            $inviter->notify(new CookbookInvitationNotification($event->notificationData()));
        }
    }
}
