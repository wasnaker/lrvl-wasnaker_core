<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\SettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API key-value settings (dari settings_helper.php PerfexCRM).
 *
 * BUKAN CRUD klasik: key adalah identifier, bukan ID auto-increment.
 * Mendukung scope multi-tenant (tenant_id NULL = global).
 *
 * @group api/v1
     * @subgroup Settings
 */
class SettingController extends Controller
{
    public function __construct(
        private readonly SettingService $settings
    ) {}

    /**
     * Ambil satu setting berdasarkan key.
     *
     * @authenticated
     *
     * @urlParam key string required Kunci setting. Example: invoice_prefix
     * @queryParam tenant_id integer optional Scope tenant. Null = global. Example: 1
     *
     * @response scenario=success {
     *   "key": "invoice_prefix",
     *   "value": "INV-",
     *   "tenant_id": null
     * }
     * @response status=404 scenario="tidak ditemukan" {
     *   "message": "Setting not found"
     * }
     */
    public function show(Request $request, string $key): JsonResponse
    {
        $tenantId = $request->query('tenant_id') !== null
            ? (int) $request->query('tenant_id') : null;

        if (!$this->settings->has($key, $tenantId)) {
            return response()->json(['message' => 'Setting not found'], 404);
        }

        $value = $this->settings->get($key, null, $tenantId);

        return response()->json([
            'key' => $key,
            'value' => $value,
            'tenant_id' => $tenantId,
        ]);
    }

    /**
     * Simpan / perbarui setting (upsert by key).
     *
     * @authenticated
     *
     * @urlParam key string required Kunci setting. Example: invoice_prefix
     * @bodyParam value string required Nilai setting. Example: INV-
     * @bodyParam tenant_id integer optional Scope tenant. Null = global. Example: 1
     *
     * @response scenario=success {
     *   "key": "invoice_prefix",
     *   "value": "INV-",
     *   "tenant_id": null
     * }
     */
    public function upsert(Request $request, string $key): JsonResponse
    {
        $value = $request->input('value');
        $tenantId = $request->input('tenant_id') !== null
            ? (int) $request->input('tenant_id') : null;

        $record = $this->settings->set($key, $value, $tenantId);

        return response()->json([
            'key' => $record->key,
            'value' => $record->value,
            'tenant_id' => $record->tenant_id,
        ]);
    }

    /**
     * Hapus setting berdasarkan key.
     *
     * @authenticated
     *
     * @urlParam key string required Kunci setting. Example: invoice_prefix
     * @queryParam tenant_id integer optional Scope tenant. Null = global. Example: 1
     *
     * @response scenario=success {
     *   "message": "Setting deleted"
     * }
     * @response status=404 scenario="tidak ditemukan" {
     *   "message": "Setting not found"
     * }
     */
    public function destroy(Request $request, string $key): JsonResponse
    {
        $tenantId = $request->query('tenant_id') !== null
            ? (int) $request->query('tenant_id') : null;

        if (!$this->settings->has($key, $tenantId)) {
            return response()->json(['message' => 'Setting not found'], 404);
        }

        $this->settings->delete($key, $tenantId);

        return response()->json(['message' => 'Setting deleted']);
    }

    /**
     * Ambil banyak setting sekaligus.
     *
     * @authenticated
     *
     * @bodyParam keys array required Daftar key. Example: ["invoice_prefix","tax_rate"]
     * @bodyParam tenant_id integer optional Scope tenant. Null = global. Example: 1
     *
     * @response scenario=success {
     *   "data": {
     *     "invoice_prefix": "INV-",
     *     "tax_rate": "11"
     *   }
     * }
     */
    public function bulk(Request $request): JsonResponse
    {
        $keys = (array) $request->input('keys', []);
        $tenantId = $request->input('tenant_id') !== null
            ? (int) $request->input('tenant_id') : null;

        $out = [];
        foreach ($keys as $k) {
            $out[$k] = $this->settings->get($k, null, $tenantId);
        }

        return response()->json(['data' => $out]);
    }
}
