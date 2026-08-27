# DataTables — `render_datatable()` (PHP) & `initDataTable()` (JS) → Implementasi di Laravel API-Only

Dokumen ini mempelajari pola **server-side DataTable** PerfexCRM dari dua sisi:
- **PHP side**: `render_datatable()` (render heading) + `data_tables_init()` (server-side:
  search/sort/paginate/filter) + table view (`views/admin/tables/*.php`) yang membangun SQL.
- **JS side**: `initDataTable()` (main.js) yang mengirim request AJAX POST & render DataTables.

Lalu menurunkan bagaimana **backend Laravel API-only** harus menyediakan endpoint list yang
memenuhi kebutuhan frontend/client tanpa terikat DataTables.

---

## 1. Alur Lengkap Server-Side DataTable di Perfex

```
Frontend (DataTables)                       Backend (Perfex)
---------------------                       ----------------
1. render_datatable(thead)  <──────────────  PHP render head (table_html.php → helper)
2. initDataTable(selector, url,             JS: inisialisasi DataTables
      notSearchable, notSortable,
      fnServerParams, defaultOrder)
   └─ ajax POST url (csrftoken + params) ─→ controllers/Estimates::table()
                                    │            └─ app->get_table_data('estimates')
                                    │                 └─ include views/admin/tables/estimates.php
                                    │                      └─ data_tables_init(...)
                                    │                           • Paging  (start,length)
                                    │                           • Ordering(order[][column,dir])
                                    │                           • Filtering (search[value])
                                    │                           • + custom $where/$filter
                                    │                           • SQL_CALC_FOUND_ROWS … LIMIT
                                    │                           • FOUND_ROWS() + COUNT(*)
                                    ▼
   └─ render rows JSON  <──────────────  { draw, iTotalRecords, iTotalDisplayRecords, aaData[] }
```

---

## 2. PHP Side

### 2.1 `render_datatable($headings, $class, ...)` — `helpers/datatables_helper.php:299`
Hanya **render `<thead>`** (heading) + class tabel. Tidak ada data.
```php
function render_datatable($headings=[], $class='', $additional_classes=[''], $table_attributes=[]) {
    // membangun <table class="table-$class ..."> <thead>...</thead> <tbody></tbody>
}
```
Dipanggil dari `views/admin/estimates/table_html.php`, yang menyusun daftar kolom
(mis. number, amount, total_tax, year, client, project, tags, date, expirydate, reference,
status) + kolom custom field.

### 2.2 `data_tables_init($aColumns, $sIndexColumn, $sTable, $join, $where, ...)` — `datatables_helper.php:16`
Satu fungsi generik untuk mengubah **POST DataTables** jadi **SQL**:

| Input POST | Jadi SQL |
|------------|----------|
| `start`, `length` | `LIMIT start, length` |
| `order[*][column]`,`dir` | `ORDER BY <col> <dir>` (sorted: null last, whitelist `ASC/DESC`) |
| `search[value]` | `WHERE (convert(col) LIKE '%..%' OR ...)` |
| `columns[*][searchable]` | kontrol kolom mana yang ikut search |
| `aColumns[$i]` (array kolom SQL) | dipakai utk select + mapping kolom |

**Output (kontrak DataTables klasik):**
```php
$output = [
    'draw'                 => intval($_POST['draw']),
    'iTotalRecords'        => $iTotal,            // total sebelum filter
    'iTotalDisplayRecords' => $iFilteredTotal,    // total setelah filter
    'aaData'               => [],                 // baris (disi di views/admin/tables/estimates.php)
];
```
Query pakai `SQL_CALC_FOUND_ROWS` + `FOUND_ROWS()` + `COUNT(*)` untuk total.

### 2.3 Table view `views/admin/tables/estimates.php`
Mendefinisikan:
- **`$aColumns`** — daftar kolom (berisi ekspresi SQL, subquery tags, dsb).
- **`$join`** — `LEFT JOIN clients/currencies/projects/customfieldsvalues`.
- **`$filter`/`$where`** — filter status, sale_agent, tahun, not_sent, invoiced; **+ scopekeperawatan**: `if(!has_permission('estimates','','view')) { get_estimates_where_sql_for_staff(...) }` (important: row-level staff scope).
- **`$additionalSelect`** — kolom ekstra (id, clientid, invoiceid, currency_name, hash).
- Membangun `$row[]` untuk setiap baris termasuk **link/HTML** (mis. nomor → `init_estimate`),
  status, tags, custom fields.
