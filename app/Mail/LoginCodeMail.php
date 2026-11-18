<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * The one-time sign-in code email (HTML with a plain-text alternative).
 */
class LoginCodeMail extends Mailable
{
    public function __construct(
        public string $code,
        public string $appName,
        public string $where,
        public int $expiresInMinutes,
        public string $supportEmail,
        public string $signInUrl,
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
