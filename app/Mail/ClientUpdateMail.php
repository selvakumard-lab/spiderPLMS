<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * A reviewed update or reminder from a workspace to its client.
 */
class ClientUpdateMail extends Mailable
{
    public function __construct(
        public string $subjectLine,
        public string $body,
        public string $senderName,
        public string $signature,
        public ?string $projectName,
        public ?string $replyToAddress = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subjectLine,
            replyTo: $this->replyToAddress ? [$this->replyToAddress] : [],
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.client-update', text: 'emails.client-update-text');
    }
}
