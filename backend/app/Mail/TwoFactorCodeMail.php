<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

final class TwoFactorCodeMail extends Mailable
{
    use Queueable;

    public function __construct(public readonly User $user, public readonly string $code, public readonly int $expiresInMinutes) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Votre code de connexion');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.two-factor-code', with: ['code' => $this->code, 'expiresInMinutes' => $this->expiresInMinutes]);
    }
}
