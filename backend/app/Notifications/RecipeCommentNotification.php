<?php

namespace App\Notifications;

use App\Enums\NotificationChannel;
use App\Models\Recipe;
use App\Models\RecipeComment;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
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
        return $this->channelFor($notifiable)->laravelChannels();
    }

    public function toMail(object $notifiable): MailMessage
    {
        /** @var User $user */
        $user = $notifiable;
        $this->comment->loadMissing('recipe', 'user');
        /** @var Recipe $recipe */
        $recipe = $this->comment->recipe;
        /** @var User $sender */
        $sender = $this->comment->user;

        return (new MailMessage)
            ->subject($this->notificationType === 'recipe_comment_reply' ? 'Nouvelle réponse sur votre recette' : 'Nouveau commentaire sur votre recette')
            ->view('emails.notifications.recipe-comment', [
                'user' => $user,
                'sender' => $sender,
                'recipe' => $recipe,
                'comment' => $this->comment,
                'isReply' => $this->notificationType === 'recipe_comment_reply',
            ]);
    }

    private function channelFor(object $notifiable): NotificationChannel
    {
        $channel = $notifiable->notificationPreferences()->where('type', $this->notificationType)->value('channel');

        return $channel instanceof NotificationChannel
            ? $channel
            : ($channel === null ? NotificationChannel::Both : NotificationChannel::from($channel));
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        return $this->notificationData();
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->notificationData());
    }

    /** @return array<string, mixed> */
    private function notificationData(): array
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
