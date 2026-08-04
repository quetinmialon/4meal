<?php

namespace App\Notifications;

use App\Enums\NotificationChannel;
use App\Models\Cookbook;
use App\Models\CookbookMessage;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class NewCookbookMessageNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly CookbookMessage $message) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return $this->channelFor($notifiable)->laravelChannels();
    }

    public function toMail(object $notifiable): MailMessage
    {
        /** @var User $user */
        $user = $notifiable;
        $this->message->loadMissing('cookbook', 'user');
        /** @var Cookbook $cookbook */
        $cookbook = $this->message->cookbook;
        /** @var User $sender */
        $sender = $this->message->user;

        return (new MailMessage)
            ->subject('Nouveau message dans '.$cookbook->name)
            ->greeting('Bonjour '.$user->name.',')
            ->line($sender->name.' a envoyé un message dans « '.$cookbook->name.' ».')
            ->line($this->message->content);
    }

    private function channelFor(object $notifiable): NotificationChannel
    {
        $channel = $notifiable->notificationPreferences()->where('type', 'cookbook_message')->value('channel');

        return $channel instanceof NotificationChannel
            ? $channel
            : ($channel === null ? NotificationChannel::Web : NotificationChannel::from($channel));
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        $this->message->loadMissing('cookbook', 'user');
        /** @var Cookbook $cookbook */
        $cookbook = $this->message->cookbook;
        /** @var User $sender */
        $sender = $this->message->user;

        return [
            'type' => 'cookbook_message',
            'cookbook' => [
                'id' => $cookbook->public_id,
                'name' => $cookbook->name,
            ],
            'message' => [
                'id' => $this->message->public_id,
                'preview' => $this->message->content,
            ],
            'sender' => [
                'id' => $sender->id,
                'name' => $sender->name,
            ],
        ];
    }
}
