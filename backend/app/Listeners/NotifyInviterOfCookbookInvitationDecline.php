<?php

namespace App\Listeners;

use App\Events\CookbookInvitationDeclined;
use App\Models\User;
use App\Notifications\CookbookInvitationNotification;

final class NotifyInviterOfCookbookInvitationDecline
{
    public function handle(CookbookInvitationDeclined $event): void
    {
        $inviter = User::query()->find($event->inviterId);

        if ($inviter instanceof User) {
            $inviter->notify(new CookbookInvitationNotification($event->notificationData()));
        }
    }
}
