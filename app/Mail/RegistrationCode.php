<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Email kode verifikasi registrasi (6 digit). Dikirim SEBELUM user dibuat —
 * akun baru lahir setelah kode diverifikasi.
 */
class RegistrationCode extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public string $code,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Kode Verifikasi Registrasi ' . config('app.name'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.registration-code',
        );
    }
}
