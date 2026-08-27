<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tenant (organisasi / unit dalam hierarki multi-tenant).
 *
 * Stub awal untuk memecahkan dangling reference dari Setting/ActivityLog.
 * Skema lengkap (Platform -> TenantGroup -> Organization -> Unit) mengikuti
 * docs/domain-multitenancy.md dan akan di-expand di migration terpisah.
 */
class Tenant extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'parent_id',
        'type', // platform | tenant_group | organization | unit
    ];

    public function settings(): HasMany
    {
        return $this->hasMany(Setting::class);
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id');
    }
}
