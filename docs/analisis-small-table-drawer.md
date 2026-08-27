# Small Table / Detail Panel Drawer — PerfexCRM (Reverse-Engineering)

Telusuri mendalam fitur yang Anda anggap paling menarik: **"small table"** — yaitu saat URL
ber-`#id`, sebagian kolom tabel disembunyikan, lalu muncul **panel tab di kanan** yang
**lazyload** isi detailnya. Contoh nyata: **Estimates**.

> **Catatan terminologi:** Yang Anda sebut `load_small_table()` di source sebenarnya bernama
> **`load_small_table_item()`** (+ `toggle_small_view()`, `small_table_full_view()`).
> Konsep "small table" = membuka ringkasan + detail panel di sisi kanan tanpa keluar dari
> halaman list.

---

## 1. Ringkasan Alur (End-to-End)

```
User klik nomor Estimate  OR  buka URL /estimates/list_estimates#<id>
        │
        ▼
onclick="init_estimate(id)"           [views/admin/tables/estimates.php:130]
        │
        ▼
load_small_table_item(id, '#estimate','estimateid',
        'estimates/get_estimate_data_ajax','.table-estimates')   [main.js:5813]
        │
        │  (a) ID diambil: arg, input[name=estimateid], atau URL hash (#id)
        │  (b) do_hash_helper(id)  → set window.location.hash = id  (deep-link)
        │  (c) jika belum: toggle_small_view(table, selector)
        │  (d) $(selector).load(admin_url + 'estimates/get_estimate_data_ajax/ID')
        │         ▲  → AJAX lazyload ke panel kanan #estimate
        ▼
toggle_small_view('.table-estimates','#estimate')   [main.js:3156]
   • body.small-table
   • #small-table : col-md-12 → col-md-5   (tabel menyusut ke kiri)
   • _table.columns(hidden_columns).visible(false)  (sembunyikan kolom samping)
   • #estimate .hide dihapus → panel kanan tampil (col-md-7)
        │
        ▼
get_estimate_data_ajax($id)  [controllers/admin/Estimates.php:233]
   • cek permission
   • muat estimate + activity + notes + status
   • render estimate_preview_template.php  (tab: estimate/tasks/activity/reminders/notes/emails/views)
        │
        ▼
[opsional] small_table_full_view()  [main.js:7040]  (ikon expand di preview:74)
   • #small-table.hide
   • .small-table-right-col : col-md-7 → col-md-12  (panel full = detail-focused)
```

---

## 2. Struktur HTML (Layout Dua Panel) — `views/admin/estimates/list_template.php`

```html
<div class="col-md-12" id="small-table">
  <div class="panel_s"><div class="panel-body">
    <?php echo form_hidden('estimateid',$estimateid); ?>   <!-- id dari URL di-copy ke sini -->
    <?php $this->load->view('admin/estimates/table_html'); ?>  <!-- DataTable -->
  </div></div>
</div>
<div class="col-md-7 small-table-right-col">
  <div id="estimate" class="hide"></div>   <!-- panel kanan, awalnya hidden -->
</div>
```

- **`#small-table`** = wadah tabel (default `col-md-12`; saat small-mode jadi `col-md-5`).
- **`.small-table-right-col`** = kolom kanan (default `col-md-7`, tapi isi di-`hide`).
- **`#estimate`** = target `.load()` — dipenuhi partial preview secara ajax.

---

## 3. JavaScript Kunci (`assets/js/main.js`)

### 3.1 `init_estimate(id)` → per-modul wrapper  (baris 5852-5864)
```js
function init_invoice(id) { load_small_table_item(id,'#invoice','invoiceid',
    'invoices/get_invoice_data_ajax','.table-invoices'); }
function init_estimate(id){ load_small_table_item(id,'#estimate','estimateid',
    'estimates/get_estimate_data_ajax','.table-estimates'); }
// proposal → #proposal / .table-proposals
// credit note → #credit_note / .table-credit-notes
// expense → #expense / .table-expenses
```
Semua modul sales memakai **pola yang sama** (parameter: selector, input_name, url ajax, selector tabel).

