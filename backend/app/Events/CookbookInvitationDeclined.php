<?php

namespace App\Events;

use App\Models\CookbookInvitation;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

final class CookbookInvitationDeclined implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable;

    public readonly int $invitationId;

    public readonly string $cookbookId;

    public readonly string $cookbookName;

    public readonly int $inviterId;

    public readonly int $declinedBy;

    public function __construct(CookbookInvitation $invitation)
    {
        $invitation->loadMissing('cookbook');

        $this->invitationId = (int) $invitation->getKey();
        $this->cookbookId = (string) $invitation->cookbook->public_id;
        $this->cookbookName = (string) $invitation->cookbook->name;
        $this->inviterId = (int) $invitation->invited_by;
        $this->declinedBy = (int) $invitation->declined_by;
    }

    /** @return list<PrivateChannel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('user.'.$this->inviterId)];
    }

    public function broadcastAs(): string
    {
        return 'cookbook.invitation.declined';
    }

    /** @return array{invitation: array<string, mixed>} */
    public function broadcastWith(): array
    {
        return ['invitation' => $this->notificationData()['invitation']];
    }

    /** @return array{type: string, status: string, invitation: array<string, mixed>} */
    public function notificationData(): array
    {
        return [
            'type' => 'cookbook_invitation',
            'status' => 'declined',
            'invitation' => [
                'id' => $this->invitationId,
                'status' => 'declined',
                'cookbook' => ['id' => $this->cookbookId, 'name' => $this->cookbookName],
                'declined_by' => $this->declinedBy,
            ],
        ];
    }
}
