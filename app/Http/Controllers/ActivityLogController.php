<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Services\ActivityLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API log aktivitas (dari database_helper.php PerfexCRM: log_activity).
 *
 * Ini adalah resource REST (setiap log punya ID auto-increment),
 * berbeda dengan Settings yang key-value. Mendukung scope multi-tenant.
 *
 * @group Activity Logs
 */
class ActivityLogController extends Controller
{
    public function __construct(
        private readonly ActivityLogService $activity
    ) {}

    /**
     * List log aktivitas.
     *
     * @authenticated
     *
     * @queryParam tenant_id integer optional Filter scope tenant. Example: 1
     * @queryParam causer_id integer optional Filter berdasar user id (causer). Example: 1
     * @queryParam subject_type string optional Filter tipe subjek. Example: App\Models\Invoice
     * @queryParam subject_id integer optional Filter id subjek. Example: 5
     * @queryParam per_page integer optional Jumlah per halaman. Example: 15
     *
     * @response scenario=success {
     *   "data": [
     *     {
     *       "id": 1,
     *       "description": "Invoice dibuat",
     *       "subject_type": "App\\Models\\Invoice",
     *       "subject_id": 5,
     *       "causer_type": "App\\Models\\User",
     *       "causer_id": 1,
     *       "tenant_id": 1,
     *       "properties": {"ip": "127.0.0.1"},
     *       "created_at": "2026-08-28T00:00:00+00:00"
     *     }
     *   ],
     *   "meta": {"current_page": 1, "per_page": 15, "total": 1}
     * }
     */
    public function index(Request $request): JsonResponse
    {
        $query = $this->activity->query(
            $request->query('tenant_id') !== null ? (int) $request->query('tenant_id') : null
        );

        if ($request->query('causer_id') !== null) {
            $query->where('causer_id', (int) $request->query('causer_id'));
        }
        if ($request->filled('subject_type')) {
            $query->where('subject_type', $request->query('subject_type'));
        }
        if ($request->query('subject_id') !== null) {
            $query->where('subject_id', (int) $request->query('subject_id'));
        }

        $perPage = min((int) $request->query('per_page', 15), 100);
        $logs = $query->orderByDesc('created_at')->paginate($perPage);

        return response()->json([
            'data' => $logs->items(),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
            ],
        ]);
    }

    /**
     * Ambil satu log aktivitas.
     *
     * @authenticated
     *
     * @urlParam id integer required ID log. Example: 1
     *
     * @response scenario=success {
     *   "id": 1,
     *   "description": "Invoice dibuat",
     *   "subject_type": "App\\Models\\Invoice",
     *   "subject_id": 5,
     *   "causer_type": "App\\Models\\User",
     *   "causer_id": 1,
     *   "tenant_id": 1,
     *   "properties": {"ip": "127.0.0.1"},
     *   "created_at": "2026-08-28T00:00:00+00:00"
     * }
     * @response status=404 scenario=tidak ditemukan {
     *   "message": "Activity log not found"
     * }
     */
    public function show(int $id): JsonResponse
    {
        $log = ActivityLog::find($id);

        if (!$log) {
            return response()->json(['message' => 'Activity log not found'], 404);
        }

        return response()->json($log);
    }

    /**
     * Catat log aktivitas baru.
     *
     * @authenticated
     *
     * @bodyParam description string required Deskripsi aktivitas. Example: Invoice dibuat
     * @bodyParam subject_type string optional Tipe subjek (FQCN). Example: App\Models\Invoice
     * @bodyParam subject_id integer optional ID subjek. Example: 5
     * @bodyParam causer_id integer optional ID user penyebab. Example: 1
     * @bodyParam tenant_id integer optional Scope tenant. Example: 1
     * @bodyParam properties object optional Data tambahan. Example: {"ip": "127.0.0.1"}
     *
     * @response scenario=success {
     *   "id": 1,
     *   "description": "Invoice dibuat",
     *   "subject_type": "App\\Models\\Invoice",
     *   "subject_id": 5,
     *   "causer_type": "App\\Models\\User",
     *   "causer_id": 1,
     *   "tenant_id": 1,
     *   "properties": {"ip": "127.0.0.1"},
     *   "created_at": "2026-08-28T00:00:00+00:00"
     * }
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'description' => ['required', 'string'],
            'subject_type' => ['nullable', 'string'],
            'subject_id' => ['nullable', 'integer'],
            'causer_id' => ['nullable', 'integer'],
            'tenant_id' => ['nullable', 'integer'],
            'properties' => ['nullable', 'array'],
        ]);

        $log = $this->activity->log(
            $validated['description'],
            isset($validated['subject_id']) ? (int) $validated['subject_id'] : null,
            isset($validated['causer_id']) ? (int) $validated['causer_id'] : null,
            $validated['properties'] ?? [],
            $validated['tenant_id'] ?? null
        );

        return response()->json($log, 201);
    }

    /**
     * Hapus log aktivitas.
     *
     * @authenticated
     *
     * @urlParam id integer required ID log. Example: 1
     *
     * @response scenario=success {
     *   "message": "Activity log deleted"
     * }
     * @response status=404 scenario=tidak ditemukan {
     *   "message": "Activity log not found"
     * }
     */
    public function destroy(int $id): JsonResponse
    {
        $log = ActivityLog::find($id);

        if (!$log) {
            return response()->json(['message' => 'Activity log not found'], 404);
        }

        $log->delete();

        return response()->json(['message' => 'Activity log deleted']);
    }
}
