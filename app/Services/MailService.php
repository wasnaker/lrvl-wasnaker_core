<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Mail\Mailer;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use App\Notifications\GenericMailNotification;

/**
 * MailService — wrapper untuk Laravel Mail + Notification.
 *
 * Diadopsi dari `App_mailer.php` / `App_Email.php` PerfexCRM.
 * Menyediakan API untuk:
 * - Kirim email via Mailable
 * - Kirim notifikasi via Notification channel
 * - Template registry
 *
 * REF: docs/analisis-library-perfex.md (mails/ — ❌ BELUM)
 */
class MailService
{
    /**
     * Kirim email menggunakan Mailable.
     *
     * @param  array{to: string, subject: string, view: string, data: array<string, mixed>}  $payload
     */
    public function send(array $payload): bool
    {
        $to = $payload['to'] ?? null;
        $subject = $payload['subject'] ?? '(no subject)';
        $view = $payload['view'] ?? null;
        $data = $payload['data'] ?? [];

        if (!$to || !$view) {
            return false;
        }

        Mail::send($view, $data, function (Message $message) use ($to, $subject): void {
            $message->to($to)->subject($subject);
        });

        return true;
    }

    /**
     * Kirim notifikasi ke user tertentu.
     *
     * @param  array{user_id: int, subject: string, body: string, action_url?: string|null}  $payload
     */
    public function notify(array $payload): bool
    {
        $userId = $payload['user_id'] ?? null;
        $subject = $payload['subject'] ?? '(no subject)';
        $body = $payload['body'] ?? '';
        $actionUrl = $payload['action_url'] ?? null;

        if (!$userId) {
            return false;
        }

        $user = \App\Models\User::find($userId);
        if (!$user) {
            return false;
        }

        $user->notify(new GenericMailNotification($subject, $body, $actionUrl));

        return true;
    }

    /**
     * Kirim notifikasi broadcast ke banyak user.
     *
     * @param  array{user_ids: list<int>, subject: string, body: string, action_url?: string|null}  $payload
     */
    public function notifyMany(array $payload): int
    {
        $userIds = $payload['user_ids'] ?? [];
        $subject = $payload['subject'] ?? '(no subject)';
        $body = $payload['body'] ?? '';
        $actionUrl = $payload['action_url'] ?? null;

        if (empty($userIds)) {
            return 0;
        }

        $users = \App\Models\User::whereIn('id', $userIds)->get();

        if ($users->isEmpty()) {
            return 0;
        }

        Notification::send($users, new GenericMailNotification($subject, $body, $actionUrl));

        return $users->count();
    }
}
