<?php

declare(strict_types=1);

namespace App\Services;

/**
 * FileService dari files_helper.php PerfexCRM — PART 1.
 *
 * Fungsi yang diadopsi:
 *   - bytesToSize → bytes_to_size
 *   - file_upload_max_size
 *   - parse_size
 *   - is_image
 *   - get_file_extension
 *   - sanitize_file_name
 *   - unique_filename
 *
 * REF: docs/porting-helper-implementasi.md, docs/analisis-helper-perfex.md
 */
class FileService
{
    /**
     * Format bytes ke string human-readable (mis. "1.5 MB").
     *
     * @param int $bytes
     * @param int $precision
     * @return string
     */
    public function bytes_to_size(int $bytes, int $precision = 2): string
    {
        if ($bytes < 0) {
            $bytes = 0;
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));

        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    /**
     * Dapatkan max upload size dari PHP config (dalam bytes).
     *
     * @return int
     */
    public function file_upload_max_size(): int
    {
        $max = ini_get('upload_max_filesize');
        $max = $this->parse_size($max);

        $post = ini_get('post_max_size');
        $post = $this->parse_size($post);

        return min($max, $post);
    }

    /**
     * Parse ukuran string (mis. "2M", "512K") ke bytes.
     *
     * @param string $size
     * @return int
     */
    public function parse_size(string $size): int
    {
        $size = trim($size);

        if (ctype_digit($size)) {
            return (int) $size;
        }

        $unit = strtoupper(substr($size, -1));
        $value = (float) substr($size, 0, -1);

        return match ($unit) {
            'P' => (int) ($value * 1024 * 1024 * 1024 * 1024),
            'T' => (int) ($value * 1024 * 1024 * 1024 * 1024 * 1024),
            'G' => (int) ($value * 1024 * 1024 * 1024),
            'M' => (int) ($value * 1024 * 1024),
            'K' => (int) ($value * 1024),
            default => (int) $value,
        };
    }

    /**
     * Apakah file adalah gambar (berdasarkan ekstensi).
     *
     * @param string $filename atau path
     * @return bool
     */
    public function is_image(string $filename): bool
    {
        $ext = strtolower($this->get_file_extension($filename));
        return in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'svg', 'ico', 'tiff', 'tif'], true);
    }

    /**
     * Dapatkan ekstensi file dari nama/file path.
     *
     * @param string $filename
     * @return string lowercase tanpa titik
     */
    public function get_file_extension(string $filename): string
    {
        $pathinfo = pathinfo($filename);
        return strtolower($pathinfo['extension'] ?? '');
    }

    /**
     * Sanitize nama file: hilangkan karakter berbahaya, spasi → underscore.
     *
     * @param string $filename
     * @return string
     */
    public function sanitize_file_name(string $filename): string
    {
        $filename = basename($filename);
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
        $filename = preg_replace('/_{2,}/', '_', $filename);
        $filename = trim($filename, '_ .');
        return $filename;
    }

    /**
     * Generate nama file unik, menggunakan timestamp + random string.
     *
     * @param string $originalName atau ekstensi
     * @param string $prefix opsional
     * @return string
     */
    public function unique_filename(string $originalName = '', string $prefix = ''): string
    {
        $ext = $this->get_file_extension($originalName);

        $base = $prefix;
        if ($base !== '') {
            $base .= '_' . strtolower(
                preg_replace('/[^a-zA-Z0-9]+/', '-', pathinfo($originalName, PATHINFO_FILENAME) ?: 'file')
            );
        }

        $unique = bin2hex(random_bytes(4)) . '_' . time();

        if ($ext !== '') {
            return ($base !== '' ? $base . '_' : '') . $unique . '.' . $ext;
        }

        return ($base !== '' ? $base . '_' : '') . $unique;
    }

    /**
     * Simpan uploaded file ke disk (Laravel Storage), path per-tenant.
     *
     * Pola Laravel standar + struktur mirip Perfex (uploads/{rel_type}/{rel_id}/).
     * File fisik di storage, metadata dicatat oleh caller (Attachment model).
     *
     * @param \Illuminate\Http\UploadedFile $file
     * @param string $relType  mis. 'invoice'
     * @param int    $relId
     * @param int|null $tenantId
     * @param string $disk     'local' (private) | 'public'
     * @return string path relatif hasil store()
     */
    public function storeUpload(
        \Illuminate\Http\UploadedFile $file,
        string $relType,
        int $relId,
        ?int $tenantId = null,
        string $disk = 'local'
    ): string {
        $dir = 'tenants/' . ($tenantId ?? 'global') . '/' . $relType . '/' . $relId;
        $name = $this->unique_filename($file->getClientOriginalName());

        return $file->storeAs($dir, $name, $disk);
    }

    /**
     * Buat response download/inline untuk attachment.
     *
     * @param \App\Models\Attachment $attachment
     * @param bool $inline true=preview (image), false=force download
     */
    public function downloadResponse(\App\Models\Attachment $attachment, bool $inline = false): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $storage = \Illuminate\Support\Facades\Storage::disk($attachment->disk);
        $fullPath = $storage->path($attachment->path);

        $disposition = $inline ? 'inline' : 'attachment';
        $filename = $attachment->original_name;

        return response()->file($fullPath, [
            'Content-Type' => $attachment->mime_type ?? 'application/octet-stream',
            'Content-Disposition' => $disposition . '; filename="' . $filename . '"',
        ]);
    }
}
