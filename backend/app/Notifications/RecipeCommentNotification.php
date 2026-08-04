<?php

namespace App\Notifications;

use App\Models\Recipe;
use App\Models\RecipeComment;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

final class RecipeCommentNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly RecipeComment $comment,
        private readonly string $notificationType,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        $this->comment->loadMissing('recipe', 'user');
        /** @var Recipe $recipe */
        $recipe = $this->comment->recipe;
        /** @var User $sender */
        $sender = $this->comment->user;

        return [
            'type' => $this->notificationType,
            'recipe' => [
                'id' => $recipe->public_id,
                'title' => $recipe->title,
            ],
            'comment' => [
                'id' => $this->comment->public_id,
                'preview' => $this->comment->content,
            ],
            'sender' => [
                'id' => $sender->id,
                'name' => $sender->name,
            ],
        ];
    }
}
