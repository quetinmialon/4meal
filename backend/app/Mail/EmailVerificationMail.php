<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

final class EmailVerificationMail extends Mailable
{
    use Queueable;

    public function __construct(
        public readonly User $user,
        public readonly string $token,
        public readonly int $expiresInMinutes,
    ) {}

    public function verificationUrl(): string
    {
        return rtrim((string) config('auth.email_verification.url'), '/').'/verification-email/'.$this->user->getKey().'/'.$this->token;
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Vérifiez votre adresse email');
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.email-verification',
            with: ['verificationUrl' => $this->verificationUrl()],
        );
    }
}
