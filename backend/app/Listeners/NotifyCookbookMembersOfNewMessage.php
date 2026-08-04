<?php

namespace App\Listeners;

use App\Events\CookbookMessageCreated;
use App\Models\Cookbook;
use App\Models\User;
use App\Notifications\NewCookbookMessageNotification;
use Illuminate\Database\Eloquent\Collection;

final class NotifyCookbookMembersOfNewMessage
{
    public function handle(CookbookMessageCreated $event): void
    {
        $message = $event->message->loadMissing('cookbook', 'user');
        /** @var Cookbook $cookbook */
        $cookbook = $message->cookbook;

        /** @var Collection<int, User> $members */
        $members = $cookbook->members()
            ->whereKeyNot($message->user_id)
            ->get();

        $members->each(function (User $member) use ($message): void {
            $member->notify(new NewCookbookMessageNotification($message));
        });
    }
}
