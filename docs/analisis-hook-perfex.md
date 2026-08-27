# Analisis Hooks PerfexCRM — Implementasi untuk Backend Laravel API-Only

Dokumen ini menganalisis sistem **hook** di PerfexCRM dan bagaimana implementasinya
di project backend **Laravel API-only** (`wasnaker.lan`).

## Poin Penting: Ada DUA Mekanisme "Hook" di Perfex

Perlu dibedakan karena keduanya sering tertukar:

1. **CodeIgniter Bootstrap Hooks** — `application/hooks/` + `application/config/hooks.php`
   Berjalan saat request dimulai (bootstrapping aplikasi).
2. **Action/Filter Hook System** — `hooks()->add_action()`, `add_filter()`, `do_action()`,
   `apply_filters()`. Sistem ekstensi ala WordPress (berbasis package `bainternet/php-hooks`).
   Dipakai di **155 file** core → ini yang sesungguhnya jadi "extension point" Perfex.

---

## Bagian A — Bootstrap Hooks (`application/hooks/`)

Dideklarasikan di `application/config/hooks.php`:

| Hook | Peran | Implementasi Laravel |
|------|-------|----------------------|
| `EnhanceSecurity::protect()` | Blokir bot/UA/referrer/IP buruk (list eksternal, cache 1 hari) | **Middleware** `BlockBadRequest` + WAF. Laravel punya middleware + throttle. List buruk bisa ditaruh di config/cache (Redis). |
| `App_Autoloader::register()` | Autoload class App_*/module (spl_autoload_register) | **Composer autoload + PSR-4** (native). Tidak perlu diadopsi. |
| `InitModules::handle()` | Load modul aktif + gabung `csrf_exclude_uris` | **Module Service Provider + Bootstrap** (`ModuleServiceProvider::boot`, `routes/api.php`). CSRF exclude → middleware `VerifyCsrfToken` (tetap default untuk API). |
| `InitHook::_app_init()` | Load library awal + jalankan action `modules_loaded`, `admin_init`, dsb | **AppServiceProvider / ModuleServiceProvider** `boot()` + proses registrasi. |

> **Kesimpulan A:** Bootstrap hook adalah mekanisme **startup** khas framework lama.
> Di Laravel digantikan **Service Provider + Middleware + Composer autoload** — native,
> tidak perlu sistem hook tersendiri.

---

## Bagian B — Action/Filter Hook System (yang REAL penting)

### Cara kerja di Perfex
- `hooks()->add_action('nama', callable, priority)` — daftarkan fungsi saat event.
- `hooks()->do_action('nama', args)` — jalankan semua fungsi yang terdaftar.
- `hooks()->add_filter('nama', callable, priority)` — daftarkan fungsi modifikasi data.
- `hooks()->apply_filters('nama', $value, ...args)` — jalankan fungsi, value jadi dimodifikasi.

### Fungsi inti
- **Ekstensibilitas** — modul/tema menambahkan perilaku tanpa mengubah core.
- **Loose coupling** — core memicu event; modul merespons.
- **Kustomisasi** — modifikasi data lewat filter.

---

## Bagian C — Pemetaan ke Laravel API-Only

Laravel **sudah punya sistem hook setara** secara native. Berikut pemetaannya:

### 1. `do_action` (jalankan aksi — tidak kembalikan data) → **Laravel Events**

| Perfex | Laravel |
|--------|---------|
| `hooks()->do_action('invoice_added', $id)` | `event(new InvoiceCreated($invoice))` |
| `hooks()->add_action('invoice_added', 'SomeClass@method', 10)` | *listener* di `EventServiceProvider` / `#[Listener]` |

Contoh pemetaan action Perfex → Event Laravel:

| Action Perfex | Event Laravel |
|---------------|---------------|
| `modules_loaded` | `ModulesLoaded` / `app()->booted()` |
| `admin_init` | `ServiceProvider::boot()` |
| `before_update_database` | `MigrationStarted` / `artisan migrate` hook |
| `database_updated` | `Migrated` |
| `new_ticket_...`, `ticket_..._loaded` | `TicketCreated`, `TicketReplied` |
| `app_init_admin_sidebar_menu_items` | registrasi di module's `boot()` |

### 2. `apply_filters` (modifikasi data) → **Laravel Events (returns) / Pipeline / Macros / Listeners**

Laravel tidak punya *filter* persis seperti WordPress. Opsi terbaik:

