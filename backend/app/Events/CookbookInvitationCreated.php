<?php

namespace App\Events;

use App\Models\CookbookInvitation;
use Carbon\CarbonInterface;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

final class CookbookInvitationCreated implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable;

    public readonly int $invitationId;

    public readonly string $cookbookId;

    public readonly string $cookbookName;

    public readonly string $role;

    public readonly string $expiresAt;

    public readonly int $recipientId;

    public readonly int $inviterId;

    public readonly string $inviterName;

    public function __construct(CookbookInvitation $invitation, int $recipientId)
    {
        $invitation->loadMissing('cookbook', 'inviter');
        /** @var CarbonInterface $expiresAt */
        $expiresAt = $invitation->expires_at;

        $this->invitationId = (int) $invitation->getKey();
        $this->cookbookId = (string) $invitation->cookbook->public_id;
        $this->cookbookName = (string) $invitation->cookbook->name;
        $this->role = (string) $invitation->role;
        $this->expiresAt = $expiresAt->toJSON();
        $this->recipientId = $recipientId;
        $this->inviterId = (int) $invitation->inviter->getKey();
        $this->inviterName = (string) $invitation->inviter->name;
    }

    /** @return list<PrivateChannel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('user.'.$this->recipientId)];
    }

    public function broadcastAs(): string
    {
        return 'cookbook.invitation.created';
    }

    /** @return array{invitation: array<string, mixed>} */
    public function broadcastWith(): array
    {
        return [
            'invitation' => [
                'id' => $this->invitationId,
                'role' => $this->role,
                'expires_at' => $this->expiresAt,
                'cookbook' => [
                    'id' => $this->cookbookId,
                    'name' => $this->cookbookName,
                ],
                'inviter' => [
                    'id' => $this->inviterId,
                    'name' => $this->inviterName,
                ],
            ],
        ];
    }
}
