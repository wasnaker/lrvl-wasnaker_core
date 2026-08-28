<?php

declare(strict_types=1);

namespace App\Services;

use App\Mail\GenericMail;
use App\Models\User;
use App\Notifications\GenericMailNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;

/**
 * MailService — wrapper untuk Laravel Mail + Notification.
 *
 * Diadopsi dari `App_mailer.php` / `App_Email.php` PerfexCRM.
 * Menyediakan API untuk:
 * - Kirim email via Mailable (sync atau queue)
 * - Kirim notifikasi via Notification channel
 * - Antrean email + retry + cleanup (App_Email)
 *
 * REF: docs/analisis-library-perfex.md (mails/ — ✅ SELESAI; App_Email — ❌ BELUM)
 */
class MailService
{
    /**
     * Kirim email menggunakan Mailable.
     *
     * @param  array{to: string, subject: string, view: string, data?: array<string, mixed>, queue?: bool, queue_name?: string|null}  $payload
     */
    public function send(array $payload): bool
    {
        $to = $payload['to'] ?? null;
        $subject = $payload['subject'] ?? '(no subject)';
        $view = $payload['view'] ?? null;
        $data = $payload['data'] ?? [];
        $queue = (bool) ($payload['queue'] ?? false);

        if (! $to || ! $view) {
            return false;
        }

        $mailable = (new GenericMail($subject, $view, $data))->to($to);

        if ($queue) {
            $queueName = $payload['queue_name'] ?? null;
            $mailable->onQueue($queueName);
            Mail::to($to)->queue($mailable);

            return true;
        }

        Mail::to($to)->send($mailable);

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

        if (! $userId) {
            return false;
        }

        $user = User::find($userId);
        if (! $user) {
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

        $users = User::whereIn('id', $userIds)->get();

        if ($users->isEmpty()) {
            return 0;
        }

        Notification::send($users, new GenericMailNotification($subject, $body, $actionUrl));

        return $users->count();
    }

    /**
     * Retry semua failed mail job (App_Email::retry_queue).
     *
     * @return int jumlah job yang di-retry
     */
    public function retryQueue(?string $queueName = null): int
    {
        $ids = $this->failedJobIds($queueName);

        if (empty($ids)) {
            return 0;
        }

        foreach ($ids as $id) {
            Queue::retry((int) $id);
        }

        return count($ids);
    }

    /**
     * Bersihkan failed mail job lama (App_Email::clean_up_old_queue).
     *
     * @return int jumlah job yang dihapus
     */
    public function cleanUpOldQueue(?string $queueName = null, int $maxAgeDays = 7): int
    {
        $table = $this->failedTable();
        $cutoff = now()->subDays($maxAgeDays);

        $failed = DB::table($table)
            ->when($queueName, fn ($q) => $q->where('queue', $queueName))
            ->where('failed_at', '<', $cutoff->toDateTimeString())
            ->pluck('id');

        $count = $failed->count();

        foreach ($failed as $id) {
            Queue::forget((int) $id);
        }

        return $count;
    }

    /**
     * Cek jumlah antrean email yang menunggu.
     */
    public function pendingCount(?string $queueName = null): int
    {
        $table = $this->queueTable();

        return (int) DB::table($table)
            ->when($queueName, fn ($q) => $q->where('queue', $queueName))
            ->count();
    }

    /**
     * @return list<int>
     */
    private function failedJobIds(?string $queueName): array
    {
        $table = $this->failedTable();

        if (! Schema::hasTable($table)) {
            return [];
        }

        return DB::table($table)
            ->when($queueName, fn ($q) => $q->where('queue', $queueName))
            ->pluck('id')
            ->map(fn ($v) => (int) $v)
            ->values()
            ->all();
    }

    private function queueTable(): string
    {
        $connector = config('queue.default');

        return (string) (config("queue.connections.{$connector}.table") ?? 'jobs');
    }

    private function failedTable(): string
    {
        $connector = config('queue.default');

        return (string) (config('queue.failed.table') ?? "{$connector}_failed_jobs");
    }
}
