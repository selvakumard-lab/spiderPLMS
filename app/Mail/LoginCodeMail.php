<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * The one-time sign-in code email (HTML with a plain-text alternative). One design for everyone who signs in:
 * the platform Super Admin, workspace team members and clients. Only the wording around the code changes.
 */
class LoginCodeMail extends Mailable
{
    public const AUDIENCES = ['platform', 'team', 'client'];

    /**
     * @param  string  $audience  platform (Super Admin), team (workspace member) or client (client portal).
     */
    public function __construct(
        public string $code,
        public string $appName,
        public string $where,
        public int $expiresInMinutes,
        public string $supportEmail,
        public string $signInUrl,
        public string $audience = 'team',
        public string $recipientEmail = '',
        public string $requestedAt = '',
        public ?string $ipAddress = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "{$this->code} is your {$this->appName} sign-in code");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.login-code', text: 'emails.login-code-text');
    }
}
