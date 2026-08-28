<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * GenericMail — Mailable generik dari array payload.
 *
 * Dipakai untuk kirim email via queue (sesuai App_Email.php PerfexCRM:
 * antrean email + retry). Payload berisi to/subject/view/data.
 */
class GenericMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public string $subject,
        public string $view,
        public array $data = []
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subject);
    }

    public function content(): Content
    {
        return new Content(view: $this->view, with: $this->data);
    }
}
