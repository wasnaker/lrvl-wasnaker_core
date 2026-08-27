# Domain & Multi-Tenancy — PerfexCRM (Single) → Platform (Many-to-Many)

Dokumen ini menangkap **perbedaan fundamental domain** antara PerfexCRM dan project baru,
yang memengaruhi hampir semua keputusan arsitektur sebelumnya (helper/library/hook/module)
dan membuat model data harus dirancang ulang sebagai **many-to-many** multi-tenant.

---

## 1. Perbedaan Konseptual Inti

| Aspek | PerfexCRM (Sumber) | Project Baru (Target) |
|-------|--------------------|------------------------|
| **Model kepemilikan (tenancy)** | **Single-operator** (1 aplikasi, 1 perusahaan penyedia, melayani banyak *client*-nya sendiri) | **Multi-tenant asli** — platform/penyedia melayani banyak **pillar** |
| **Arah relasi** | **One-to-many**: 1 operator → banyak clients | **Many-to-many**: banyak tenant, banyak penjual, banyak buyer, operator = platform |
| **Entitas operator** | Perfex itu sendiri = satu "company" | Penyelenggara/platform + **banyak tenant** (setiap tenant punya 1..N org/departemen) |
| **Data isolation** | Tidak ada (semua data satu aplikasi) | Harus **terisolasi per tenant** (horizontal isolation) |
| **Struktur organisasi** | client/customer level saja | **Platform → asosiasi/penjual → kantor (provinsi/kabupaten) → customer** |
| **Aktor** | Staff (admin internal) + contacts (external customer) | Staff platform, **penjual/vendor**, **pembeli/customer**, pegawai, pejabat (pemerintah provinsi) |
| **Roles/Permissions** | Perfex: staff + contact permissions | Harus **RBAC berlapis**: global + per-tenant + per-role |

---

## 2. Tenancy yang Dibutuhkan

Project kita adalah **B2B SaaS multi-tenant** di mana operator = penyedia platform, dan
banyak entitas independen memakainya. Karena **many-to-many**, tenancy tidak sesederhana
"setiap perusahaan punya satu DB/acara" — satu tenant (mis. perusahaan penjual) bisa
berinteraksi dengan banyak counterpart lain, dan kompleksitas lintas-tenant.

### Opsi arsitektur tenancy

| Opsi | Isolasi | Dukungan lintas-tenant | Cocok? |
|------|---------|------------------------|--------|
| **A. Single DB, kolom tenant_id** | Logical (via scope/global) | Ya (join lintas tenant) | ✅ **Direkomendasikan** untuk many-to-many |
| **B. Schema-per-tenant** (PostgreSQL) | Physical schema | Sulit lintas schema | Tidak (sulit many-to-many lintas tenant) |
| **C. DB-per-tenant** | Full physical | Tidak (butuh federation) | Tidak cocok many-to-many |

Karena relasi **lintas tenant** diperlukan (penjual ↔ pembeli ↔ pemerintah), maka:
- Gunakan **single database** + **tenant context** di setiap model.
- Gunakan package/modul tenancy, mis. **`stancl/tenancy`** (domain-based) atau
  **custom Tenant Scope** dengan global scope Eloquent.
- Tenant identification via **subdomain / header / JWT claim / path prefix**.

---

## 3. Struktur Organisasi & Aktor (Berdasarkan Kebutuhan Anda)

Kebutuhan Anda menyebutkan entitas berlapis:
- **Platform / Operator** (penyedia platform = aplikasi utama)
- **Asosiasi penjual** (asosiasi)
- **Kantor pemerintah tingkat provinsi** (instansi/dinas)
- **Penjual / vendor** (ribuan)
- **Pembeli / customer** (banyak)
- **Pegawai/pejabat** (internal tiap org)

### Model hierarki organisasi (proposal)

```text
Platform (root)
├── TenantGroup / Association   (asosiasi penjual)
│   └── Organization            (perusahaan/instansi)
│       └── OrganizationUnit / Kantor  (cabang, dinas provinsi, dll)
│           └── User / Employee (pegawai)
├── Merchant / Penjual          (vendor)
│   └── Organization/Outlet
│       └── User/Sales
└── Customer / Buyer            (pembeli)
    └── Contact / Account
```

Entitas kunci:
- **Tenant** — akar kepemilikan data (dapat berupa company/org/instansi).
- **Organization** — entitas operasional (company, asosiasi, dinas).
- **OrganizationUnit / Branch** — kantor/cabang (mendukung kantor provinsi).
- **Merchant / Vendor** — penjual.
- **Customer / Buyer** — pembeli.
- **User** — manusia (dapat berafiliasi ke 1..N org/merchant/customer).
- **Contact** — kontak eksternal (seperti `contacts` di Perfex).

