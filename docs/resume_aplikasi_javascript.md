# Resume — Arsitektur Aplikasi JavaScript

## 1. Tujuan

- Membuat frontend modern yang terpisah dari backend Laravel.
- Web dan Mobile menggunakan backend API yang sama.
- Business logic tetap berada di Laravel.
- JavaScript bertanggung jawab atas UI, interaksi, client state, dan konsumsi API.
- Arsitektur dibuat modular dan dapat dikembangkan jangka panjang.

## 2. Stack Utama

| Layer | Teknologi |
|---|---|
| Language | TypeScript |
| Web | Next.js |
| Mobile | Expo / React Native |
| Monorepo | Turborepo |
| UI | gluestack-ui |
| Styling | NativeWind |
| Server State | TanStack Query |
| HTTP Client | Axios |
| Client State | Zustand |
| Backend | Laravel API-only |
| Authentication | Laravel Sanctum + Fortify |
| Authorization | Spatie Laravel Permission |
| Queue | Laravel Queue |
| Worker Monitoring | Laravel Horizon |
| Realtime | Laravel Reverb |
| Scheduler | Laravel Scheduler |
| ORM | Eloquent |
| Cache / Queue Backend | Redis |

## 3. Arsitektur

```text
Web (Next.js) ─────┐
                   ├── API Client ── Laravel API ── Business Logic ── Database
Mobile (Expo) ─────┘                       │
                                           ├── Redis
                                           └── Queue / Worker
```

## 4. Monorepo

Struktur dasar:

```text
project/
├── apps/
│   ├── web/
│   └── mobile/
│
├── packages/
│   ├── api-client/
│   ├── ui/
│   ├── types/
│   ├── validation/
│   ├── config/
│   └── utils/
│
├── turbo.json
├── package.json
└── tsconfig.json
```

- Turborepo mengelola Web dan Mobile dalam satu repository.
- Shared packages digunakan untuk code yang memang dapat digunakan lintas platform.
- TypeScript digunakan di seluruh aplikasi.

## 5. Web

### Next.js

- Framework utama aplikasi Web.
- Routing dan application structure.
- Integrasi langsung dengan Laravel API.
- Web dapat dideploy secara independen dari backend.

## 6. Mobile

### Expo / React Native

- Framework aplikasi Mobile.
- Target Android dan iOS.
- Berbagi ecosystem React dan TypeScript dengan Web.
- Build dan deployment Mobile dilakukan terpisah dari Web.

## 7. UI

### gluestack-ui

- Component system.
- Reusable UI components.
- Design system yang konsisten.

### NativeWind

- Utility-first styling.
- Digunakan terutama untuk React Native.
- Menjaga pendekatan styling tetap konsisten.

## 8. Data Management

### TanStack Query

Digunakan untuk **server state**:

- Fetching API.
- Caching.
- Refetching.
- Mutation.
- Loading state.
- Error state.
- Sinkronisasi data dengan Laravel.

### Zustand

Digunakan untuk **client state**:

- UI state.
- Preferences.
- Modal/dialog state.
- State lokal aplikasi.
- State client yang tidak berasal dari database/backend.

Prinsip:

```text
Laravel API
     │
     ▼
TanStack Query
     ├── Server Data
     ├── Cache
     └── Mutation

Zustand
     ├── Client State
     └── UI State
```

## 9. API Client

### Axios

- HTTP client utama.
- Centralized API configuration.
- Authentication interceptor.
- Error handling terpusat.
- Standardisasi request/response.

Frontend **tidak mengakses database secara langsung**.

```text
Web / Mobile
     ↓
Axios / API Client
     ↓
Laravel API
     ↓
Business Logic
     ↓
Database
```

## 10. Authentication & Authorization

- Authentication ditangani Laravel.
- Laravel Sanctum untuk API authentication.
- Laravel Fortify untuk fitur authentication.
- Spatie Laravel Permission untuk role dan permission.
- Laravel Policies/Gates untuk authorization resource/action.
- Frontend hanya menyesuaikan UI berdasarkan capability.
- Backend tetap menjadi sumber kebenaran security.

