<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Sent to the client when their project is marked as completed. */
class ProjectCompletedMail extends Mailable
{
    /**
     * @param  string  $verdict  on_time, early or late, comparing the delivery with the planned date.
     */
    public function __construct(
        public string $clientName,
        public string $workspaceName,
        public string $projectName,
        public string $projectCode,
        public string $startedOn,
        public string $deliveredOn,
        public string $plannedOn,
        public int $calendarDays,
        public string $verdict,
        public int $daysDifference,
        public int $stepsDone,
        public int $stepsTotal,
        public ?string $portalUrl,
        public string $senderName,
        public string $appName,
        public string $supportEmail,
        public ?string $replyToAddress = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Project delivered: {$this->projectName}",
            replyTo: $this->replyToAddress ? [$this->replyToAddress] : [],
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.project-completed', text: 'emails.project-completed-text');
    }
}
