<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Metadata file upload (mirip tblfiles PerfexCRM).
 * File fisik disimpan via Laravel Storage; ini hanya metadata + pointer path.
 */
class Attachment extends Model
{
    public $timestamps = true;

    protected $fillable = [
        'rel_type',
        'rel_id',
        'tenant_id',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size',
        'extension',
    ];

    protected $casts = [
        'rel_id' => 'integer',
        'tenant_id' => 'integer',
        'size' => 'integer',
    ];

    /**
     * Relasi polymorphic ke entity pembawa (Invoice, Client, ...).
     * (Opsional; entity belum tentu pakai trait. Biarkan morphTo.)
     */
    public function relatable(): MorphTo
    {
        return $this->morphTo('rel', 'rel_type', 'rel_id');
    }
}
