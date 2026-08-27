<?php

declare(strict_types=1);

namespace App\Traits;

use App\Models\CustomMeta;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Trait metadata key-value per entity.
 *
 * Diadopsi dari user_meta_helper.php PerfexCRM:
 *   - get_staff_meta, update_staff_meta, add_staff_meta
 *   - get_customer_meta, add_customer_meta
 *
 * REF: docs/porting-helper-implementasi.md
 */
trait HasMetaData
{
    /**
     * Ambil seluruh metadata entity sebagai array.
     *
     * @return array<string, mixed>
     */
    public function getMetaArray(): array
    {
        return $this->meta()->get()->keyBy('meta_key')
            ->map(fn (CustomMeta $m): mixed => $m->meta_value)
            ->toArray();
    }

    /**
     * Ambil satu nilai meta oleh key.
     *
     * @param string $key
     * @param mixed $default
     */
    public function getMeta(string $key, mixed $default = null): mixed
    {
        $record = $this->meta()->where('meta_key', $key)->first();

        if (!$record) {
            return $default;
        }

        return $record->meta_value;
    }

    /**
     * Simpan / update satu nilai meta.
     *
     * @param string $key
     * @param mixed $value
     */
    public function setMeta(string $key, mixed $value): CustomMeta
    {
        $record = $this->meta()->where('meta_key', $key)->first();

        if ($record) {
            $record->meta_value = $value;
            $record->save();
            return $record;
        }

        return $this->meta()->create([
            'meta_key'  => $key,
            'meta_value' => $value,
        ]);
    }

    /**
     * Hapus satu meta oleh key.
     *
     * @param string $key
     */
    public function deleteMeta(string $key): bool
    {
        return $this->meta()->where('meta_key', $key)->delete() > 0;
    }

    /**
     * Simpan banyak meta sekaligus (replace).
     *
     * @param array<string, mixed> $data
     */
    public function setMetaArray(array $data): void
    {
        // Hapus meta lama yang tidak ada di data baru
        $currentKeys = $this->meta()->pluck('meta_key')->toArray();
        $newKeys = array_keys($data);

        foreach (array_diff($currentKeys, $newKeys) as $oldKey) {
            $this->deleteMeta($oldKey);
        }

        // Upsert meta baru
        foreach ($data as $key => $value) {
            $this->setMeta($key, $value);
        }
    }

    /**
     * Relasi ke custom meta.
     */
    public function meta(): MorphMany
    {
        return $this->morphMany(CustomMeta::class, 'meta');
    }
}
