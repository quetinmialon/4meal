<?php

namespace App\Listeners;

use App\Events\RecipeCommentCreated;
use App\Models\Recipe;
use App\Models\RecipeComment;
use App\Models\User;
use App\Notifications\RecipeCommentNotification;
use Illuminate\Database\Eloquent\Collection;

final class NotifyUsersOfRecipeComment
{
    public function handle(RecipeCommentCreated $event): void
    {
        /** @var RecipeComment $comment */
        $comment = $event->comment->loadMissing('recipe.author', 'recipe.user', 'user', 'parent.user');
        /** @var Recipe $recipe */
        $recipe = $comment->recipe;
        /** @var User $sender */
        $sender = $comment->user;

        /** @var Collection<int, User> $recipients */
        $recipients = new Collection;
        $notificationType = $comment->parent_id === null ? 'recipe_comment' : 'recipe_comment_reply';

        if ($comment->parent instanceof RecipeComment && $comment->parent->user instanceof User) {
            $parentAuthor = $comment->parent->user;
            if ($parentAuthor->isNot($sender)) {
                $recipients->push($parentAuthor);
            }
        } else {
            /** @var User|null $recipeOwner */
            $recipeOwner = $recipe->author ?? $recipe->user;
            if ($recipeOwner instanceof User && $recipeOwner->isNot($sender)) {
                $recipients->push($recipeOwner);
            }
        }

        $recipients
            ->unique(fn (User $recipient): int => (int) $recipient->getKey())
            ->each(function (User $recipient) use ($comment, $notificationType): void {
                $recipient->notify(new RecipeCommentNotification($comment, $notificationType));
            });
    }
}
