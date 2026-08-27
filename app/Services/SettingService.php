<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Setting;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Service pengelolaan settings ( SettingsService ).
 * 
 * Diadopsi dari settings_helper.php PerfexCRM:
 *   - add_option → set()
 *   - get_option → get() dengan fallback
 *   - update_option → set() (sama)
 *   - delete_option → delete()
 *   - option_exists → has()
 *
 * REF: docs/porting-helper-implementasi.md
 */
class SettingService
{
    /**
     * Simpan atau update setting.
     * 
     * @param string $key   Nama setting
     * @param mixed  $value Nilai setting (disimpan sebagai teks)
     * @param int|null $tenantId  Tenant scope (null = global/platform)
     * @return \App\Models\Setting
     */
    public function set(string $key, mixed $value, ?int $tenantId = null): Setting
    {
        $existing = $this->resolveQuery($tenantId)->where('key', $key)->first();

        if ($existing) {
            $existing->value = $value;
            $existing->save();
            return $existing;
        }

        return Setting::create([
            'key'       => $key,
            'value'     => $value,
            'tenant_id' => $tenantId,
        ]);
    }

    /**
     * Ambil nilai setting. Jika tidak ada, kembalikan $default.
     * 
     * @param string $key      Nama setting
     * @param mixed  $default  Nilai fallback jika tidak ada
     * @param int|null $tenantId Scope (null = global)
     * @return mixed
     */
    public function get(string $key, mixed $default = null, ?int $tenantId = null): mixed
    {
        $record = $this->resolveQuery($tenantId)->where('key', $key)->first();

        if (!$record) {
            return $default;
        }

        return $record->value;
    }

    /**
     * Hapus setting.
     * 
     * @param string $key      Nama setting
     * @param int|null $tenantId Scope
     * @return bool
     */
    public function delete(string $key, ?int $tenantId = null): bool
    {
        return $this->resolveQuery($tenantId)->where('key', $key)->delete() > 0;
    }

    /**
     * Cek apakah setting ada.
     * 
     * @param string $key      Nama setting
     * @param int|null $tenantId Scope
     * @return bool
     */
    public function has(string $key, ?int $tenantId = null): bool
    {
        return $this->resolveQuery($tenantId)->where('key', $key)->exists();
    }

    /**
     * Ambil semua settings dalam scope tertentu.
     * 
     * @param int|null $tenantId Scope
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function all(?int $tenantId = null): \Illuminate\Database\Eloquent\Collection
    {
        return $this->resolveQuery($tenantId)->get();
    }

    /**
     * Query builder untuk scope tertentu.
     * 
     * @param int|null $tenantId Scope (null = tanpa filter tenant)
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function resolveQuery(?int $tenantId): \Illuminate\Database\Eloquent\Builder
    {
        $query = Setting::query();

        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        }

        return $query;
    }

    /**
     * Alias get() dengan nama yang familiar dari PerfexCRM.
     * 
     * @param string $key      Nama setting
     * @param mixed  $default  Nilai fallback
     * @param int|null $tenantId Scope
     * @return mixed
     */
    public function get_option(string $key, mixed $default = null, ?int $tenantId = null): mixed
    {
        return $this->get($key, $default, $tenantId);
    }
}
