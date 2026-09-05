<?php

declare(strict_types=1);

namespace Modules\Events\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Spine\Traits\HasLifecycleHooks;

/**
 * Event — entri kalender (paritas legacy tbl events).
 * Pemilik = user_id (creator); widget calendar menampilkan event milik user.
 */
class Event extends Model
{
    use HasLifecycleHooks;
    use HasUlids;

    protected $fillable = ['title', 'description', 'start', 'end', 'color', 'user_id', 'ulid'];

    protected $casts = [
        'id'    => 'integer',
        'start' => 'datetime',
        'end'   => 'datetime',
    ];

    public static function labels(): array
    {
        return [
            'title'       => 'Judul',
            'description' => 'Deskripsi',
            'start'       => 'Mulai',
            'end'         => 'Selesai',
            'color'       => 'Warna',
        ];
    }

    /**
     * HasUlids default mengisi PRIMARY KEY — override agar mengisi kolom 'ulid'.
     */
    public function uniqueIds(): array
    {
        return ['ulid'];
    }
}
