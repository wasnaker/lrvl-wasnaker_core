# Resume — Migrasi PerfexCRM ke Backend Laravel API-Only

## 1. Tujuan

- Migrasi arsitektur PerfexCRM menuju sistem yang lebih modular.
- Business logic PerfexCRM akan **diekstraksi ke backend Laravel baru**.
- Laravel menjadi core backend dan menyediakan API sebagai pintu utama aplikasi.
- PerfexCRM lama dipertahankan sementara sebagai legacy/reference system selama masa transisi.
- Target akhir adalah satu backend/business logic yang dapat digunakan oleh berbagai client.

## 2. Strategi Migrasi

- Business logic tidak lagi bergantung pada controller dan view PerfexCRM.
- Logic bisnis dipindahkan secara bertahap ke Laravel.
- Sistem lama dan sistem baru dapat berjalan paralel selama masa transisi.
- Migrasi dilakukan per modul agar risiko perubahan dapat dikendalikan.
- Database dan data existing PerfexCRM harus dipertahankan kompatibilitasnya selama proses migrasi.
- Setelah modul berhasil dipindahkan dan diuji, frontend/client baru menggunakan API Laravel.

## 3. Arsitektur Target

```text
                    ┌─────────────────┐
                    │   Web Frontend  │
                    └────────┬────────┘
                             │
                    ┌────────▼────────┐
                    │   Laravel API   │
                    │    Backend      │
                    └────────┬────────┘
                             │
             ┌───────────────┼────────────────┐
             │               │                │
        Authentication   Business Logic    Authorization
        Sanctum/Fortify   Services/Jobs     Permission
             │               │                │
             └───────────────┼────────────────┘
                             │
                    ┌────────▼────────┐
                    │    Database     │
                    └─────────────────┘
                             │
                    ┌────────▼────────┐
                    │ Redis / Queue   │
                    │    Workers      │
                    └─────────────────┘
```

## 4. Laravel Stack

### Core

- **Laravel Framework**
  - Core backend.
  - REST API.
  - Routing.
  - Dependency Injection.
  - Eloquent ORM.
  - Validation.
  - Configuration dan environment management.

### Authentication

- **Laravel Sanctum**
  - API authentication.
  - Token-based authentication.
  - Cocok untuk SPA, mobile application, internal API dan service client.

- **Laravel Fortify**
  - Login.
  - Registration.
  - Password reset.
  - Email verification.
  - Two-factor authentication bila diperlukan.

### Authorization

- **Spatie Laravel Permission**
  - Role management.
  - Permission management.
  - Capability management.
  - User-role relationship.
  - Role-permission relationship.

- **Laravel Policies & Gates**
  - Authorization pada level resource dan action.
  - Memastikan user hanya dapat mengakses resource yang memang diperbolehkan.

### Background Processing

- **Laravel Queue**
  - Menjalankan proses berat secara asynchronous.
  - Menghindari proses panjang berada langsung di HTTP request.
  - Mengurangi risiko PHP `max_execution_time` timeout.

- **Laravel Jobs**
  - Generate dokumen.
  - Import/export.
  - Processing data.
  - Integrasi API eksternal.
  - Pekerjaan berat lainnya.

- **Laravel Horizon**
  - Monitoring queue.
  - Monitoring worker.
  - Management job berbasis Redis.

### Scheduling

- **Laravel Scheduler**
  - Scheduled task.
  - Maintenance task.
  - Sinkronisasi berkala.
  - Processing otomatis.

### Event & Notification

- **Laravel Events & Listeners**
  - Memisahkan business event dari proses yang mengikutinya.
  - Mengurangi coupling antar modul.

- **Laravel Notifications**
  - Email notification.
  - Database notification.
  - Channel notification lainnya.

### API

- **Laravel API Resources**
  - Standardisasi response JSON.
  - Memisahkan representasi API dari model database.

- **Laravel Form Requests**
  - Validasi request.
  - Authorization request.
  - Menjaga controller tetap tipis.

- **Laravel HTTP Client**
  - Komunikasi dengan external API/service.

### Data & Infrastructure

- **Laravel Eloquent ORM**
  - Database abstraction.
  - Model dan relationship.
  - Query database.

- **Redis**
  - Queue backend.
  - Cache.
  - Shared state bila diperlukan.

