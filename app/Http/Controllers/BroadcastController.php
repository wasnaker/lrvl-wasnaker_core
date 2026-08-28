<?php

namespace App\Http\Controllers;

use App\Events\NotificationSent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Broadcasting
 *
 * Realtime (pengganti App_pusher Perfex) — Laravel Broadcasting + Reverb.
 * Klien: laravel-echo + pusher-js diarahkan ke server Reverb; auth channel
 * via `POST /api/broadcasting/auth` (token Sanctum).
 */
class BroadcastController extends Controller
{
    /**
     * Kirim notifikasi realtime ke user yang sedang login.
     *
     * Memicu event `notification.sent` di private channel `user.{id}`.
     * Frontend (Next.js) mendengarkan channel tersebut lalu menampilkan
     * desktop notification via browser Notification API.
     *
     * @authenticated
     *
     * @bodyParam title string optional Judul notifikasi. Example: Pesan baru
     * @bodyParam message string optional Isi notifikasi. Example: Anda menerima pesan baru
     * @bodyParam type string optional Tipe notifikasi: info|success|warning|error. Example: success
     * @bodyParam data array optional Data tambahan bebas (JSON object).
     *
     * @response 200 scenario="terkirim" {"success": true, "event": "notification.sent", "channel": "user.1", "payload": {"title": "Pesan baru", "message": "Anda menerima pesan baru", "type": "success", "data": [], "sent_at": "2026-08-29T00:00:00+07:00"}}
     * @response 422 {"message": "The given data was invalid.", "errors": {"type": ["The selected type is invalid."]}}
     */
    public function sendTest(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:190'],
            'message' => ['sometimes', 'string', 'max:1000'],
            'type' => ['sometimes', 'in:info,success,warning,error'],
            'data' => ['sometimes', 'array'],
        ]);

        $user = $request->user();
        $event = new NotificationSent(
            userId: (int) $user->id,
            title: $validated['title'] ?? 'Notifikasi baru',
            message: $validated['message'] ?? 'Ini adalah notifikasi uji realtime.',
            type: $validated['type'] ?? 'info',
            data: $validated['data'] ?? [],
        );

        broadcast($event);

        return response()->json([
            'success' => true,
            'event' => $event->broadcastAs(),
            'channel' => 'user.'.$user->id,
            'payload' => $event->broadcastWith(),
        ]);
    }

    /**
     * Konfigurasi koneksi realtime untuk frontend.
     *
     * Mengembalikan parameter publik (tanpa secret) agar klien bisa
     * menghubungkan laravel-echo ke server Reverb.
     *
     * @authenticated
     *
     * @response 200 {"driver": "reverb", "key": "a0adc25fe8777223fb4a", "scheme": "http", "host": "wasnaker.lan", "port": 8080}
     */
    public function config(): JsonResponse
    {
        return response()->json([
            'driver' => 'reverb',
            'key' => config('reverb.apps.apps.0.key'),
            'scheme' => config('reverb.apps.apps.0.options.scheme', 'http'),
            'host' => config('reverb.apps.apps.0.options.host', '127.0.0.1'),
            'port' => (int) config('reverb.apps.apps.0.options.port', 8080),
        ]);
    }
}