- `DT_RowClass` → class baris.

> **Penting:** Sel data berisi **HTML** (link). Backend Perfex menggabungkan data + presentasi.

---

## 3. JS Side — `initDataTable()` (`assets/js/main.js:2699`)

```js
function initDataTable(selector, url, notsearchable, notsortable, fnserverparams, defaultorder) {
    // ...default order dari attr data-default-order
    // length options: [10,25,50,100] + tables_pagination_limit + -1(All)
    var dtSettings = {
        serverSide: true,
        processing: true,
        searchDelay: 750,
        bDeferRender: true,
        autoWidth: false,
        pageLength: app.options.tables_pagination_limit,
        columnDefs: [
            { searchable:false, targets: notsearchable },
            { sortable:false,   targets: notsortable },
        ],
        order: defaultorder,      // mis. [[0,'asc']]
        ajax: {
            url, type:'POST',
            data: function(d){
                d[csrf] = ...;                       // CSRF token
                for (key in fnserverparams)          // custom filter (status, year, sale_agent)
                    d[key] = $(fnserverparams[key]).val();
                d['last_order_identifier'] = ...;    // persist last order (opsional)
            }
        },
    };
    table = table.dataTable(dtSettings);

    // hidden columns dari th.not_visible → tableApi.columns(i).visible(false)
    // custom column visibility (toggleable, save ke staff/save_hidden_table_columns)
    // preXhr: abort request lama (anti double)
}
```

Key points:
- **`serverSide: true`** → semua paging/search/sort lewat server.
- **`fnserverparams`** = array selector (e.g. `[".estimates-filter"]`) yang nilainya dikirim
  sebagai `$_POST` tambahan → dipakai di table view utk filter status/tahun/agent.
- **CSRF** disisipkan tiap request.
- **column visibility** (`th.not_visible`, tabel `customizable-table`) → `columns().visible(false)`.
- `preXhr` abort request ganda.

---

## 4. Implementasi di Project Laravel API-Only

Karena project kita **API-only**, kita TIDAK menggunakan DataTables/DataTablesJS di backend,
dan TIDAK mengirim HTML dari server. Tapi **kontrak & capability** yang dipenuhi DataTables
justru jadi **spesifikasi endpoint list** yang harus disediakan API kita.

### 4.1 Endpoint list generik (session Query Params, bukan POST DataTables)
Frontend apapun (DataTables, AG-Grid, TanStack Table, mobile) harus bisa pakai satu endpoint.

```
GET /api/v1/estimates
    ?include=items,status
    &fields[]=number,id,total,currency          # projection (ganti "hidden columns")
    &search=foo                                 # global search
    &filter[status]=draft,accepted              # multi-filter (ganti fnserverparams)
    &filter[year]=2026
    &sort=-created_at,number                    # ordering
    &per_page=25  &page=2                       # pagination (ganti start/length)
    &cursor=...                                 # (opsional) cursor pagination utk skala besar
```

### 4.2 Kontrak response (JSON, standard Resource Collection)

```jsonc
{
  "data": [ /* array resource (di-proyeksi) */ ],
  "links": { "first": "...", "last": "...", "next": "...", "prev": "..." },
  "meta": {
    "current_page": 2, "from": 26, "last_page": 40,
    "per_page": 25, "to": 50,
    "total": 1000,          // = iTotalRecords (sebelum filter)
    "total_filtered": 500   // opsional, = iTotalDisplayRecords
  }
}
```
- `data` = **resource JSON**, bukan HTML → frontend merender link sendiri.
- Separuh `total` (semua) vs `total_filtered` (hasil filter) dipertahankan agar frontend
  bisa nampilkan "X dari Y".
- Lambang `draw` DataTables tidak perlu; anti-CSRF (API memakai token Bearer).

### 4.3 Fitur yang "harus" didukung (diterjemahkan dari DataTables)

