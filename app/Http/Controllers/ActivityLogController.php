<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Services\ActivityLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * API log aktivitas (dari database_helper.php PerfexCRM: log_activity).
 *
 * Ini adalah resource REST (setiap log punya ID auto-increment),
 * berbeda dengan Settings yang key-value. Mendukung scope multi-tenant.
 *
 * @group api/v1
     * @subgroup Activity Logs
 */
class ActivityLogController extends Controller
{
    public function __construct(
        private readonly ActivityLogService $activity
    ) {}

    /**
     * List log aktivitas (server-side list: paging, sort, filter, search, include).
     *
     * Pengganti `data_tables_init()` Perfex — kontrak DataTables diterjemahkan
     * ke query param REST:
     *   ?sort=-created_at,id            (whitelist, prefix - = DESC)
     *   &filter[tenant_id]=1            (exact)
     *   &filter[causer_id]=1            (exact)
     *   &filter[subject_type]=...       (exact)
     *   &filter[subject_id]=5           (exact)
     *   &filter[description]=cari       (partial / LIKE)
     *   &search=...                     (global search: description + subject_type)
     *   &include=causer,tenant          (eager load relasi)
     *   &per_page=25&page=2             (pagination)
     *
     * @authenticated
     *
     * @queryParam sort string optional Kolom urut (whitelist): id, description, subject_type, subject_id, causer_id, tenant_id, created_at; prefix - untuk DESC. Example: -created_at
     * @queryParam filter[tenant_id] integer optional Filter exact tenant. Example: 1
     * @queryParam filter[causer_id] integer optional Filter exact user penyebab. Example: 1
     * @queryParam filter[subject_type] string optional Filter exact tipe subjek. Example: App\Models\Invoice
     * @queryParam filter[subject_id] integer optional Filter exact id subjek. Example: 5
     * @queryParam filter[description] string optional Filter partial (LIKE) deskripsi. Example: invoice
     * @queryParam search string optional Pencarian global (description + subject_type). Example: invoice
     * @queryParam include string optional Relasi yang di-eager-load: causer,subject,tenant (pisahkan koma). Example: causer,tenant
     * @queryParam per_page integer optional Jumlah per halaman (max 100). Example: 25
     * @queryParam page integer optional Halaman. Example: 2
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
     *   "links": {"first": "...?page=1", "last": "...?page=3", "next": "...?page=2", "prev": null},
     *   "meta": {
     *     "current_page": 2, "from": 26, "last_page": 3, "per_page": 25, "to": 50,
     *     "total": 80, "total_filtered": 50
     *   }
     * }
     */
    public function index(Request $request): JsonResponse
    {
        $tenantId = $request->query('tenant_id') !== null ? (int) $request->query('tenant_id') : null;

        // total sebelum filter (padanan iTotalRecords / meta.total)
        $total = $this->activity->query($tenantId)->count();

        $query = QueryBuilder::for($this->activity->query($tenantId))
            ->allowedSorts([
                'id', 'description', 'subject_type', 'subject_id',
                'causer_id', 'tenant_id', 'created_at',
            ])
            ->allowedFilters([
                AllowedFilter::exact('tenant_id'),
                AllowedFilter::exact('causer_id'),
                AllowedFilter::exact('subject_type'),
                AllowedFilter::exact('subject_id'),
                AllowedFilter::partial('description'),
            ])
            ->allowedIncludes(['causer', 'subject', 'tenant'])
            ->defaultSort('-created_at');

        // global search (padanan search[value] data_tables_init)
        if ($request->filled('search')) {
            $term = $request->query('search');
            $query->where(function ($q) use ($term) {
                $q->where('description', 'like', "%{$term}%")
                  ->orWhere('subject_type', 'like', "%{$term}%");
            });
        }

        $perPage = min((int) $request->query('per_page', 15), 100);
        $logs = $query->paginate($perPage, ['*'], 'page', (int) $request->query('page', 1))->withQueryString();

        return response()->json([
            'data' => $logs->items(),
            'links' => [
                'first' => $logs->url(1),
                'last' => $logs->url($logs->lastPage()),
                'next' => $logs->nextPageUrl(),
                'prev' => $logs->previousPageUrl(),
            ],
            'meta' => [
                'current_page' => $logs->currentPage(),
                'from' => $logs->firstItem(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'to' => $logs->lastItem(),
                'total' => $total,
                'total_filtered' => $logs->total(),
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
            $validated['tenant_id'] ?? null,
            $validated['subject_type'] ?? null,
            $validated['causer_type'] ?? null
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
