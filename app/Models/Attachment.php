<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Attachment — file generik (tabel 2026_08_28_000004_create_attachments_table).
 *
 * Pakai rel_type/rel_id (polimorfik manual), mis. NPWP: rel_type='vat',
 * rel_id = vats.id. 1 entitas bisa punya beberapa file; caller yang
 * menentukan aturan "1 versi" (replace) atau versi.
 */
class Attachment extends Model
{
    protected $table = 'attachments';

    protected $fillable = [
        'rel_type', 'rel_id', 'tenant_id',
        'disk', 'path', 'original_name', 'mime_type', 'size', 'extension',
    ];
}
