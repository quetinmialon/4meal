<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

final class PasswordResetMail extends Mailable
{
    use Queueable;

    public function __construct(
        public readonly User $user,
        public readonly string $token,
        public readonly int $expiresInMinutes,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Réinitialisation de votre mot de passe');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.password-reset');
    }
}
