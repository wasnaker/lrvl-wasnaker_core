<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * GenericMailNotification — notifikasi email sederhana.
 *
 * Digunakan oleh MailService untuk kirim notifikasi email ke user.
 */
class GenericMailNotification extends Notification
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        private string $subject,
        private string $body,
        private ?string $actionUrl = null,
        private array $payload = []
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject($this->subject)
            ->line($this->body);

        if ($this->actionUrl) {
            $message->action('Lihat Detail', $this->actionUrl);
        }

        return $message;
    }
}
