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

### `sales_helper.php` — formatting money & perhitungan sales ⚠️ ADAPT
`app_format_money`, `app_format_number`, `get_decimal_places`, `is_using_multiple_currencies`,
`get_tax_by_id`, `get_tax_by_name`, `update_sales_total_tax_column`, `add_new_sales_item_post`.

→ **Keputusan:** Money formatting native di Laravel belum lengkap → adopsi formatting.
Perhitungan total/pajak pindah ke **service/domain layer** (bukan helper global).
Konteks *info format* (HTML) → skip.

### `relation_helper.php` — data relasi customer/project/lead ⚠️ ADAPT
`get_relation_data`, `get_relation_values`.

→ **Keputusan:** Logika resolusi relasi berguna → jadikan service `RelationService`.
Format HTML `init_relation_options` → skip.

### `files_helper.php` — penanganan file/upload ❌ sebagian
`is_image`, `get_file_extension`, `unique_filename`, `sanitize_file_name`, `bytesToSize`,
`validate_file`, `file_upload_max_size`, `protected_file_url_by_path`.

→ **Keputusan:** Laravel sudah punya Filesystem, Storage, `Intervention` untuk gambar.
Adopsi yang berguna: validasi file & URL aman. Fungsi render HTML → skip.

### `user_meta_helper.php` — metadata per user (staff/contact/customer) ✅ ADOP
`get_staff_meta`, `update_staff_meta`, `add_customer_meta`, `get_customer_meta`, dst.

→ **Keputusan:** Metadata key-value per entitas **sangat berguna** untuk API.
Adopsi sebagai trait `HasMetaData` + tabel `user_meta`/`meta_data` + service.

### `database_helper.php` — utilitas DB 🔄 NATIVE sebagian
`log_activity`, `add_notification`, `total_rows`, `sum_from_table`, `table_exists`,
`add_notification`, `get_department_email`.

→ **Keputusan:** `log_activity` → buat `ActivityLogService` (sangat berguna).
`add_notification` → native Laravel Notifications. Lainnya native Eloquent/Query.

### `settings_helper.php` — sistem options/settings ✅ ADOP
`add_option`, `get_option`, `update_option`, `delete_option`, `option_exists`.

→ **Keputusan:** Buat `SettingService` (tabel `settings` key-value cache). Penting untuk API.

### `core_hooks_helper.php` — hook/merge field ❌ SKIP
Hook dan merge-field khas CodeIgniter/Perfex → tidak relevan. Laravel pakai Events/`stub`.

---

## 2. ⚠️ Helper yang Perlu Diadaptasi (sebagian berguna)

### `general_helper.php`
**Adopsi:** `generate_encryption_key`, `get_timezones_list`, `get_total_days_overdue`,
`is_connected`, `is_logged_in` → ganti `auth()->check()` (API pakai token).
**Skip:** `redirect_after_login_to_current_url`, `set_alert`, `blank_page`, `access_denied`
(semua berkaitan session/frontend → Laravel middleware & JSON response).

### `staff_helper.php` — data staff & permission
**Adopsi:** `get_staff_full_name`, `get_staff`, `is_staff_member`.
**Ganti:** permission → Spatie Permission (native Laravel). Profile image → Storage URL.

### `contracts_helper.php`, `credits_notes_helper.php`, `estimates_helper.php`,
`invoices_helper.php`, `projects_helper.php`, `proposals_helper.php`, `subscriptions_helper.php`,
`tasks_helper.php`, `tickets_helper.php`, `leads_helper.php`
→ Semua helper per-modul ini berisi logika **generate nomor, status, total, due date,**
dan **format HTML/info view**.

**Keputusan:** Ambil **logika bisnis** (penomoran, status, perhitungan total) → pindah ke
**domain service per modul**. Buang **format HTML/info view** (tidak relevan API-only;
API cukup return data + frontend yang format).

