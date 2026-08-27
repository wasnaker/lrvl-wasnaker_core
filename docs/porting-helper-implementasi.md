# Porting Helper PerfexCRM → Laravel API-Only

Dokumen ini merekam pemetaan fungsi helper PerfexCRM (`application/helpers/*.php`)
ke implementasi Laravel di `wasnaker-core/`.

Prinsip: hanya logika bisnis murni yang di-port. Fungsi yang bergantung view/HTML/session/
frontend tidak di-port (SKIP). Fungsi yang sudah native di Laravel tidak di-port ulang
(NATIVE).

---

## Manual Pemetaan

### func_helper.php

Dokumen referensi: `docs/analisis-helper-perfex.md`

| Fungsi Perfex | Lokasi Laravel | Status | Catatan |
|---------------|----------------|--------|---------|
| `startsWith` | `Illuminate\Support\Str::startsWith` | NATIVE | Tidak perlu port |
| `endsWith` | `Illuminate\Support\Str::endsWith` | NATIVE | Tidak perlu port |
| `strafter` | `App\Support\Helpers\Str::strafter()` | ADOP | Tidak ada di Laravel native |
| `strbefore` | `App\Support\Helpers\Str::strbefore()` | ADOP | Tidak ada di Laravel native |
| `get_string_between` | `App\Support\Helpers\Str::get_string_between()` | ADOP | Tidak ada di Laravel native |
| `sluq_it` | `App\Support\Helpers\Str::sluq_it()` | ADOP | Slugify string; tidak ada di Laravel native |
| `time_ago` | `App\Support\Helpers\Time::time_ago()` | ADOP | Wrapper Carbon `diffForHumans()` |
| `seconds_to_time_format` | `App\Support\Helpers\Time::seconds_to_time_format()` | ADOP | Tidak ada di Laravel native |
| `hours_to_seconds_format` | `App\Support\Helpers\Time::hours_to_seconds_format()` | ADOP | Sederhana, tidak ada di Laravel native |
| `array_pluck` | `Illuminate\Support\Arr::pluck` | NATIVE | Tidak perlu port |
| `in_array_multidimensional` | `App\Support\Helpers\Str::in_array_multidimensional()` | ADOP | Pencarian rekursif di array multidimensi |
| `array_flatten` | `Illuminate\Support\Arr::flatten` | NATIVE | Tidak perlu port |
| `similarity` | `App\Support\Helpers\Str::similarity()` | ADOP | Wrapper `similar_text()` PHP |
| `array_to_object` | `App\Support\Helpers\Str::array_to_object()` | ADOP | Konversi array → stdClass |

### sales_helper.php (bagian formatter)

Dokumen referensi: `docs/analisis-helper-perfex.md`

| Fungsi Perfex | Lokasi Laravel | Status | Catatan |
|---------------|----------------|--------|---------|
| `app_format_money` | `App\Support\Helpers\Number::formatMoney()` | ADOP | Formatter murni, bukan business logic |
| `app_format_number` | `App\Support\Helpers\Number::formatNumber()` | ADOP | Formatter murni |
| `get_decimal_places` | `App\Support\Helpers\Number::getDecimalPlaces()` | ADOP | Lookup dasar; nanti bisa pakai model Currency |
| `is_using_multiple_currencies` | `App\Support\Helpers\Number::isUsingMultipleCurrencies()` | ADAPT | Placeholder; nanti terhubung ke SettingService/Currency model |

**Yang tidak di-port di sini (pindah ke module service nanti):**
- `get_tax_by_id`, `get_tax_by_name`, `update_sales_total_tax_column`, `add_new_sales_item_post`
  → `modules/Sales/Services/InvoiceService.php` (business logic)

### settings_helper.php

| Fungsi Perfex | Lokasi Laravel | Status | Catatan |
|---------------|----------------|--------|----------|
| `add_option` | `App\Services\SettingService::set()` | ADOP | Key-value setting dengan tenant_id |
| `get_option` | `App\Services\SettingService::get()` | ADOP | Value setting + fallback |
| `update_option` | `App\Services\SettingService::set()` (sama) | ADOP | Update existing |
| `delete_option` | `App\Services\SettingService::delete()` | ADOP | Hapus setting |
| `option_exists` | `App\Services\SettingService::has()` | ADOP | Cek keberadaan |