- **Laravel Cache**
  - Caching data yang sering digunakan.
  - Mengurangi beban database.

- **Laravel Storage**
  - Abstraksi penyimpanan file.
  - Dapat diarahkan ke local storage maupun object storage.

- **Laravel Logging**
  - Application logging.
  - Error dan operational logging.

- **Laravel Reverb**
  - Real-time/WebSocket.
  - Digunakan bila diperlukan untuk dashboard realtime, notification realtime, status job, dan kebutuhan sejenis.

## 5. Prinsip Business Logic

- Controller tidak menjadi tempat utama business logic.
- Controller bertugas menerima request dan meneruskan proses ke layer aplikasi/business.
- Business logic ditempatkan pada service/domain layer yang dapat digunakan kembali.
- Business logic tidak boleh bergantung pada tampilan frontend.
- Business logic tidak boleh bergantung pada controller tertentu.
- API menjadi interface untuk mengakses business logic.
- Web, mobile, AI agent, automation, dan integrasi pihak ketiga dapat menggunakan backend yang sama.

### 5.1 Penempatan Service — Level Module (keputusan arsitektur)

> **Keputusan:** Service bisnis per modul (mis. `InvoiceService`, `EstimateService`,
> `ProjectService`) **berada di level module**, bukan di core `app/`.

Alasan:
- Sesuai konsep awal: modul yang dihilangkan dari core PerfexCRM menjadi **module tersendiri**.
- Module menjadi unit mandiri yang membungkus service + model + routes + migrations + dll.
- Service modul tidak bergantung pada core; komunikasi antar modul lewat interface/events,
  bukan dependency langsung.
- Core hanya menyimpan **cross-cutting service** (lintas modul) yang benar-benar reusabel.

Pembagian dua level:

- **Level Core (`app/`)** — hanya service lintas-modul & reusabel:
  `SettingService`, `ActivityLogService`, `NotificationService`, `FileService`,
  `RelationService`, `MetaDataService`, helper `Str/Number/Time`, trait `HasMetaData`.
- **Level Module (`modules/*`)** — service bisnis milik masing-masing modul:
  `InvoiceService`, `EstimateService`, `ProjectService`, `ContactService`, dst.

### 5.2 Struktur Module (berdasar konsep awal + analisis helper)

```text
modules/
├── Sales/
│   ├── Invoice/
│   │   ├── src/
│   │   │   ├── Services/
│   │   │   │   └── InvoiceService.php      # business logic (dari invoices_helper)
│   │   │   ├── Models/
│   │   │   │   └── Invoice.php
│   │   │   ├── Http/
│   │   │   │   ├── Controllers/
│   │   │   │   └── Requests/
│   │   │   └── Events/                      # komunikasi antar modul
│   │   ├── Routes/
│   │   │   └── api.php
│   │   └── Database/
│   │       └── migrations/
│   ├── Estimate/                 # EstimateService (dari estimates_helper)
│   └── ...
├── Project/                      # ProjectService (dari projects_helper)
│   └── ...
├── Support/                      # TicketService (dari tickets_helper)
├── HR/                           # StaffService / RoleService
├── Content/                      # KnowledgeBase / Announcement / EmailTemplate
└── ...
```

Aturan:
- Tiap modul = satu unit mandiri (service + model + routes + migrations + events + jobs).
- Service modul TIDAK menempel di core `app/` — menempel di module miliknya.
- Komunikasi antar modul melalui **Events/Listeners** dan **interface**, bukan dependency langsung.
- Implementasi di Laravel dapat memakai: package `nwidart/laravel-modules`, struktur folder
  manual (`modules/*`), atau struktur package php iteratif — disepakati saat setup project.

## 6. Masalah PHP Traditional yang Ingin Dihindari

- PHP HTTP request memiliki batas waktu eksekusi.
- `max_execution_time` dapat menyebabkan proses panjang mengalami timeout.
- Proses berat tidak sebaiknya dijalankan langsung dalam request API.
- Solusi:
  - API menerima request.
  - API membuat Job.
  - Job dimasukkan ke Queue.
  - Worker menjalankan proses di background.
  - Client mendapatkan status/progress melalui API.
- Dengan pola ini, proses berat tidak bergantung pada lifetime HTTP request.

## 7. Pemisahan Jenis Proses

