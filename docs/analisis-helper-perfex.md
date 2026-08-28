# Analisis Helper PerfexCRM — Adopsi untuk Backend Laravel API-Only

Dokumen ini membahas **45 file helper** di `application/helpers/` PerfexCRM dan
bagaimana pendekatan adopsinya untuk project backend **Laravel API-only** (`wasnaker.lan`).

> Prinsip utama: karena project ini **API-only** (tanpa view/frontend), banyak helper
> yang berkaitan dengan **render HTML/view** dan **session-based auth** TIDAK relevan
> untuk diadopsi langsung. Laravel juga sudah punya banyak fungsi native yang menggantikannya.

---

## Kategori Keputusan

| Kode | Makna |
|------|-------|
| ✅ **ADOP** | Logika berguna untuk backend API → perlu dipindah (sebagai service/helper Laravel) |
| ⚠️ **ADAPT** | Ada gunanya tapi perlu dimodifikasi untuk konteks API/token-based |
| ❌ **SKIP** | Terkait view/HTML/session-frontend → tidak relevan untuk API-only |
| 🔄 **NATIVE** | Sudah tersedia native di Laravel (tidak perlu diadopsi) |

---

## 1. ✅ Helper yang WAJIB Diadopsi (core logika bisnis)

### `func_helper.php` — fungsi string/array/utilitas umum 🔄 sebagian
`startsWith`, `endsWith`, `strafter`, `strbefore`, `get_string_between`, `sluq_it`,
`time_ago`, `seconds_to_time_format`, `hours_to_seconds_format`, `array_pluck`,
`in_array_multidimensional`, `array_flatten`, `similarity`, `array_to_object`.

→ **Keputusan:** Sebagian sudah native di Laravel (`Str::*`, `Arr::*`, Collection).
Adopsi yang **tidak ada** native-nya: `sluq_it`, `strafter/strbefore`,
`seconds_to_time_format`, `hours_to_seconds_format`, `similarity`. Buat sebagai
`app/Support/Helpers` atau utility class.

**STATUS: ✅ SELESAI (Batch 1)**
- `app/Support/Helpers/Str.php` — `strafter`, `strbefore`, `slug_it`, `similarity`, dll
- `app/Support/Helpers/Number.php` — format angka, `seconds_to_time_format`, `hours_to_seconds_format`
- `app/Support/Helpers/Time.py` — time utilities
- **Commit:** `70cb910` | **Push:** `lrvl-wasnaker_core` | **Test:** tinker 6/6 passed
- **Apidocs:** ❌ N/A (helper murni, tidak punya endpoint)

---

### `sales_helper.php` — formatting money & perhitungan sales ⚠️ ADAPT
`app_format_money`, `app_format_number`, `get_decimal_places`, `is_using_multiple_currencies`,
`get_tax_by_id`, `get_tax_by_name`, `update_sales_total_tax_column`, `add_new_sales_item_post`.

→ **Keputusan:** Money formatting native di Laravel belum lengkap → adopsi formatting.
Perhitungan total/pajak pindah ke **service/domain layer** (bukan helper global).
Konteks *info format* (HTML) → skip.

**STATUS: ⚠️ PARCIAL (diteruskan ke Sales module)**
- Formatting money/number → diadopsi di `Str/Number` helpers (Batch 1)
- Perhitungan total/pajak/tax → **belum**, akan dipindah ke `Sales/InvoiceService` dll di module
- **Catatan:** Ini helper per-modul (Sales), bukan core. Akan dikerjakan saat build Sales module.
- **Apidocs:** ❌ Belum (belum ada controller endpoint)

---

### `relation_helper.php` — data relasi customer/project/lead ⚠️ ADAPT
`get_relation_data`, `get_relation_values`.

→ **Keputusan:** Logika resolusi relasi berguna → jadikan service `RelationService`.
Format HTML `init_relation_options` → skip.