---

## 4. Many-to-Many Relasi Bisnis

Perfex: `client` punya `invoices/estimates/projects/tickets` (one-to-many milik client itu).

Project baru: relasi **bersilang** antar aktor:

- **Penjual ↔ Pembeli**: blok/skema, pesanan, penawaran, invoice, kontrak, pembayaran
  (pihak "seller" dan "buyer" bisa dari tenant berbeda).
- **Penjual ↔ Pemerintah (provinsi)**: pendaftaran/izin, compliance/inspeksi K3L,
  pelaporan, sertifikasi.
- **Asosiasi penjual ↔ anggotanya**: keanggotaan, standar, berita, program.
- **Instansi provinsi ↔ penjual di wilayahnya**: pengawasan, data kepatuhan.

Implikasi: **tiap record bisnis (invoice, contract, dsb) harus punya dua sisi pihak**
(contoh: `seller_org_id` + `buyer_org_id`), bukan sekadar `client_id` satu arah.

---

## 5. Implikasi terhadap Keputusan Sebelumnya

Analisis helper/library/hook/module tetap relevan untuk **business logic**, namun pemetaannya
perlu diberi lapisan **tenant-aware**:

1. **Model**: tiap Eloquent model terikat tenant serelevan (`belongsTo` tenant/org) +
   global scope menghindari kebocoran data antar tenant.
2. **Tenant service di Core**: `TenantService` / `TenantManager` (resolusi tenant saat request
   via middleware `SetCurrentTenant`) — menjadi **wajib**, bukan opsional.
3. **RBAC berlapis** (`spatie/laravel-permission`): perlu extension untuk:
   - *permission per tenant* (role sama tapi scope data berbeda),
   - *permission per organization/unit* (pejabat dinas hanya lihat wilayahnya).
4. **Module utilitas Perfex** (ActivityLog, Setting, Files):
   - Setting → bisa global (platform) ATAU per-tenant (`setting` punya `tenant_id`).
   - File → tersimpan per tenant + akses terkontrol lintas-tenant.
   - Activity log → context tenant.
5. **API contract**: path/param harus menyertakan konteks tenant; resource API dibatasi scope.
6. **Migrations/data**: skema Perfex (`tbl*`) tidak punya tenant — nanti ditambah kolom
   `tenant_id`, `org_id`, `seller/buyer` side. Ini **bukan sekadar copy**, tapi redesign relasi.

---

## 6. Arsitektur Data Target (High-level)

```text
tenants
organizations
organization_units         (kantor/cabang, provinsi)
merchants                  (penjual)
customers                  (pembeli)
users
  ├── tenant_memberships   (pivot: user <-> tenant/org + role + scope)
  ├── merchant_memberships
  └── customer_contacts

(dokumen/transaksi inti — berisi dua sisi pihak:)
sales_documents
  ├── sales_documents_line_taxes
  ├── sales_document_parties    (negotiator/party per dokumen: seller & buyer)
  ├── contracts
  ├── invoices
  ├── payments
  └── ...
projects
tickets
...
```

---

## 7. Keputusan Terbuka (perlu konfirmasi Anda)

1. **Pola tenancy**: Single-DB + tenant scope (rekomendasi) vs lain. Apakah perlu
   multi-database untuk tenant premium/wajib?
2. **Identifikasi tenant**: subdomain (`merchant.aplikasi.id`), custom domain,
   JWT claim, atau path prefix?
3. **Relasi lintas-tenant** mana yang paling kritis pertama (B2B sales? K3L/compliance?
   asosiasi? instansi provinsi?) → menentukan modul prioritas.
4. **Peran operator** pada transaksi *direct B2B*: apakah operator/platform di tengah
   (marketplace, ambil komisi) atau hanya penyedia infrastruktur (penjual & pembeli
   bertransaksi langsung)?
5. **Skala data** yang dibayangkan (ribuan tenant? ratusan ribu invoice?) → memengaruhi
   strategi indeks, cache, partisi.

---

## 8. Kesimpulan

- **PerfexCRM = single-operator (1:many)**. Project kita = **platform multi-tenant (many:many)**.
- Karena many-to-many lintas tenant, gunakan **single-DB + tenant isolation via scope**,
  bukan DB/schema per tenant.
- Data inti perlu tambahan sisi pihak ganda — bukan sekadar `client_id`.
- RBAC + File + Setting + Log harus **tenant-aware**.
- Analisis Perfex (helper/library/hook) **dipakai sebagai sumber business logic**,
  tetapi **model data & authorization harus di-redesign untuk many-to-many**.