Sistem akan membedakan:

- **Synchronous API Request**
  - Operasi cepat.
  - Harus segera memberikan response.

- **Background Job**
  - Operasi berat.
  - Diproses oleh worker.

- **Scheduled Task**
  - Operasi yang berjalan berdasarkan jadwal.

- **Long-running Worker**
  - Worker yang terus berjalan untuk memproses queue.

## 8. Client yang Dapat Menggunakan Backend

Backend Laravel dirancang agar dapat digunakan oleh:

- Web application.
- Mobile application.
- Internal application.
- Third-party integration.
- AI agent.
- Automation service.
- Service/API lain.

## 9. Target Arsitektur Akhir

```text
                    ┌──────────────────────┐
                    │    Client Layer      │
                    │                      │
                    │ Web │ Mobile │ AI    │
                    └──────────┬───────────┘
                               │
                               ▼
                    ┌──────────────────────┐
                    │    Laravel API       │
                    │                      │
                    │ Auth                 │
                    │ Authorization        │
                    │ API Resources        │
                    │ Validation            │
                    └──────────┬───────────┘
                               │
                               ▼
                     ┌──────────────────────┐
                     │    Core Layer        │
                     │  (cross-cutting)     │
                     │                      │
                     │ SettingService       │
                     │ ActivityLogService   │
                     │ Auth / Authorization │
                     └──────────┬───────────┘
                                │
                     ┌──────────▼───────────┐
                     │  Module Layer        │
                     │  (business logic)    │
                     │                      │
                     │  ┌──────────────┐    │
                     │  │InvoiceModule │    │
                     │  │ InvoiceSvc   │    │
                     │  │ Model/Routes │    │
                     │  └──────────────┘    │
                     │  ┌──────────────┐    │
                     │  │ProjectModule │    │
                     │  │ ProjectSvc   │    │
                     │  └──────────────┘    │
                     │  ...                 │
                     └──────────┬───────────┘
                                │
                 ┌──────────────┼──────────────┐
                 ▼              ▼              ▼
            Database          Redis        External APIs
                 │              │
                 │              ▼
                 │          Queue/Worker
                 │
                 ▼
         Existing / New Data
```

> Business/service layer kini dipisah menjadi:
> - **Core Layer** — layanan lintas modul (auth, settings, activity log, files, meta).
> - **Module Layer** — masing-masing modul membungkus service bisnis, model, routes, jobs-nya sendiri.

## 10. Prinsip Utama Proyek

> **PerfexCRM menjadi sumber business logic legacy, sedangkan Laravel menjadi target backend baru yang modular, API-first, dan tidak bergantung pada frontend.**

- Migrasi dilakukan bertahap.
- Business logic dipisahkan dari presentation layer.
- API menjadi interface utama.
- Authentication dan authorization terpusat.
- Proses berat menggunakan Queue/Worker.
- Sistem dirancang agar dapat digunakan oleh banyak jenis client.
- Komponen Laravel digunakan sesuai kebutuhan; tidak semuanya harus diaktifkan sejak tahap pertama.

## 11. Tahap Berikutnya

Tahapan teknis berikutnya dapat diarahkan pada:

1. Audit struktur PerfexCRM existing. *(done — lihat `identifikasi-core-perfex.md`)*
2. Identifikasi seluruh business logic dan dependency.
3. Mapping module PerfexCRM ke Laravel. *(kelompok modul — lihat `identifikasi-core-perfex.md`)*
4. Menentukan database strategy.
5. Membuat Laravel API foundation.
6. Implementasi authentication dan authorization.
7. Membuat architecture untuk service/domain layer. *(keputusan: service di level module)*
8. Memindahkan modul Perfex satu per satu.
9. Membuat API contract.
10. Membuat frontend/client baru.
11. Menjalankan old dan new system secara paralel.
12. Melakukan cut-over secara bertahap.
13. Menghapus dependency PerfexCRM setelah seluruh business logic berhasil dimigrasikan.

> **Referensi dokumen analisis:**
> - `identifikasi-core-perfex.md` — modul core yang jadi module (Kelompok 1–6).
> - `analisis-helper-perfex.md` — keputusan adopsi 45 helper PerfexCRM
>   (ADOP / ADAPT / SKIP / NATIVE) untuk backend API-only.
