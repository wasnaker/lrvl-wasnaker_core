<?php

declare(strict_types=1);

namespace App\Notifications\Channels;

use App\Services\SmsService;
use Illuminate\Notifications\Notification;

/**
 * SmsChannel — channel notifikasi Laravel untuk kirim SMS.
 *
 * Notifiable harus punya method `routeNotificationForSms()` yang mengembalikan
 * nomor telepon (string) atau array `['number' => ..., 'driver' => ...]`.
 *
 * Diadopsi dari `sms/` PerfexCRM.
 */
class SmsChannel
{
    public function __construct(
        private SmsService $sms
    ) {}

    /**
     * @param  object  $notifiable
     */
    public function send($notifiable, Notification $notification): void
    {
        $to = $notifiable->routeNotificationForSms($notification);
        if (empty($to)) {
            return;
        }

        $payload = method_exists($notification, 'toSms')
            ? $notification->toSms($notifiable)
            : null;

        if ($payload === null) {
            return;
        }

        $driver = null;

        if (is_array($to)) {
            $driver = $to['driver'] ?? null;
            $to = $to['number'] ?? null;
        }

        if (! $to) {
            return;
        }

        $this->sms->send([
            'to' => $to,
            'body' => $payload['body'] ?? $payload,
            'driver' => $driver,
        ]);
    }
}
