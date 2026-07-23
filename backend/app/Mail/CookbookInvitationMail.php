<?php

namespace App\Mail;

use App\Models\Cookbook;
use App\Models\CookbookInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class CookbookInvitationMail extends Mailable
{
    use Queueable;

    public function __construct(
        public readonly CookbookInvitation $invitation,
        public readonly string $token,
    ) {}

    public function envelope(): Envelope
    {
        /** @var Cookbook $cookbook */
        $cookbook = $this->invitation->cookbook;

        return new Envelope(subject: 'Invitation à rejoindre '.$cookbook->name);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.cookbook-invitation');
    }
}