Model: `App\Models\Setting`
Migration: `create_settings_table`
Dokumen domain: `docs/domain-multitenancy.md` (setting level global + per-tenant)

### database_helper.php (bagian log_activity)

| Fungsi Perfex | Lokasi Laravel | Status | Catatan |
|---------------|----------------|--------|----------|
| `log_activity` | `App\Services\ActivityLogService::log()` | ADOP | Activity log dengan context |

Model: `App\Models\ActivityLog`
Migration: `create_activity_log_table`

### user_meta_helper.php

| Fungsi Perfex | Lokasi Laravel | Status | Catatan |
|---------------|----------------|--------|----------|
| `get_staff_meta`, `update_staff_meta`, `add_staff_meta` dst. | `App\Traits\HasMetaData` trait | ADOP | Metadata key-value per entity |

Model: `App\Models\CustomMeta`
Migration: `create_custom_meta_table`

---

## Struktur File Hasil Port

```
wasnaker-core/app/
├── Support/
│   └── Helpers/
│       ├── Str.php          # dari func_helper.php (string utilities)
│       ├── Number.php       # dari sales_helper.php (formatter)
│       └── Time.php         # dari func_helper.php (time utilities)
├── Services/
│   ├── SettingService.php       # dari settings_helper.php
│   └── ActivityLogService.php   # dari database_helper.php (log_activity)
└── Traits/
    └── HasMetaData.php          # dari user_meta_helper.php
```

---

## Catatan Implementasi

1. Helper `Str.php` sengaja tidak dinamai `StringHelper` mengikuti konvensi dokumen
   (`docs/analisis-helper-perfex.md`). Jika terjadi bentrok import dengan
   `Illuminate\Support\Str` di file yang sama, pertimbangkan rename atau alias.
2. `sluq_it` di Perfex adalah fungsi slugify. Implementasi di sini mengikuti pola
   slugify standar (lowercase, dash, strip special chars).
3. `Number::formatMoney()` saat ini menggunakan format simbol mata uang statis.
   Nanti dapat digabung dengan model Currency / exchange-rate service.
4. `SettingService::findWithFallback()` mengimplementasi pola fallback global→tenant
   sesuai `docs/domain-multitenancy.md`.
5. `HasMetaData` trait menggunakan pola `morphMany` ke `CustomMeta` model.
   Entity yang punya meta tidak perlu tahu implementasi storage.

---

## Status Porting

| Helper | Versi Perfex | Versi Laravel | Status | Catatan |
|--------|--------------|---------------|--------|---------|
| func_helper.php → Str/Time | application/helpers/func_helper.php | app/Support/Helpers/ | ✅ Port | Batch 1 (ini) |
| sales_helper.php → Number (format) | application/helpers/sales_helper.php | app/Support/Helpers/Number.php | ✅ Port | Batch 1 (ini) |
| settings_helper.php → SettingService | application/helpers/settings_helper.php | app/Services/SettingService.php | ✅ Port | Batch 2 (ini): migration + model + service |
| database_helper.php → ActivityLogService | application/helpers/database_helper.php | app/Services/ActivityLogService.php | ✅ Port | Batch 3 (ini): migration + model + service |
|| user_meta_helper.php → HasMetaData | application/helpers/user_meta_helper.php | app/Traits/HasMetaData.php | ⏳ Pending migration | Batch 4 |
| relation_helper.php | application/helpers/relation_helper.php | TBD | ⏳ Belum tentu | Diskusi terpisah |
| files_helper.php → FileService | application/helpers/files_helper.php | app/Services/FileService.php | ⏳ Nanti | Batch N |

---

*Dokumen ini dibuat saat proses porting helper PerfexCRM ke Laravel API-only,
dimulai dari func_helper.php, sales_helper.php (format), settings_helper.php,
database_helper.php (log_activity), dan user_meta_helper.php.*
