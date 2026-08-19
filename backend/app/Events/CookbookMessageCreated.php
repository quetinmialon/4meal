<?php

namespace App\Events;

use App\Http\Resources\CookbookMessageResource;
use App\Models\Cookbook;
use App\Models\CookbookMessage;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Http\Request;
use Illuminate\Queue\SerializesModels;

final class CookbookMessageCreated implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    /** @var array<string, mixed> */
    private readonly array $payload;

    public function __construct(public readonly CookbookMessage $message)
    {
        $message->loadMissing('cookbook', 'user', 'deletedBy', 'reactions');
        /** @var Cookbook $cookbook */
        $cookbook = $message->cookbook;
        $this->payload = CookbookMessageResource::make($message)->resolve(Request::create('/'));
    }

    /** @return list<PrivateChannel> */
    public function broadcastOn(): array
    {
        /** @var Cookbook $cookbook */
        $cookbook = $this->message->cookbook;

        return [new PrivateChannel('cookbook.'.$cookbook->public_id)];
    }

    public function broadcastAs(): string
    {
        return 'cookbook.message.created';
    }

    /** @return array{message: array<string, mixed>} */
    public function broadcastWith(): array
    {
        return ['message' => $this->payload];
    }
}