| Pola | Deskripsi |
|------|-----------|
| **Event + Listener mengubah objek** | Listener menerima objek & mengubah properti (paling mudah, setara filter) |
| **Middleware / Pipeline (`Illuminate\Pipeline`)** | Rantai transform data, cocok untuk filter bernilai kembalian |
| **Macros** (`Macroable`) | Menambah method dinamis → setara customisasi |
| **Higher Order / Decorators** | Membungkus perilaku asli |

Contoh konversi filter:
```php
// Perfex
$value = hooks()->apply_filters('before_update_invoice_item', $value, $item_id);

// Laravel
$event = new UpdatingInvoiceItem($value, $itemId);
event($event);                       // listeners bisa ubah $event->value
$value = $event->value;
```

### 3. Fitur ekstensi Perfex lain → padanan Laravel

| Fitur Perfex | Padanan Laravel |
|--------------|-----------------|
| Merging menu/tabs/sidebar (`app_menu`, `app_tabs`) | **API: frontend/client** mengelola menu — tidak di backend. Bila perlu: module expose endpoint/`MenuService`. |
| Register merge fields | **Blade + Mailable data** (template) |
| Variabel global `hooks()` | **Event dispatcher `Illuminate\Events\Dispatcher`** (`Event::dispatch`) |
| Hook prioritas (priority) | Laravel listener urutan keberdaftaran; untuk kontrol lebih → Pipeline |

---

## Bagian D — Strategi untuk Project Kita (API-Only & Modular)

### Rekomendasi utama: **pakai Laravel Events + EventServiceProvider per module**

Karena project **modular**, setiap module mendaftarkan **event & listener-nya sendiri**
di `modules/<modul>/Providers/EventServiceProvider.php`.

```text
modules/
├── Sales/
│   ├── Events/
│   │   ├── InvoiceCreated.php
│   │   └── PaymentRecorded.php
│   ├── Listeners/
│   │   ├── SendInvoiceEmail.php
│   │   └── SyncAccountLedger.php
│   └── Providers/
│       └── EventServiceProvider.php   # register events + listeners modul ini
└── Project/
    ├── Events/
    │   └── TaskStatusChanged.php
    └── Listeners/
        └── NotifyProjectMember.php
```

### Aturan implementasi

1. **Tidak perlu sistem hooks kustom** — Laravel Events sudah cukup & idiomatis.
2. Perancangan **event per domain** dilakukan saat memindahkan tiap modul
   (dari daftar `do_action`/`apply_filters` yang ada di code Perfex tiap modul).
3. Event LARANG dipakai untuk control-flow yang kompleks antar modul → gunakan
   **Jobs/Queue** + **interface** bila butuh kepastian urutan.
4. Untuk kebutuhan font-extension point di level API (mis. webhook keluar / plugin),
   cukup siapkan **WebhookOutService** + queue, dan **API hook-out** — bukan hook internal.
5. Bootstrap hooks lama (`EnhanceSecurity`, `InitModules`, `InitHook`) →
   **Middleware + ServiceProvider** (native), tidak perlu adopsi.

### Contoh konkret migrasi hook Perfex → Laravel

| Hook Perfex (contoh di code) | Implementasi Laravel |
|------------------------------|----------------------|
| `hooks()->do_action('invoice_created', $id)` | `event(new InvoiceCreated($invoice))` → listener kirim email |
| `hooks()->apply_filters('invoice_status_changed', ...)` | Event `InvoiceStatusChanged` + listener ubah data |
| `hooks()->do_action('lead_converted_to_customer')` | `LeadConvertedToCustomer` → listener buat customer default |
| `hooks()->add_filter('before_send_ticket_reply', ...)` | Middleware/Pipeline pada service `TicketService::reply()` |

---

## Kesimpulan

- **Bootstrap hooks** (`application/hooks/`) → digantikan **Service Provider + Middleware +
  Composer autoload** Laravel (native). Tidak diadopsi ulang.
- **Action/Filter hooks** (`hooks()->*`) → dipetakan ke **Laravel Events** (`do_action`) dan
  **Pipeline/decorator/event-mutation** (`apply_filters`).
- Tidak perlu membangun ulang sistem hooks khas WordPress di Laravel; gunakan kemudahan
  `Event::dispatch` + per-module `EventServiceProvider`.
- Saat memindahkan tiap modul, **inventarisasi** `do_action`/`apply_filters` dalam code
  Perfex modul tsb → konversi menjadi Event/Listener di module Laravel.

---

*Dokumen dibuat berdasarkan inspeksi `application/hooks/`, `application/config/hooks.php`,
`application/third_party/action_hooks.php`, dan `application/helpers/core_hooks_helper.php`,
27 Agustus 2026.*