**STATUS: ✅ SELESAI (Baru saja)**
- `app/Services/RelationService.php` — inti resolver (core, hook-based)
- `app/Exceptions/RelationTypeNotRegisteredException.php` — guard 404
- `app/Http/Controllers/RelationController.php` — API `GET /api/relations/types`, `GET /api/relations/{type}/{id}`
- Dummy resolver `user` di core; module (Sales) register `customer`/`project`/`lead` via hook
- **Commit:** `d841801` | **Push:** `lrvl-wasnaker_core` + `apidocs-wasnaker`
- **Apidocs:** ✅ ADA (generate + push ke `apidocs-wasnaker`)

---

### `files_helper.php` — penanganan file/upload ❌ sebagian
`is_image`, `get_file_extension`, `unique_filename`, `sanitize_file_name`, `bytesToSize`,
`validate_file`, `file_upload_max_size`, `protected_file_url_by_path`.

→ **Keputusan:** Laravel sudah punya Filesystem, Storage, `Intervention` untuk gambar.
Adopsi yang berguna: validasi file & URL aman. Fungsi render HTML → skip.

**STATUS: ✅ SELESAI (Batch 5 + API)**
- `app/Services/FileService.php` — utility + `storeUpload()` Laravel Storage per-tenant
- `app/Models/Attachment.py` + migration `attachments` (mirip `tblfiles` Perfex)
- `app/Http/Controllers/FileController.php` — upload, download, preview, limits
- **Commit:** `3e0d74e` | **Push:** `lrvl-wasnaker_core` + `apidocs-wasnaker`
- **HTTP test:** UPLOAD:201 SHOW:200 DOWNLOAD:200 DEL:200 AFTER:404
- **Apidocs:** ✅ ADA (generate + push ke `apidocs-wasnaker`)

---

### `user_meta_helper.php` — metadata per user (staff/contact/customer) ✅ ADOP
`get_staff_meta`, `update_staff_meta`, `add_customer_meta`, `get_customer_meta`, dst.

→ **Keputusan:** Metadata key-value per entitas **sangat berguna** untuk API.
Adopsi sebagai trait `HasMetaData` + tabel `custom_meta` + polymorphic API.

**STATUS: ✅ SELESAI (Batch 3/4)**
- `app/Traits/HasMetaData.php` — trait (getMeta, setMeta, deleteMeta, setMetaArray, getMetaArray)
- `app/Models/CustomMeta.py` + migration `custom_meta`
- `app/Http/Controllers/MetaController.php` — 5 route polymorphic `GET/POST/PUT/DELETE /api/meta/{type}/{id}/{key}`
- User model sudah pakai trait `HasMetaData`
- **Commit:** `a21fca5` | **Push:** `lrvl-wasnaker_core` + `apidocs-wasnaker`
- **HTTP test:** all 200/404 scenarios passed
- **Apidocs:** ✅ ADA (generate + push ke `apidocs-wasnaker`)

---

### `database_helper.php` — utilitas DB 🔄 NATIVE sebagian
`log_activity`, `add_notification`, `total_rows`, `sum_from_table`, `table_exists`,
`add_notification`, `get_department_email`.

→ **Keputusan:** `log_activity` → buat `ActivityLogService` (sangat berguna).
`add_notification` → native Laravel Notifications. Lainnya native Eloquent/Query.

**STATUS: ✅ SELESAI (Batch 3)**
- `app/Services/ActivityLogService.py` + `ActivityLog` model + migration
- `app/Http/Controllers/ActivityLogController.php` — REST resource (index, show, store, destroy)
- **Commit:** `58e14cd` | **Push:** `lrvl-wasnaker_core` + `apidocs-wasnaker`
- **Bug fix:** `subject_type` null → fixed, re-pushed `51957e6`
- **Apidocs:** ✅ ADA (generate + push ke `apidocs-wasnaker`)

---

### `settings_helper.php` — sistem options/settings ✅ ADOP
`add_option`, `get_option`, `update_option`, `delete_option`, `option_exists`.

→ **Keputusan:** Buat `SettingService` (tabel `settings` key-value cache). Penting untuk API.

