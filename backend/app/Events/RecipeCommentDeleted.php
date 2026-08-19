<?php

namespace App\Events;

use App\Models\Cookbook;
use App\Models\Recipe;
use App\Models\RecipeComment;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class RecipeCommentDeleted implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly RecipeComment $comment)
    {
        $comment->loadMissing('recipe.cookbook');
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
        return 'recipe.comment.deleted';
    }

    /** @return array{recipe: array{id: string}, comment: array{id: string}} */
    public function broadcastWith(): array
    {
        /** @var Recipe $recipe */
        $recipe = $this->comment->recipe;

        return [
            'recipe' => ['id' => $recipe->public_id],
            'comment' => ['id' => $this->comment->public_id],
        ];
    }
}
