<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ActivityLog extends Model
{
    public $timestamps = true;

    protected $fillable = [
        'description',
        'subject_type',
        'subject_id',
        'causer_type',
        'causer_id',
        'tenant_id',
        'properties',
    ];

    protected $casts = [
        'properties' => 'array',
        'subject_id' => 'integer',
        'causer_id' => 'integer',
        'tenant_id' => 'integer',
    ];

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function causer(): MorphTo
    {
        return $this->morphTo();
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