**STATUS: ✅ SELESAI (Batch 2 + fix)**
- Migration `settings` dengan composite unique `['key','tenant_id']` (multi-tenant)
- `app/Models/Setting.py` + `app/Services/SettingService.py` (key-value: get/set/delete/has/all/findWithFallback)
- `app/Http/Controllers/SettingController.php` — key-value API (`GET/PUT/DELETE /api/settings/{key}`, `POST /api/settings/bulk`)
- **Commit:** `9360444` (fix) + `53a673f` (API) | **Push:** `lrvl-wasnaker_core` + `apidocs-wasnaker`
- **HTTP test:** 401 tanpa token, 200 dengan token, persist OK
- **Apidocs:** ✅ ADA (generate + push ke `apidocs-wasnaker`)

---

### `core_hooks_helper.php` — hook/merge field ❌ SKIP
Hook dan merge-field khas CodeIgniter/Perfex → tidak relevan. Laravel pakai Events/`stub`.

**STATUS: ❌ SKIP** — tidak relevan untuk API-only
- **Apidocs:** ❌ N/A

---

## 2. ⚠️ Helper yang Perlu Diadaptasi (sebagian berguna)

### `general_helper.py`
**Adopsi:** `generate_encryption_key`, `get_timezones_list`, `get_total_days_overdue`,
`is_connected`, `is_logged_in` → ganti `auth()->check()` (API pakai token).
**Skip:** `redirect_after_login_to_current_url`, `set_alert`, `blank_page`, `access_denied`
(semua berkaitan session/frontend → Laravel middleware & JSON response).

**STATUS: 🔄 ONGOING** — sebagian native, `generate_encryption_key` butuh service, timezones native Laravel
- **Apidocs:** ❌ Belum

---

### `staff_helper.py` — data staff & permission
**Adopsi:** `get_staff_full_name`, `get_staff`, `is_staff_member`.
**Ganti:** permission → Spatie Permission (native Laravel). Profile image → Storage URL.

**STATUS: ⚠️ PENDING** — akan di-handle di Sales module (user/staff domain)
- **Apidocs:** ❌ Belum

---

### `contracts_helper.php`, `credits_notes_helper.py`, `estimates_helper.php`,
`invoices_helper.php`, `projects_helper.py`, `proposals_helper.php`, `subscriptions_helper.php`,
`tasks_helper.py`, `tickets_helper.py`, `leads_helper.py`
→ Semua helper per-modul ini berisi logika **generate nomor, status, total, due date**,
dan **format HTML/info view**.

**Keputusan:** Ambil **logika bisnis** (penomoran, status, perhitungan total) → pindah ke
**domain service per modul**. Buang **format HTML/info view** (tidak relevan API-only;
API cukup return data + frontend yang format).

**STATUS: ⚠️ PENDING (Sales Module)** — ini tugas module `wasnaker-sales-module`:
- `InvoiceService` (nomor, total, status, due date)
- `EstimateService`, `ProjectService`, `TaskService`, `LeadService`, dll
- **Catatan:** core HANYA sediakan cross-cutting; domain logic di module
- **Apidocs:** ❌ Belum (belum ada controller endpoint)

---

### `misc_helper.py`
**Adopsi:** `maybe_add_http`, `get_weekdays_between_dates`, `generate_two_factor_auth_key`.
**Skip/ganti:** recaptcha (native), dropbox/thumbnail, signature image, alert class.

**STATUS: 🔄 PARTIAL** — `maybe_add_http` → helper, `get_weekdays_between_dates` → native Carbon, 2FA → Laravel Fortify/Sanctum
- **Apidocs:** ❌ Belum

---

### `tags_helper.py`
**Adopsi:** logika tagging. Laravel ada package `laravel-tags` (Spatie) → gunakan itu.

**STATUS: 🔄 DEFERRED** — pakai Spatie Tags saat butuh, bukan core
- **Apidocs:** ❌ N/A

---

### `payment_gateways_helper.py`
**Adopsi:** integrasi gateway → jadikan service/`PaymentGateway` interface.
**Ganti:** invoice_pdf → pakai `barryvdh/laravel-dompdf` atau `laravel-snappy`.

