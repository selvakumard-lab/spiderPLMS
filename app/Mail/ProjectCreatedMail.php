<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Sent to the client when a project is created for them. */
class ProjectCreatedMail extends Mailable
{
    /**
     * @param  list<array{title: string, due: ?string}>  $steps  The first steps of the plan, with their deadlines.
     */
    public function __construct(
        public string $clientName,
        public string $workspaceName,
        public string $projectName,
        public string $projectCode,
        public string $startDate,
        public string $dueDate,
        public ?int $workingDays,
        public array $steps,
        public int $stepCount,
        public ?string $portalUrl,
        public string $senderName,
        public string $appName,
        public string $supportEmail,
        public ?string $replyToAddress = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "New project started: {$this->projectName}",
            replyTo: $this->replyToAddress ? [$this->replyToAddress] : [],
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.project-created', text: 'emails.project-created-text');
    }
}
