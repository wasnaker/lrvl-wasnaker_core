<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ApiResponse;
use App\Services\TagService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API tagging (spatie/laravel-tags).
 *
 * Diadopsi dari `App_tags.php` PerfexCRM.
 * Mendukung pengelolaan tag global + attach/detach ke model ber-tag.
 *
 * Endpoint:
 *   GET  /api/tags              -> daftar semua tag (opsional ?type=)
 *   POST /api/tags              -> buat tag baru
 *   DELETE /api/tags/{id}       -> hapus tag
 *
 * @group api/v1
     * @subgroup Tags
 */
class TagController extends Controller
{
    use ApiResponse;

    public function __construct(
        private TagService $tags
    ) {}

    /**
     * Daftar semua tag.
     *
     * @authenticated
     *
     * @queryParam type string Filter berdasarkan tipe tag. Example: invoice
     *
     * @response {
     *   "data": [ { "id": 1, "name": "penting" } ]
     * }
     */
    public function index(Request $request): JsonResponse
    {
        $type = $request->query('type');

        $tags = $this->tags->all($type ?: null)->map(fn (object $tag) => [
            'id' => $tag->id,
            'name' => $tag->name,
            'slug' => $tag->slug,
            'type' => $tag->type,
        ])->values();

        return $this->list($tags, $tags->count(), $tags->count(), 1);
    }

    /**
     * Buat tag baru.
     *
     * @authenticated
     *
     * @bodyParam name string required Nama tag. Example: prioritas-tinggi
     * @bodyParam type string optional Tipe tag. Example: invoice
     *
     * @response status=201 {
     *   "data": { "id": 1, "name": "prioritas-tinggi" }
     * }
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'type' => ['nullable', 'string', 'max:50'],
        ]);

        $tag = $this->tags->findOrCreate($validated['name'], $validated['type'] ?? null);

        return $this->created([
            'id' => $tag->id,
            'name' => $tag->name,
            'type' => $tag->type,
        ]);
    }

    /**
     * Hapus tag.
     *
     * @authenticated
     *
     * @urlParam id integer required ID tag. Example: 1
     *
     * @response scenario=success {
     *   "message": "Tag deleted"
     * }
     */
    public function destroy(int $id): JsonResponse
    {
        $deleted = $this->tags->delete($id);

        if (! $deleted) {
            return response()->json(['message' => 'Tag not found'], 404);
        }

        return response()->json(['message' => 'Tag deleted']);
    }
}
