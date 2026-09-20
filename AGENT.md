# AGENT.md — Aturan Kerja (wajib dibaca sebelum mengerjakan apa pun)

## Prinsip #1: iSeed = konversi LANGSUNG database. URUTAN WAJIB:

1. **ISI/ubah DATABASE DULU** (tinker/mysql langsung — database adalah satu-satunya sumber kebenaran).
2. **BARU** jalankan iSeed dari database:
   ```
   php84 artisan iseed <tabel> --force --dumpauto=false --noregister
   php84 artisan iseed users --where="id BETWEEN A AND B" --classnameprefix=<NamaModul> --force --dumpauto=false --noregister
   ```
3. Pindahkan file hasil iSeed dari `database/seeders/` ke `modules/<Modul>/Database/Seeders/`, ganti namespace ke `Modules\<Modul>\Database\Seeders`, rename class bila perlu.
4. **JANGAN PERNAH menulis/mengedit isi seeder secara manual** (nama, email, realname, id, dll). Semua perubahan data dilakukan DI DATABASE dulu, lalu iSeed ulang. Seeder = snapshot database, bukan kode yang ditulis tangan.

## Prinsip #2: tabel `users` dipakai SEMUA modul

- Setiap modul meng-seed user-nya sendiri dengan range statis (User Start Number): customer 10249, surveyor 30373, agency 41537, association 42738, referral 44444, platform 75732.
- Seeder users modul WAJIB scoped ke range sendiri:
  ```
  \DB::table('users')->whereBetween('id', [START, END])->delete();
  ```
  GANTI `\DB::table('users')->delete();` (iSeed default) dengan whereBetween — delete semua akan menghapus user modul lain.
- **JANGAN PERNAH menjalankan seeder users modul SENDIRIAN** — FK `ON DELETE CASCADE`/`SET NULL` akan merusak tabel modul lain (contoh: `customer_staffs` CASCADE hilang, `associations.admin_id` jadi NULL). Selalu jalankan urutan penuh modul: **Users → Entity → Staffs**, dan reset demo = seed SEMUA modul berurutan.
- `users.name = slug(realname)`, `users.email = slug(realname)@wasnaker.lan`. Sebelum memilih nama/email baru, CEK bentrok dengan semua modul lain (users.email unique global).

## Prinsip #3: data statis & idempoten

- ID entity & user STATIS di seeder (bukan auto-increment dinamis) supaya reset periodik demo menghasilkan data identik.
- AUTO_INCREMENT users di-set 80000 (di CustomerUsersTableSeeder) — user baru via app tidak pernah masuk range statis modul mana pun.
- Vat (NPWP): range per modul — customer 1–97, surveyor 98–117 (di-seed modul pemakai, bukan modul Vat).

## RBAC & permission

- Permission modul = tanggung jawab modul itu sendiri (jangan tambah ke `RolePermissionSeeder` platform). Konvensi: seeder `Modules\<Modul>\Database\Seeders\<Modul>PermissionSeeder` (idempotent, `Permission::findOrCreate("...", 'sanctum')`) didaftarkan di `module.json` → `migration.seeds` agar ikut `php artisan module:seed <alias>`.
- `RolePermissionSeeder` platform hanya `users|roles|settings`. Contoh sukses: Region (sebelumnya di seeder platform, sekarang di modul).
- Setelah seed permission baru, jalankan `php artisan spine:rbac:sync` (idempotent, reset cache permission). Role `admin` bypass semua gate via `Gate::before` di `AppServiceProvider`; `staff` menerima grant per-modul.

## Pitfall teknis

- `--classnameprefix` = **nama modul saja** (misal `Association`), JANGAN sertakan nama tabel (`AssociationUsers` → menghasilkan `AssociationUsersUsersTableSeeder`).
- Jangan pakai loop bash dengan `\$f` untuk class seeder (escaping literal) — tulis class lengkap satu per satu.
- Setelah iSeed users: patch delete → whereBetween, baru seed.
- Lint: `php -l` setiap file seeder sebelum dijalankan.
- Komunikasi dengan user: Bahasa Indonesia. Commit message: English.
