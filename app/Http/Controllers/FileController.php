<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Services\FileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

/**
 * API upload file (Laravel Storage + metadata mirip tblfiles PerfexCRM).
 *
 * Upload menyimpan file fisik ke storage (per-tenant) dan mencatat metadata
 * ke tabel `attachments`. Akses file via route download ber-auth.
 *
 * Endpoint:
 *   POST   /api/files                  -> upload (multipart)
 *   GET    /api/files/{id}             -> meta attachment
 *   GET    /api/files/{id}/download    -> stream file (force download)
 *   GET    /api/files/{id}/preview     -> stream inline (image/pdf)
 *   DELETE /api/files/{id}             -> hapus file + meta
 *   GET    /api/files/limits           -> max upload size (utility)
 *
 * @group api/v1
     * @subgroup Files
 */
class FileController extends Controller
{
    public function __construct(
        private FileService $file
    ) {}

    /**
     * Upload file baru.
     *
     * @authenticated
     *
     * @bodyParam file file required File yang diupload.
     * @bodyParam rel_type string required Tipe entity (invoice, client, task). Example: invoice
     * @bodyParam rel_id integer required ID entity. Example: 10
     * @bodyParam tenant_id integer optional ID tenant (multi-tenant). Example: 1
     * @bodyParam disk string optional 'local' (default, private) | 'public'. Example: local
     *
     * @response scenario=success {
     *   "id": 1, "rel_type": "invoice", "rel_id": 10, "original_name": "doc.pdf",
     *   "mime_type": "application/pdf", "size": 12345, "extension": "pdf"
     * }
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'max:' . (int) (ini_get('upload_max_filesize') ?: 2048)],
            'rel_type' => ['required', 'string', 'max:50'],
            'rel_id' => ['required', 'integer', 'min:1'],
            'tenant_id' => ['nullable', 'integer', 'min:1'],
            'disk' => ['nullable', 'in:local,public'],
        ]);

        /** @var UploadedFile $uploaded */
        $uploaded = $validated['file'];
        $disk = $validated['disk'] ?? 'local';
        $tenantId = $validated['tenant_id'] ?? null;

        $path = $this->file->storeUpload(
            $uploaded,
            $validated['rel_type'],
            (int) $validated['rel_id'],
            isset($validated['tenant_id']) ? (int) $validated['tenant_id'] : null,
            $disk
        );

        $attachment = Attachment::create([
            'rel_type' => $validated['rel_type'],
            'rel_id' => (int) $validated['rel_id'],
            'tenant_id' => $tenantId,
            'disk' => $disk,
            'path' => $path,
            'original_name' => $uploaded->getClientOriginalName(),
            'mime_type' => $uploaded->getClientMimeType(),
            'size' => $uploaded->getSize(),
            'extension' => $uploaded->getClientOriginalExtension(),
        ]);

        return response()->json($attachment, 201);
    }

    /**
     * Ambil meta attachment.
     *
     * @authenticated
     *
     * @urlParam id integer required Attachment id. Example: 1
     */
    public function show(int $id): JsonResponse
    {
        $attachment = Attachment::find($id);

        if (!$attachment) {
            return response()->json(['message' => 'Attachment not found'], 404);
        }

        return response()->json($attachment);
    }

    /**
     * Download file (force attachment).
     *
     * @authenticated
     *
     * @urlParam id integer required Attachment id. Example: 1
     */
    public function download(int $id)
    {
        $attachment = Attachment::find($id);

        if (!$attachment) {
            return response()->json(['message' => 'Attachment not found'], 404);
        }

        return $this->file->downloadResponse($attachment, false);
    }

    /**
     * Preview file inline (image/pdf).
     *
     * @authenticated
     *
     * @urlParam id integer required Attachment id. Example: 1
     */
    public function preview(int $id)
    {
        $attachment = Attachment::find($id);

        if (!$attachment) {
            return response()->json(['message' => 'Attachment not found'], 404);
        }

        return $this->file->downloadResponse($attachment, true);
    }

    /**
     * Hapus attachment (file fisik + meta).
     *
     * @authenticated
     *
     * @urlParam id integer required Attachment id. Example: 1
     *
     * @response scenario=success {
     *   "message": "Attachment deleted"
     * }
     */
    public function destroy(int $id): JsonResponse
    {
        $attachment = Attachment::find($id);

        if (!$attachment) {
            return response()->json(['message' => 'Attachment not found'], 404);
        }

        \Illuminate\Support\Facades\Storage::disk($attachment->disk)->delete($attachment->path);
        $attachment->delete();

        return response()->json(['message' => 'Attachment deleted']);
    }

    /**
     * Batas maksimal upload (utility, dari php.ini).
     *
     * @authenticated
     *
     * @response scenario=success {
     *   "max_bytes": 2097152, "max_human": "2 MB", "max_post_bytes": 8388608
     * }
     */
    public function limits(): JsonResponse
    {
        $maxBytes = $this->file->file_upload_max_size();

        return response()->json([
            'max_bytes' => $maxBytes,
            'max_human' => $this->file->bytes_to_size($maxBytes),
            'max_post_bytes' => $this->file->parse_size(ini_get('post_max_size') ?: '0'),
        ]);
    }
}