---

## 9. Pola Single-Operator yang Ditemukan di Source (reverse-engineering)

Bagian ini mencatat **pola konkret dalam code PerfexCRM** yang membuktikan asumsi
single-operator, sebagai panduan apa yang **harus diubah** saat redesign many-to-many.

### 9.1 Identitas operator = SETTING global, bukan entitas
- Identitas "perusahaan penyedia" disimpan sebagai **option** di `tbloptions`
  (`get_option('companyname')` dipakai di banyak tempat: PDF, email, template).
- Tidak ada tabel `tenants`/`organizations`. `company` pada konteks `tblclients`
  adalah **nama perusahaan klien**, bukan identitas operator.
- → **Redesign:** operator menjadi entitas `Platform`/`Root` + `Tenant`; nama perusahaan
  operator & klien jadi field data, bukan "option global".

### 9.2 Query selalu `WHERE <owner>_id = X` — satu pemilik
- Contoh `Clients_model::get()` → `where('userid', $id)`, tanpa scope operator/tenant.
- Semua relasi (invoice/estimate/project/ticket) berpijak pada **satu client**.
- → **Redesign:** tiap transaksi butuh **dua sisi** (`seller_org_id` + `buyer_org_id`),
  dan semua query wajib **global scope tenant**.

### 9.3 RBAC = serialize array, flat, tanpa scope
- `Roles_model` menyimpan `permissions` sebagai **`serialize($permissions)`** per role,
  dan `Staff_model` menyimpan permission per-staff (serialized).
- Tidak ada konsep *permission per tenant/org/unit* — satu aplikasi, satu set data.
- → **Redesign:** ganti dengan **`spatie/laravel-permission` + TenantModel + Organization scope**
  agar role bisa berbeda-beda per tenant dan per unit (mis. dinas provinsi hanya lihat
  datanya sendiri).

### 9.4 Module/hook adalah "plugin internal", bukan tenancy
- `App_modules` / `App_Module_Migration` mengelola aktif/nonaktif plugin dalam **satu**
  aplikasi — untuk menambah fitur, bukan untuk isolasi data antar tenant.
- → **Redesign:** modular (module) tetap dipakai untuk fitur, tetapi penambahannya
  terpisah dari mekanisme tenancy/isolasi data.

### 9.5 Ratings sederhana & global setting
- Semua `get_option()` bersifat global (satu nilai untuk seluruh aplikasi).
- → **Redesign:** `SettingService` perlu level: **global (platform)** vs **per-tenant** vs
  **per-org** — bukan satu `tbloptions` flat.

### 9.6 Kontak = eksternal, staff = internal
- `contacts` = kontak milik client (eksternal); `staff` = karyawan operator (internal).
- Ini **tidak cukup** untuk many-to-many karena tiap tenant (penjual/asosiasi/instansi)
  punya staf internal sendiri.
- → **Redesign:** satukan ke `users` + `memberships` (pivot ke tenant/org/merchant +
  role + scope), bukan 2 tabel terpisah.

---

## 10. Ringkasan "Pola yang Harus Diubah"

| Pola Perfex (single-operator) | Pola Project (many-to-many) |
|-------------------------------|------------------------------|
| `tbloptions` = identitas operator | `platforms` / `tenants` = entitas |
| 1 pemilik per record (`userid`) | 2+ pihak (`seller_org_id`, `buyer_org_id`) |
| query tanpa scope tenant | global scope tenant wajib |
| RBAC flat (serialize) | `spatie/permission` + scope tenant/org |
| `staff` vs `contacts` terpisah | `users` + pivot `memberships` |
| setting global | setting global + per-tenant + per-org |
| module = fitur internal | module = fitur + tetap ada isolasi data |

---

## 11. Kesimpulan

- PerfexCRM terbukti **single-operator** dari sisi model data, RBAC, dan setting.
- Project kita harus **merombak** ketiga hal tersebut menjadi **many-to-many multi-tenant**.
- Business logic (helper/library/hook) **tetap berguna sebagai sumber logika**,
  tetapi **lapisan data & otorisasi direncanakan ulang total**.
- Modularity (module) bernilai tetap, tetapi **bukan pengganti tenancy** — keduanya perlu
  digabung: tenancy untuk isolasi data, module untuk organisasi fitur.

---

*Dokumen disusun sebagai konsep domain, 27 Agustus 2026. Bagian 9–10 berdasarkan pembacaan
langsung source PerfexCRM (`models/`, `core/`, `libraries/`, `helpers/`). Beberapa keputusan
terbuka perlu konfirmasi sebelum implementasi.*