| Kemampuan DataTables Perfex | SDKatait | API Laravel |
|-----------------------------|-----------|-------------|
| `serverSide` paging/sort/search | `page/per_page`, `sort`, `search`, `filter` |
| kolom hidden (`th.not_visible`) | **`fields[]` projection** (client pilih kolom yg diambil) |
| `fnserverparams` custom filter | **`filter[...]` query param** (whitelist di controller) |
| default order / last order | `sort` + opsional simpan preferensi per-user (Spatie setting) |
| searchable per kolom | `searchable[]`/ignore → mapping ke kolom yang aman |
| sortable per kolom | whitelist kolom yang bisa di-sort di controller (hindari inject) |
| custom fields kolom | join + select projectable (customfield metadata) |
| CSRF+abort | Bearer token; **idempotent GET** (cacheable, no double-abort) |
| Dapat di-extend (custom columns/row) | **Dynamic API Resource / composition** |

### 4.4 Keamanan (penting di API)
- **Jangan pernah** mengambil `sort`/`filter` langsung sebagai kolom SQL (raw SQL injection).
  Gunakan **whitelist peta kolom** di controller/service:
  ```php
  $sortable = ['number','total','date','status','company' => 'clients.company'];
  // hanya terima key yang ada di whitelist
  ```
- **Search**: gunakan Eloquent `whereLike` dengan kolom whitelist; untuk custom field gunakan
  relasi, bukan concat SQL bebas.
- **Scope per-role/tenant**: di project multi-tenant, tiap endpoint list WAJIB menerapkan
  global scope tenant + row-level permission (padanan `get_estimates_where_sql_for_staff`)
  — jangan hanya mengandalkan `has_permission` global.

### 4.5 Rekomendasi pohon query service di Laravel

```text
app/Http/Controllers/Api/V1/EstimateController.php
  └─ index(Request)
       └─ EstimateQuery (Spacious/query builder pattern)
            ├─ allowedFilters([...])     # whitelist filter [+ tenant scope]
            ├─ allowedSorts([...])       # whitelist sort
            ├─ allowedIncludes([...])    # eager loading relasi
            ├─ defaultSort('-created_at')
            └─ ->paginate() 
EstimateResource.php   # proyeksi kolom (ganti piihan field/hidden columns)
```

Bisa memakai **`spatie/laravel-query-builder`** (allowedFilters, allowedSorts,
allowedIncludes, paginate) — sangat cocok sebagai pengganti `data_tables_init()`.

---

## 5. Ringkasan Pemetaan

| Konsep Perfex | File | Padanan di Laravel API |
|---------------|------|------------------------|
| `render_datatable()` (thead) | `datatables_helper.php:299` | Frontend-only (API return data, bukan head) |
| `data_tables_init()` (SSR SQL) | `datatables_helper.php:16` | **`spatie/laravel-query-builder`** + service query |
| Table view (`$aColumns`/`$join`/`$where`) | `views/admin/tables/estimates.php` | Controller query + eager loading |
| `get_estimates_where_sql_for_staff()` | models | **Global scope tenant + row-level permission** |
| `initDataTable()` (JS) | `main.js:2699` | Frontend (bebas library) |
| `fnserverparams` filter | main.js ajax.data | `filter[...]` query param (whitelist) |
| `iTotalRecords/iTotalDisplayRecords` | output | `meta.total` + `meta.total_filtered` |
| response `aaData` (HTML sel) | tables/*.php | `data` = JSON resource (tanpa HTML) |

---

## 6. Kesimpulan

- **Jangan** bawa DataTablesJS / `render_datatable` / POST `aaData` ke backend baru.
- API jadi penyedia **endpoint list re-usable**: paging, sort, search, filter, include, projection —
  yang dipakai beragam client (DataTables, TanStack, mobile, dsb).
- Implementasi utama di Laravel: **`spatie/laravel-query-builder`** (atau pattern query serupa)
  + **Resource Collection** + **whitelist + tenant/row scope**.
- Trust boundary: API memutuskan kolom yang aman di-sort/filter/search; client hanya kirim
  intent (query param), bukan SQL/HTML.

---

*Dokumen dibuat berdasarkan reverse-engineering `datatables_helper.php`, `initDataTable()` di
main.js, dan `views/admin/tables/estimates.php`, 27 Agustus 2026.*
