<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CustomMeta extends Model
{
    public $timestamps = true;

    protected $fillable = [
        'meta_key',
        'meta_value',
        'meta_type',
        'meta_id',
    ];

    protected $casts = [
        'meta_id' => 'integer',
    ];

    public function meta(): MorphTo
    {
        return $this->morphTo();
    }
}