**STATUS: 🔄 DEFERRED** — bukan core, modul Payment terpisah
- **Apidocs:** ❌ N/A

---

## 3. ❌ Helper yang TIDAK Diadopsi (view/frontend/session — API-only)

| Helper | Alasan | Apidocs |
|--------|--------|---------|
| `admin_helper.php` | Menu/sidebar admin view | ❌ N/A |
| `assets_helper.php` | Aset CSS/JS frontend | ❌ N/A |
| `app_html_helper.php` | Render HTML | ❌ N/A |
| `clients_helper.php` | Area frontend customer view | ❌ N/A |
| `datatables_helper.php` | Server-side DataTables (server-rendered tabel) | ❌ N/A |
| `deprecated_helper.py` | Fungsi lama/henti pakai | ❌ N/A |
| `email_templates_helper.php` + `templates_helper.php` | Template email/HTML → ganti Laravel Mailable/Blade | ❌ N/A |
| `menu_helper.py` | Menu frontend/admin | ❌ N/A |
| `pdf_helper.py` | PDF → ganti DomPDF/Snappy di Laravel | ❌ N/A |
| `sms_helper.py` | SMS — bisa diadopsi sebagai service tetapi kecil | ❌ N/A |
| `template_helper.php`, `themes_helper.py`, `widgets_helper.php` | Theme/widget/view frontend | ❌ N/A |
| `pre_query_data_formatters_helper.py` | Format data untuk query view | ❌ N/A |
| `emails_tracking_helper.py` | Tracking email (native Laravel mailable events) | ❌ N/A |
| `fields_helper.php`, `custom_fields_helper.py` | Dur ke field/form builder | ❌ N/A |
| `upload_helper.py` | Upload → ganti Laravel Storage (sebagian diadopsi di FileService) | ❌ N/A |

**STATUS: ❌ SKIP — tidak relevan API-only**

---

## 4. Rekomendasi Struktur di Laravel API-Only (UPDATE: status implementasi)

Berdasarkan analisis di atas, helper PerfexCRM yang **bernilai bisnis** sebaiknya
di-adopsi sebagai **service classes + helpers**, bukan sebagai satu file global seperti
CodeIgniter.

```text
app/
├── Support/
│   └── Helpers/                 # func_helper, money formatting, string utils
│       ├── Str.php              ✅ DONE (Batch 1)
│       ├── Number.php           ✅ DONE (Batch 1)
│       └── Time.php             ✅ DONE (Batch 1)
├── Services/
│   ├── SettingService.php       ✅ DONE (Batch 2)
│   ├── ActivityLogService.py    ✅ DONE (Batch 3)
│   ├── NotificationService.php  🔄 NATIVE (Laravel Notifications)
│   ├── FileService.php          ✅ DONE (Batch 5)
│   ├── RelationService.py       ✅ DONE (baru)
│   ├── MetaDataService.py       ➡️ HasMetaData trait (Batch 4)
│   └── ...
├── Domain/                      # business logic per modul
│   ├── Sales/                   ⚠️ PENDING (Sales Module)
│   │   ├── InvoiceService.py    # dari invoices_helper (logika bisnis saja)
│   │   ├── EstimateService.py
│   │   └── ...
│   ├── Projects/
│   │   └── ProjectService.py
│   └── ...
└── Traits/
     └── HasMetaData.py          ✅ DONE (Batch 4)
```

---

## 5. Ringkasan Status Porting (UPDATE — with Apidocs column)