### 3.2 `load_small_table_item()`  (baris 5813-5840) — resolver ID + lazyload
```js
function load_small_table_item(id, selector, input_name, url, table) {
    var _tmpID = $('input[name="'+input_name+'"]').val();
    // (a) jika ada id di input & tidak ada hash → pakai input, kosongkan input
    if (_tmpID !== '' && !window.location.hash) {
        id = _tmpID;
        $('input[name="'+input_name+'"]').val('');
    } else {
        // (b) jika ada hash & id tidak diberikan → id = hash (#12345)
        if (window.location.hash && !id) {
            id = window.location.hash.substring(1);
        }
    }
    if (typeof(id)=='undefined' || id==='') return;
    destroy_dynamic_scripts_in_element($(selector));
    if (!$("body").hasClass('small-table')) {
        toggle_small_view(table, selector);   // aktifkan mode small-table pertama kali
    }
    $('input[name="'+input_name+'"]').val(id);
    do_hash_helper(id);                       // set URL #id
    $(selector).load(admin_url + url + '/' + id);   // *** LAZYLOAD ***
    $('html, body').animate({ scrollTop: $(selector).offset().top }, 600);
}
```
- **Sumber ID diprioritaskan:** arg fungsi → input tersembunyi → **URL hash (#id)**.
- **Lazyload:** `.load(admin_url + 'estimates/get_estimate_data_ajax/' + id)` memuat
  partial detail ke panel kanan **tanpa refresh halaman**.
- `do_hash_helper(id)` → deep-link: URL jadi `...#<id>`, bisa di-share/reload.

### 3.3 `do_hash_helper(hash)`  (baris 3339-3349) — URL state
```js
function do_hash_helper(hash) {
    if (typeof(history.pushState)!="undefined") {
        var url = window.location.href;
        history.pushState(obj,'',obj.Url);   // dorong state baru
        window.location.hash = hash;          // set #id
    }
}
```
Mendukung navigasi back/forward antar item lewat hash history.

### 3.4 `toggle_small_view(table, main_data)`  (baris 3155-3178) — layout + hidden columns
```js
function toggle_small_view(table, main_data) {
    $("body").toggleClass('small-table');
    var tablewrap = $('#small-table');
    if (tablewrap.length===0) return;
    var _visible = false;
    if (tablewrap.hasClass('col-md-5')) {
        tablewrap.removeClass('col-md-5').addClass('col-md-12');  // restore full
        _visible = true;
        // ikon toggle → panah kiri
    } else {
        tablewrap.addClass('col-md-5').removeClass('col-md-12');  // shrink
        _visible = false;
        // ikon toggle → panah kanan
    }
    var _table = $(table).DataTable();
    _table.columns(hidden_columns).visible(_visible, false);  // ******** HIDDEN COLUMNS ********
    _table.columns.adjust();
    $(main_data).toggleClass('hide');   // tampilkan panel kanan
    $(window).trigger('resize');
}
```
- **`hidden_columns`** (variabel global) didefinisikan per halaman, mis. estimates:
  `var hidden_columns = [2,5,6,8,9];` (di `manage.php:11`).
- Saat small-view aktif, kolom-kolom itu `visible(false)` → tabel ringkas, panel kanan muncul.
- Ini mekanisme "sebagian tabel di-hidden + muncul tab panel kanan".

### 3.5 `small_table_full_view()`  (baris 7040-7044) — expand detail panel (full)
```js
function small_table_full_view() {
    $('#small-table').toggleClass('hide');                       // sembunyikan tabel
    $('.small-table-right-col').toggleClass('col-md-12 col-md-7'); // panel → full width
    $(window).trigger('resize');
}
```
Dipanggil dari ikon **expand** di `estimate_preview_template.php:74`:
```html
<li class="tab-separator toggle_view">
  <a href="#" onclick="small_table_full_view(); return false;"><i class="fa fa-expand"></i></a>
</li>
```
Mengubah panel kanan menjadi full-width (mode "fokus detail", tabel disembunyikan).

### 3.6 Pemicu on-load via hash (#id)
- `manage.php` memanggil `init_estimate()` saat DOM ready.
- Karena tanpa argumen, `load_small_table_item` membaca `window.location.hash` (#id) →
  membuka drawer langsung saat akses URL ber-`#id`. **Ini perilaku yang Anda maksud.**
- Item yang sama juga dibuka ulang dari proses lain (e.g. lampiran/attachment mengarah ke
  `init_estimate(response.rel_id)`, notifikasi `_data.rel_id` di baris 7501, dst).

---

## 4. Server-Side (`Estimates.php` controller)

### 4.1 `get_estimate_data_ajax($id)`  (baris 233-283)
- **Permission check** dulu (view / view_own / allow_staff_view_estimates_assigned).
- Muat estimate (`estimates_model->get($id)`) + cek `user_can_view_estimate`.
- Muat data pendukung: invoice terkait, `mail preview`, **activity**, **notes count**,
  staff members, statuses.
- **Render `estimate_preview_template.php`** (partial dengan tab-tab) → HTML hasil `.load()`.

### 4.2 `list_estimates($id)`  (baris 22-58)
- Menerima `$id` dari URL `/estimates/list_estimates/<id>`.
- Mengisi `$data['estimateid']` → dipakai `form_hidden('estimateid', $estimateid)`
  (jalur alternatif jika bukan lewat hash).
- Bisa render mode **table** atau mode **pipeline** (kanban) tergantung session.

---

## 5. Preview Template (`estimate_preview_template.php`)

Panel kanan berisi **tab horizontal** (`preview-tabs-top`):
- **estimate** (detail: infonya, item table, subtotal/tax/total, attachments, notes, terms)
- **tasks** (`init_rel_tasks_table` — related tasks, lazyload saat tab aktif)
- **activity** (activity feed/log)
- **reminders** (datatable + form reminder)
- **notes** (form + list catatan)
- **emails_tracking** (tracking email yang dikirim)
- **views** (tracking siapa/ip view)

Setiap tab berisi konten "berat" yang baru dimuat saat dipilih → **lazyload bertahap**,
konsisten dengan prinsip menghindari render berat sekaligus.

---

## 6. CSS / Styling (`assets/css/style.css`)

```css
#small-table .table>tbody>tr>td a { font-size: 13.6px; }

@media (min-width:801px) {
    body.small-table .small-table-right-col.col-md-7 { padding-left: 0; }
}
```
- **Tidak ada library drawer** khusus — murni **Bootstrap grid** (`col-md-5`/`col-md-7`/`col-md-12`)
  + toggling class.
- Tabel DataTables diinisialisasi lewat `initDataTable` dengan **server-side (SSR)** render
  (`views/admin/tables/estimates.php`), sehingga sel "Number" berisi link `onclick=init_estimate`.

---

## 7. Relevansi & Rekomendasi untuk Project (Laravel API-Only)

Karena project kita **API-only** (frontend/web terpisah), **layout HTML/DataTable/CSS tidak
langsung diadopsi**. Namun **konsep/rutin UX-nya** sangat berharga dan bisa diduplikasi oleh
frontend/client mana pun yang memakai API kita:

### 7.1 Konsep yang diadopsi (didokumentasikan ke API contract)
| Konsep Perfex | Cara kita sediakan di API |
|---------------|---------------------------|
| **List ringkas + detail panel** ("small table") | API pisah: `GET ...?list=summary` (kolom ringkas) vs `GET /estimates/{id}` (detail penuh). Frontend bisa render panel kanan. |
| **Lazyload detail per item** (`/estimates/get_estimate_data_ajax/{id}`) | Endpoint detail `GET /api/estimates/{id}` + **single-flight**; data pendukung (activity/notes/emails) via **sub-resource** terpisah agar muat bertahap. |
| **Deep-link `#id`** (URL hash shareable) | API stateless → hash murni domain frontend. Backend cukup ekspos `GET /estimates/{id}`; frontend tautkan `#id`. |
| **Hidden columns** (ringkas) | API menyediakan **field whitelist / summary view** (projection), bukan kembali seluruh kolom. |
| **Tab-tab konten** | API pecah per resource (`/estimates/{id}/activity`, `/tasks?rel_id=`, `/notes`, `/emails`) → frontend tab + lazy. |
| **Expand (full view)** | Frontend toggle layout; API stateless tidak perlu tahu. |

### 7.2 Anti-pattern yang dihindari
- Jangan render **HTML partial** dari backend (API harus return **JSON/structure**, bukan HTML
  siap-tempel) — biarkan frontend/client yang render.
- Jangan jadikan "hidden columns" sebagai domain backend; itu murni preferensi UI.

### 7.3 Inti yang perlu kita jaga di API
1. **Pemisahan summary vs detail** (endpoint/kontrak berbeda).
2. **Resource pendukung terpisah** untuk tab/lazy (activity, notes, tasks, emails, views).
3. **Auth + scope per resource** — di project multi-tenant ini, tiap sub-resource wajib
   cek izin + tenant scope (jangan hanya `user_can_view_estimate` satu operator).

---

## 8. Lokasi Kode (Referensi)

| Komponen | File |
|----------|------|
| Layout 2 panel | `views/admin/estimates/list_template.php` |
| Link klik nomor → `init_estimate` | `views/admin/tables/estimates.php:130` |
| `hidden_columns` (estimates) | `views/admin/estimates/manage.php:11` |
| Wrapper per modul | `assets/js/main.js:5843-5864` |
| `load_small_table_item` | `assets/js/main.js:5813` |
| `toggle_small_view` | `assets/js/main.js:3155` |
| `small_table_full_view` | `assets/js/main.js:7040` |
| `do_hash_helper` | `assets/js/main.js:3339` |
| Endpoint ajax | `controllers/admin/Estimates.php:233` |
| Preview template (tab panel) | `views/admin/estimates/estimate_preview_template.php` |
| CSS | `assets/css/style.css:616,676` |

---

*Dokumen dibuat berdasarkan reverse-engineering, 27 Agustus 2026.*