## 11. Feature-based Architecture

Frontend dikembangkan berdasarkan feature/module:

```text
features/
├── auth/
├── users/
├── customers/
├── projects/
├── invoices/
├── documents/
└── settings/
```

Setiap feature dapat memiliki:

```text
feature/
├── components/
├── hooks/
├── queries/
├── mutations/
├── types/
└── utils/
```

Tujuannya agar module mudah dikembangkan, diuji, dan dipindahkan.

## 12. Shared Packages

### api-client
- Axios configuration.
- API service.
- Authentication handling.

### types
- Shared TypeScript types.
- Entity types.
- API response types.
- DTO types.

### ui
- Reusable components.
- Design system.

### validation
- Shared validation schema bila diperlukan.

### utils
- Utility lintas aplikasi.

## 13. Form & Validation

- Client melakukan validation untuk UX.
- Laravel melakukan validation final.
- Client validation bukan security boundary.
- API contract harus konsisten antara frontend dan backend.

## 14. Error Handling

```text
Laravel API
     ↓
Axios Interceptor
     ├── 401 → Authentication handling
     ├── 403 → Permission error
     ├── 404 → Resource not found
     ├── 422 → Validation error
     ├── 429 → Rate limit
     └── 5xx → Server error
```

Error handling dibuat terpusat agar setiap feature tidak perlu membuat mekanisme berbeda.

## 15. Background Job

Proses berat tidak dijalankan dengan menunggu HTTP request terlalu lama.

```text
Frontend
   ↓
Laravel API
   ↓
Create Job
   ↓
Queue
   ↓
Worker
   ↓
Result
```

Frontend dapat:

- Mendapatkan Job ID.
- Mengecek status job.
- Menggunakan TanStack Query untuk polling.
- Menggunakan Reverb jika membutuhkan realtime.

## 16. Realtime

Laravel Reverb dapat digunakan untuk:

- Notification realtime.
- Dashboard realtime.
- Status background job.
- Progress processing.
- Perubahan data tertentu.

Realtime bukan dependency wajib pada tahap pertama.

## 17. Prinsip Deployment

```text
Git Repository
      │
      ├── Web → Next.js deployment
      │
      ├── Mobile → Expo build
      │
      └── Backend → Laravel API
```

- Web dapat dideploy independen.
- Mobile dapat dibuild independen.
- Laravel dapat dideploy independen.
- API contract menjadi penghubung semua client.

## 18. Prinsip Utama

- **Backend owns business logic.**
- **Frontend owns presentation and interaction.**
- **API owns communication contract.**
- **TanStack Query owns server state.**
- **Zustand owns client state.**
- **TypeScript provides type safety.**
- **Turborepo manages the monorepo.**
- **Next.js handles Web.**
- **Expo handles Mobile.**

## 19. Target Akhir

```text
                         ┌──────────────┐
                         │    Web App   │
                         │   Next.js    │
                         └──────┬───────┘
                                │
                         ┌──────▼───────┐
                         │  Mobile App  │
                         │     Expo     │
                         └──────┬───────┘
                                │
                         ┌──────▼───────┐
                         │ API Client   │
                         │ Axios +      │
                         │ TanStack     │
                         └──────┬───────┘
                                │
                         ┌──────▼───────┐
                         │   Laravel    │
                         │   API-only   │
                         └──────┬───────┘
                                │
                  ┌─────────────┼─────────────┐
                  ▼             ▼             ▼
              Database        Redis       External APIs
                                │
                                ▼
                              Queue
                                │
                              Worker
```

## 20. Kesimpulan

Aplikasi JavaScript akan menjadi **frontend ecosystem terpisah dari Laravel backend**. Next.js digunakan untuk Web, Expo untuk Mobile, dan keduanya dikelola dalam Turborepo dengan TypeScript. TanStack Query menangani server state, Zustand menangani client state, Axios menangani komunikasi API, dan shared packages menjaga code tetap reusable.

Target akhirnya adalah Web, Mobile, AI agent, automation, dan client lainnya dapat menggunakan **business logic Laravel yang sama tanpa duplikasi logic di masing-masing frontend**.
