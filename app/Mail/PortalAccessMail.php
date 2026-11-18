<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Sent to a client the moment they are given access to the client portal. */
class PortalAccessMail extends Mailable
{
    public function __construct(
        public string $clientName,
        public string $workspaceName,
        public string $signInUrl,
        public string $email,
        public string $senderName,
        public string $appName,
        public string $supportEmail,
        public ?string $replyToAddress = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Your {$this->workspaceName} client portal is ready",
            replyTo: $this->replyToAddress ? [$this->replyToAddress] : [],
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.portal-access', text: 'emails.portal-access-text');
    }
}
