<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\CustomMeta;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Model;

/**
 * API custom meta (polymorphic) — dari user_meta_helper.php PerfexCRM.
 *
 * Meta adalah key-value yang menempel ke entity mana pun (User, Invoice, ...)
 * via trait HasMetaData (morphMany ke CustomMeta).
 *
 * Karena polymorphic, endpoint dibentuk generik:
 *   GET    /api/meta/{type}/{id}          -> semua meta entity
 *   GET    /api/meta/{type}/{id}/{key}    -> satu meta
 *   PUT    /api/meta/{type}/{id}/{key}    -> set/update satu meta
 *   POST   /api/meta/{type}/{id}          -> bulk set (replace)
 *   DELETE /api/meta/{type}/{id}/{key}    -> hapus satu meta
 *
 * {type} adalah short name class yang di-allowlist (aman, bukan arbitrary FQCN).
 *
 * @group api/v1
     * @subgroup Custom Meta
 */
class MetaController extends Controller
{
    /**
     * Map short type -> FQCN yang diizinkan (cegah arbitrary class).
     *
     * @var array<string,string>
     */
    private const ALLOWED = [
        'user' => \App\Models\User::class,
    ];

    private function resolveEntity(string $type, int $id): ?Model
    {
        $fqcn = self::ALLOWED[strtolower($type)] ?? null;

        if (!$fqcn) {
            return null;
        }

        // Pastikan entity pakai trait HasMetaData
        if (!method_exists($fqcn, 'meta')) {
            return null;
        }

        return $fqcn::find($id);
    }

    /**
     * Ambil semua meta entity.
     *
     * @authenticated
     *
     * @urlParam type string required Short entity type (allowlist). Example: user
     * @urlParam id integer required Entity id. Example: 1
     *
     * @response scenario=success {
     *   "data": {"theme": "dark", "language": "id"}
     * }
     */
    public function index(string $type, int $id): JsonResponse
    {
        $entity = $this->resolveEntity($type, $id);

        if (!$entity) {
            return response()->json(['message' => 'Entity not found or type not allowed'], 404);
        }

        return response()->json(['data' => $entity->getMetaArray()]);
    }

    /**
     * Ambil satu meta by key.
     *
     * @authenticated
     *
     * @urlParam type string required Short entity type. Example: user
     * @urlParam id integer required Entity id. Example: 1
     * @urlParam key string required Meta key. Example: theme
     *
     * @response scenario=success {
     *   "key": "theme", "value": "dark"
     * }
     * @response status=404 scenario="tidak ditemukan" {
     *   "message": "Meta not found"
     * }
     */
    public function show(string $type, int $id, string $key): JsonResponse
    {
        $entity = $this->resolveEntity($type, $id);

        if (!$entity) {
            return response()->json(['message' => 'Entity not found or type not allowed'], 404);
        }

        if (!$entity->meta()->where('meta_key', $key)->exists()) {
            return response()->json(['message' => 'Meta not found'], 404);
        }

        return response()->json([
            'key' => $key,
            'value' => $entity->getMeta($key),
        ]);
    }

    /**
     * Set/update satu meta by key.
     *
     * @authenticated
     *
     * @urlParam type string required Short entity type. Example: user
     * @urlParam id integer required Entity id. Example: 1
     * @urlParam key string required Meta key. Example: theme
     * @bodyParam value mixed required Nilai meta. Example: dark
     *
     * @response scenario=success {
     *   "key": "theme", "value": "dark"
     * }
     */
    public function update(Request $request, string $type, int $id, string $key): JsonResponse
    {
        $entity = $this->resolveEntity($type, $id);

        if (!$entity) {
            return response()->json(['message' => 'Entity not found or type not allowed'], 404);
        }

        $value = $request->input('value');
        $entity->setMeta($key, $value);

        return response()->json([
            'key' => $key,
            'value' => $entity->getMeta($key),
        ]);
    }

    /**
     * Bulk set meta (replace) untuk entity.
     *
     * @authenticated
     *
     * @urlParam type string required Short entity type. Example: user
     * @urlParam id integer required Entity id. Example: 1
     * @bodyParam meta object required Map key=>value. Example: {"theme":"dark","language":"id"}
     *
     * @response scenario=success {
     *   "data": {"theme": "dark", "language": "id"}
     * }
     */
    public function store(Request $request, string $type, int $id): JsonResponse
    {
        $entity = $this->resolveEntity($type, $id);

        if (!$entity) {
            return response()->json(['message' => 'Entity not found or type not allowed'], 404);
        }

        $data = (array) $request->input('meta', []);
        $entity->setMetaArray($data);

        return response()->json(['data' => $entity->getMetaArray()]);
    }

    /**
     * Hapus satu meta by key.
     *
     * @authenticated
     *
     * @urlParam type string required Short entity type. Example: user
     * @urlParam id integer required Entity id. Example: 1
     * @urlParam key string required Meta key. Example: theme
     *
     * @response scenario=success {
     *   "message": "Meta deleted"
     * }
     * @response status=404 scenario="tidak ditemukan" {
     *   "message": "Meta not found"
     * }
     */
    public function destroy(string $type, int $id, string $key): JsonResponse
    {
        $entity = $this->resolveEntity($type, $id);

        if (!$entity) {
            return response()->json(['message' => 'Entity not found or type not allowed'], 404);
        }

        if (!$entity->meta()->where('meta_key', $key)->exists()) {
            return response()->json(['message' => 'Meta not found'], 404);
        }

        $entity->deleteMeta($key);

        return response()->json(['message' => 'Meta deleted']);
    }
}
