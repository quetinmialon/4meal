<?php

namespace App\Events;

use App\Http\Resources\RecipeCommentResource;
use App\Models\Cookbook;
use App\Models\Recipe;
use App\Models\RecipeComment;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Http\Request;
use Illuminate\Queue\SerializesModels;

final class RecipeCommentUpdated implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    /** @var array<string, mixed>|null */
    private readonly ?array $payload;

    public function __construct(public readonly RecipeComment $comment)
    {
        $comment->loadMissing('recipe.cookbook', 'user', 'parent', 'reactions');
        /** @var Recipe $recipe */
        $recipe = $comment->recipe;
        /** @var Cookbook|null $cookbook */
        $cookbook = $recipe->cookbook;
        $this->payload = $cookbook === null
            ? null
            : RecipeCommentResource::make($comment)->resolve(Request::create('/'));
    }

    /** @return list<PrivateChannel> */
    public function broadcastOn(): array
    {
        /** @var Recipe $recipe */
        $recipe = $this->comment->recipe;
        /** @var Cookbook|null $cookbook */
        $cookbook = $recipe->cookbook;

        return $cookbook === null
            ? []
            : [new PrivateChannel('cookbook.'.$cookbook->public_id)];
    }

    public function broadcastAs(): string
    {
        return 'recipe.comment.updated';
    }

    /** @return array{recipe: array{id: string}, comment: array<string, mixed>} */
    public function broadcastWith(): array
    {
        /** @var Recipe $recipe */
        $recipe = $this->comment->recipe;

        return [
            'recipe' => ['id' => $recipe->public_id],
            'comment' => $this->payload ?? [],
        ];
    }
}
