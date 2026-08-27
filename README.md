# wasnaker-core

**Backend inti (API-only) Wasnaker** — migrasi arsitektur dari PerfexCRM ke Laravel, dirancang sebagai monorepo **core tanpa modul** + repository terpisah per modul. Core ini berfungsi sebagai fondasi aplikasi: tidak mengandung modul bisnis apa pun; setiap modul (mis. `wasnaker/sales-module`) dipasang sebagai *package* Composer.

## Stack

- **Laravel 12** (PHP ^8.2)
- **API-only** — hanya menyediakan endpoint JSON (tidak ada UI/Blade bisnis)
- **Laravel Sanctum** — autentikasi token (personal access tokens)
- **nwidart/laravel-modules** — kerangka modular, dengan strategi **vendor scan** (modul didaftarkan sebagai package Composer normal di `vendor/*/*`)
- **MySQL 8** — database `wasnaker`, koneksi `127.0.0.1:3306`

## Struktur

```
wasnaker.lan/               <- core (repo: lrvl-wasnaker_core)
├── app/                    <- kode inti Laravel (model, provider, dll)
├── bootstrap/              <- app.php (routing API + penanganan autentikasi)
├── config/
│   ├── modules.php         <- nwidart/laravel-modules (scan.enabled=true, paths=vendor/*/*)
│   └── sanctum.php         <- API token guard
├── database/migrations/    <- users, cache, jobs, personal_access_tokens
├── docs/                   <- analisis & rencana migrasi Perfex -> Laravel
├── modules/                <- placeholder (modul hidup di vendor)
├── routes/
│   └── api.php             <- /api/health, /api/user, + rute modul (/api/v1/...)
└── vendor/
    └── wasnaker/
        └── sales-module    <- contoh modul (ini commit, dipasang via path/vendor)
```

## Arsitektur modul

- **Core** = tanpa modul bisnis; difokuskan pada fondasi (auth, base services, konfigurasi).
- Tiap **modul** = repository git sendiri (mis. `wasnaker-sales-module`), nama package `wasnaker/<nama>-module`.
- Modul di-install ke core via Composer (saat ini `type:path` dengan `symlink:true` ke repo lokal; dapat dialihkan ke `type:vcs` setelah repo modul mendapat remote).
- Pendaftaran otomatis via **vendor scan**: `config/modules.php` → `scan.enabled=true`, `scan.paths = vendor/*/*`.

## Endpoint inti

| Method | URI          | Auth        | Deskripsi                          |
|--------|--------------|-------------|------------------------------------|
| GET    | `/api/health`| –           | Health check (200 JSON)            |
| GET    | `/api/user`  | `auth:sanctum` | Detail user terautentikasi       |
| (beragam) | `/api/v1/sales/*` | `auth:sanctum` | Rute dari `wasnaker/sales-module` (stub JSON) |

Semua permintaan tidak terautentikasi dikembalikan sebagai `401 {"message":"Unauthenticated."}` (JSON).

## Persyaratan instalasi

- PHP 8.2+ (di aaPanel tersedia profil 8.2 & 8.4; vhost `wasnaker.lan` berjalan di PHP 8.4 FPM)
- Extensions: `fileinfo`, `zip`, `pdo_mysql`, `mbstring`, `openssl`
- Composer 2.x, Node.js/NPM (untuk asset bila dibutuhkan)
- MySQL 8

## Instalasi

```bash
composer install
cp .env.example .env        # sesuaikan DB_DATABASE, DB_USERNAME, DB_PASSWORD
php artisan key:generate
php artisan migrate --force   # membuat schema db `wasnaker`
php artisan serve
```

Catatan: `.env` tidak dikomit (perhatikan pola ignore secret di `.gitignore`).

## Menambah modul baru

1. Buat/install package `wasnaker/<nama>-module` (repo terpisah) ke `vendor/`.
2. Jalankan `php artisan module:list` — modul terdeteksi otomatis (vendor scan) dan berstatus `[Enabled]`.
3. Implementasikan rute, controller, migration di dalam repo modul.

## Lisensi

Kode inti disebarkan di bawah **GNU GPL v2** (lihat `LICENSE`).
