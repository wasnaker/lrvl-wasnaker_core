<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Spine\Traits\HasMetaData;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, HasMetaData, HasRoles;

    /**
     * Guard konsisten utk relasi role/permission — aplikasi API-only (Sanctum).
     *
     * @var string
     */
    protected $guard_name = 'sanctum';

    /**
     * Sertakan akses (roles + permissions) saat user di-serialize ke JSON —
     * dipakai frontend dari /api/v1/user.
     *
     * @var list<string>
     */
    protected $appends = [];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'is_active',
        'avatar',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Roles & permissions untuk respons API (mis. /api/v1/user).
     *
     * @return array{roles: list<string>, permissions: list<string>}
     */
    public function getAccessAttribute(): array
    {
        return [
            'roles' => $this->getRoleNames()->all(),
            'permissions' => $this->getAllPermissions()->pluck('name')->all(),
        ];
    }

    /**
     * Profil staff (realname/NIP/jabatan) — 1:1. Optionlist pengawas dll
     * diambil dari sini (realname), bukan username/email.
     */
    public function agencyStaff()
    {
        return $this->hasOne(\Modules\Agency\Models\AgencyStaff::class, 'user_id');
    }

    /**
     * Profil staf platform (realname/jabatan/departemen) — 1:1.
     */
    public function platformStaff()
    {
        return $this->hasOne(\Modules\Platform\Models\PlatformStaff::class, 'user_id');
    }
}
