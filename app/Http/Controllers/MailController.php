<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\MailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API untuk pengiriman email dan notifikasi.
 *
 * Diadopsi dari `App_mailer.php` / `App_Email.php` PerfexCRM.
 *
 * @group api/v1
     * @subgroup Mail
 */
class MailController extends Controller
{
    public function __construct(
        private MailService $mail
    ) {}

    /**
     * Kirim email menggunakan Mailable.
     *
     * @authenticated
     *
     * @bodyParam to string required Alamat email tujuan. Example: user@example.com
     * @bodyParam subject string required Subjek email. Example: Invoice #123
     * @bodyParam view string required Nama view Blade untuk template email. Example: emails.invoice
     * @bodyParam data array<string, mixed> optional Data untuk template. Example: {"invoice_id": 123}
     *
     * @response scenario=success {"message":"Email sent","success":true}
     */
    public function send(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'to' => 'required|email',
            'subject' => 'required|string|max:255',
            'view' => 'required|string',
            'data' => 'sometimes|array',
            'queue' => 'sometimes|boolean',
            'queue_name' => 'sometimes|nullable|string|max:120',
        ]);

        $success = $this->mail->send([
            'to' => $validated['to'],
            'subject' => $validated['subject'],
            'view' => $validated['view'],
            'data' => $validated['data'] ?? [],
            'queue' => (bool) ($validated['queue'] ?? false),
            'queue_name' => $validated['queue_name'] ?? null,
        ]);

        return response()->json([
            'message' => $success ? 'Email queued/sent' : 'Failed to send email',
            'success' => $success,
        ], $success ? 200 : 500);
    }

    /**
     * Kirim notifikasi email ke user.
     *
     * @authenticated
     *
     * @bodyParam user_id int required ID user penerima. Example: 1
     * @bodyParam subject string required Subjek notifikasi. Example: Task assigned
     * @bodyParam body string required Isi notifikasi. Example: Anda mendapat task baru
     * @bodyParam action_url string opsional URL aksi. Example: https://app.example.com/tasks/1
     *
     * @response scenario=success {"message":"Notification sent","success":true}
     */
    public function notify(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'subject' => 'required|string|max:255',
            'body' => 'required|string',
            'action_url' => 'sometimes|nullable|url',
        ]);

        $success = $this->mail->notify([
            'user_id' => (int) $validated['user_id'],
            'subject' => $validated['subject'],
            'body' => $validated['body'],
            'action_url' => $validated['action_url'] ?? null,
        ]);

        return response()->json([
            'message' => $success ? 'Notification sent' : 'Failed to send notification',
            'success' => $success,
        ], $success ? 200 : 500);
    }

    /**
     * Kirim notifikasi email ke banyak user.
     *
     * @authenticated
     *
     * @bodyParam user_ids array<int> required Daftar ID user penerima. Example: [1,2,3]
     * @bodyParam subject string required Subjek notifikasi. Example: Pengumuman penting
     * @bodyParam body string required Isi notifikasi.
     * @bodyParam action_url string opsional URL aksi.
     *
     * @response scenario=success {"message":"Notification sent","success":true,"recipients":3}
     */
    public function notifyMany(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => 'required|integer|exists:users,id',
            'subject' => 'required|string|max:255',
            'body' => 'required|string',
            'action_url' => 'sometimes|nullable|url',
        ]);

        $count = $this->mail->notifyMany([
            'user_ids' => $validated['user_ids'],
            'subject' => $validated['subject'],
            'body' => $validated['body'],
            'action_url' => $validated['action_url'] ?? null,
        ]);

        return response()->json([
            'message' => 'Notification sent',
            'success' => $count > 0,
            'recipients' => $count,
        ], $count > 0 ? 200 : 500);
    }

    /**
     * Antrean email: retry failed mail job.
     *
     * @authenticated
     *
     * @queryParam queue string Nama queue (opsional). Example: emails
     *
     * @response scenario=success {
     *   "message": "Retried 2 failed job(s)", "success": true, "retried": 2
     * }
     */
    public function retryQueue(Request $request): JsonResponse
    {
        $queue = $request->query('queue') ?: null;

        $count = $this->mail->retryQueue($queue);

        return response()->json([
            'message' => "Retried {$count} failed job(s)",
            'success' => true,
            'retried' => $count,
        ]);
    }

    /**
     * Antrean email: bersihkan failed job lama.
     *
     * @authenticated
     *
     * @queryParam queue string Nama queue (opsional). Example: emails
     * @queryParam days integer Umur maksimal failed job (hari). Example: 7
     *
     * @response scenario=success {
     *   "message": "Cleaned 5 old failed job(s)", "success": true, "cleaned": 5
     * }
     */
    public function cleanUpQueue(Request $request): JsonResponse
    {
        $queue = $request->query('queue') ?: null;
        $days = (int) ($request->query('days', 7));

        $count = $this->mail->cleanUpOldQueue($queue, max(1, $days));

        return response()->json([
            'message' => "Cleaned {$count} old failed job(s)",
            'success' => true,
            'cleaned' => $count,
        ]);
    }

    /**
     * Antrean email: jumlah job menunggu.
     *
     * @authenticated
     *
     * @queryParam queue string Nama queue (opsional). Example: emails
     *
     * @response scenario=success {
     *   "data": { "pending": 3 }
     * }
     */
    public function queueStatus(Request $request): JsonResponse
    {
        $queue = $request->query('queue') ?: null;

        return response()->json([
            'data' => [
                'pending' => $this->mail->pendingCount($queue),
            ],
        ]);
    }
}