### `misc_helper.php`
**Adopsi:** `maybe_add_http`, `get_weekdays_between_dates`, `generate_two_factor_auth_key`.
**Skip/ganti:** recaptcha (native), dropbox/thumbnail, signature image, alert class.

### `tags_helper.php`
**Adopsi:** logika tagging. Laravel ada package `laravel-tags` (Spatie) → gunakan itu.

### `payment_gateways_helper.php`
**Adopsi:** integrasi gateway → jadikan service/`PaymentGateway` interface.
**Ganti:** invoice_pdf → pakai `barryvdh/laravel-dompdf` atau `laravel-snappy`.

---

## 3. ❌ Helper yang TIDAK Diadopsi (view/frontend/session — API-only)

| Helper | Alasan |
|--------|--------|
| `admin_helper.php` | Menu/sidebar admin view |
| `assets_helper.php` | Aset CSS/JS frontend |
| `app_html_helper.php` | Render HTML |
| `clients_helper.php` | Area frontend customer view |
| `datatables_helper.php` | Server-side DataTables (server-rendered tabel) |
| `deprecated_helper.php` | Fungsi lama/henti pakai |
| `email_templates_helper.php` + `templates_helper.php` | Template email/HTML → ganti Laravel Mailable/Blade |
| `menu_helper.php` | Menu frontend/admin |
| `pdf_helper.php` | PDF → ganti DomPDF/Snappy di Laravel |
| `sms_helper.php` | SMS — bisa diadopsi sebagai service tetapi kecil |
| `template_helper.php`, `themes_helper.php`, `widgets_helper.php` | Theme/widget/view frontend |
| `pre_query_data_formatters_helper.php` | Format data untuk query view |
| `emails_tracking_helper.php` | Tracking email (native Laravel mailable events) |
| `fields_helper.php`, `custom_fields_helper.php` | Dur ke field/form builder |
| `upload_helper.php` | Upload → ganti Laravel Storage (sebagian diadopsi) |

---

## 4. Rekomendasi Struktur di Laravel API-Only

Berdasarkan analisis di atas, helper PerfexCRM yang **bernilai bisnis** sebaiknya
di-adopsi sebagai **service classes + helpers**, bukan sebagai satu file global seperti
CodeIgniter.

```text
app/
├── Support/
│   └── Helpers/                 # func_helper, money formatting, string utils
│       ├── Str.php
│       ├── Number.php
│       └── Time.php
├── Services/
│   ├── SettingService.php       # dari settings_helper
│   ├── ActivityLogService.php   # dari database_helper log_activity
│   ├── NotificationService.php  # notifikasi (native Laravel)
│   ├── FileService.php          # dari files_helper/upload
│   ├── RelationService.php      # dari relation_helper
│   ├── MetaDataService.php      # dari user_meta_helper
│   └── ...
├── Domain/                      # business logic per modul
│   ├── Sales/
│   │   ├── InvoiceService.php   # dari invoices_helper (logika bisnis saja)
│   │   ├── EstimateService.php
│   │   └── ...
│   ├── Projects/
│   │   └── ProjectService.php
│   └── ...
└── Traits/
    └── HasMetaData.php          # dari user_meta_helper
```

---

## 5. Kesimpulan Singkat

- **Ya, kita adopsi** helper PerfexCRM — **tapi hanya bagian logika bisnis** yang relevan
  untuk backend API.
- Helper yang berisi **format HTML/view, session auth, menu, theme, DataTables** → **skip**
  (karena API-only + frontend dipegang client/web lain).
- Fungsi yang sudah **native di Laravel** (Str, Arr, Collection, Storage, Notification,
  Spatie Permission/Tags) → **tidak perlu diadopsi ulang**.
- Cara adopsinya: ubah dari **global function** CodeIgniter menjadi **service/domain class**
  Laravel yang reusable dan bisa dipakai web, mobile, AI agent, dan integrasi lain.

---

*Dokumen dibuat berdasarkan inspeksi `application/helpers/` PerfexCRM, 27 Agustus 2026.*