| Helper | Kategori | Status | Lokasi Implementasi | Commit/Batch | Apidocs |
|--------|----------|--------|---------------------|--------------|---------|
| `func_helper.php` | ADOP/NATIVE | ✅ SELESAI | `app/Support/Helpers/Str.php, Number.py, Time.py` | Batch 1 (`70cb910`) | ❌ N/A (helper only) |
| `settings_helper.php` | ADOP | ✅ SELESAI | `SettingService`, `SettingController` | Batch 2 (`9360444`/`53a673f`) | ✅ ADA |
| `database_helper.php` (log_activity) | ADOP/NATIVE | ✅ SELESAI | `ActivityLogService`, `ActivityLogController` | Batch 3 (`58e14cd`/`51957e6`) | ✅ ADA |
| `user_meta_helper.php` | ADOP | ✅ SELESAI | `HasMetaData`, `CustomMeta`, `MetaController` | Batch 3/4 (`a21fca5`) | ✅ ADA |
| `files_helper.php` | ADOP/NATIVE | ✅ SELESAI | `FileService`, `Attachment`, `FileController` | Batch 5 (`3e0d74e`) | ✅ ADA |
| `relation_helper.php` | ADAPT | ✅ SELESAI | `RelationService`, `RelationController`, exception | `d841801` | ✅ ADA |
| `sales_helper.php` | ADAPT | ⚠️ PARCIAL | `Str/Number` + future `Sales/*Service` | - | ❌ Belum |
| `general_helper.py` | ADAPT | 🔄 PARTIAL | native Laravel / future utils | - | ❌ Belum |
| `staff_helper.py` | ADAPT | ⚠️ PENDING | Sales module | - | ❌ Belum |
| Module helpers (invoices, projects, dll) | ADAPT | ⚠️ PENDING | `Sales/*Service` di module | - | ❌ Belum |
| `misc_helper.py` | ADAPT | 🔄 PARTIAL | native / future | - | ❌ Belum |
| `tags_helper.py` | ADAPT | 🔄 DEFERRED | Spatie Tags | - | ❌ N/A |
| `payment_gateways_helper.py` | ADAPT | 🔄 DEFERRED | Payment module | - | ❌ N/A |
| View/frontend helpers (16 files) | SKIP | ❌ SKIP | - | - | ❌ N/A |

**Legenda Apidocs:**
- ✅ **ADA** = Scribe generate + push ke repo `apidocs-wasnaker` (terdokumentasi di apidocs.wasnaker.lan)
- ❌ **Belum** = Belum ada controller endpoint, atau belum di-generate
- ❌ **N/A** = Tidak relevan (helper murni / view-only / skip)

---

## 6. Kesimpulan Singkat

- **Ya, kita adopsi** helper PerfexCRM — **tapi hanya bagian logika bisnis** yang relevan
  untuk backend API.
- Helper yang berisi **format HTML/view, session auth, menu, theme, DataTables** → **skip**
  (karena API-only + frontend dipegang client/web lain).
- Fungsi yang sudah **native di Laravel** (Str, Arr, Collection, Storage, Notification,
  Spatie Permission/Tags) → **tidak perlu diadopsi ulang**.
- Cara adopsinya: ubah dari **global function** CodeIgniter menjadi **service/domain class**
  Laravel yang reusable dan bisa dipakai web, mobile, AI agent, dan integrasi lain.

---

## 7. Sisa Pekerjaan (Next Steps)

| Prioritas | Item | Catatan |
|-----------|------|---------|
| **High** | Sales Module domain services | `InvoiceService`, `EstimateService`, `ProjectService`, `LeadService`, `TaskService`, `CustomerService` — logika bisnis dari helper modul Perfex |
| **High** | Sales Module API controllers + routes | Expose domain services via REST API |
| **Medium** | Apply `HasMetaData` ke entity Sales (Customer, Project, Lead, Invoice, dll) | Trait sudah siap, tinggal pakai di model module |
| **Medium** | Register relation resolvers di Sales module | `RelationService::registerResolver('customer', ...)` di `SalesServiceProvider` |
| **Low** | Frontend menu (Next.js) untuk Settings, ActivityLog, Meta, Files, Relations | SPA pages consuming existing APIs |
| **Low** | `general_helper.py` sisa → utility class | `generate_encryption_key`, `get_weekdays_between_dates` |
| **Low** | Spatie Tags integration (jika butuh tagging) | `tags_helper.py` |

---

*Dokumen dibuat berdasarkan inspeksi `application/helpers/` PerfexCRM, 27 Agustus 2026.*
*Diupdate: 28 Agustus 2026 — status porting tiap helper + kolom Apidocs ditambahkan.*