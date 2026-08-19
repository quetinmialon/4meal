<?php

namespace App\Notifications;

use App\Enums\NotificationChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class CookbookInvitationNotification extends Notification
{
    use Queueable;

    /** @param array<string, mixed> $data */
    public function __construct(private readonly array $data) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        $channel = $notifiable->notificationPreferences()
            ->where('type', 'cookbook_invitation')
            ->value('channel');

        $channel = $channel instanceof NotificationChannel
            ? $channel
            : ($channel === null ? NotificationChannel::Both : NotificationChannel::from($channel));

        $channels = $channel->laravelChannels();

        // The invitation workflow already sends its dedicated invitation email.
        // Keep the persisted/broadcast notification, but do not send that email twice.
        if (($this->data['status'] ?? 'pending') === 'pending') {
            $channels = array_values(array_filter(
                $channels,
                static fn (string $notificationChannel): bool => $notificationChannel !== 'mail',
            ));
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $cookbook = (array) ($this->data['cookbook'] ?? []);
        $status = (string) ($this->data['status'] ?? 'pending');
        $subject = match ($status) {
            'accepted' => 'Invitation cookbook acceptée',
            'declined' => 'Invitation cookbook refusée',
            default => 'Nouvelle invitation cookbook',
        };

        return (new MailMessage)
            ->subject($subject)
            ->line((string) ($cookbook['name'] ?? 'Un cookbook'))
            ->line('Consultez votre espace SUPMEAL pour voir les détails.');
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        return $this->data;
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->data);
    }
}
