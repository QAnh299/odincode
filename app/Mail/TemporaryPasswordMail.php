<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Email gửi mật khẩu tạm cho chức năng quên mật khẩu.
 */
class TemporaryPasswordMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $fullName,
        public string $username,
        public string $temporaryPassword,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('forgot_password.mail.subject'));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.temporary-password');
    }
}
