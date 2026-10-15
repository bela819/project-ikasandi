# IKASANDI_Admin (Versi Single Entry Point — index.php)

Aplikasi web admin untuk **Penilaian Indeks Keamanan Siber dan Sandi (IKASANDI)** — dibangun dengan PHP native (mysqli) dan MySQL, mengikuti alur: login → dashboard → instrumen → penilaian → rekap → pengguna → log aktivitas.

Versi ini sudah digabung menjadi **satu file `index.php`** di root folder sebagai titik akses tunggal (front controller). Semua halaman yang sebelumnya terpisah (login.php, dashboard.php, dst.) sekarang menjadi satu file dan diakses lewat parameter `?page=`.

## Cara Mengakses Halaman

```
index.php?page=login       -> Login
index.php?page=register    -> Daftar akun operator
index.php?page=dashboard   -> Dashboard
index.php?page=instrumen   -> Instrumen IKASANDI
index.php?page=penilaian   -> Penilaian
index.php?page=rekap       -> Rekap IKASANDI
index.php?page=pengguna    -> Manajemen Pengguna (khusus admin)
index.php?page=log         -> Log Aktivitas (khusus admin)
index.php?page=download    -> Unduh rekap CSV
index.php?page=logout      -> Keluar
```

Jika mengakses `index.php` tanpa parameter apa pun, aplikasi otomatis mengarahkan ke halaman **dashboard** (bila sudah login) atau **login** (bila belum login).

## Struktur Folder

```
IKASANDI_Admin/
├── index.php            Titik masuk tunggal — seluruh halaman & logika digabung di sini
├── function/
│   ├── config.php       Konfigurasi & koneksi database (mysqli)
│   ├── auth.php         Session, requireLogin(), requireRole(), attemptLogin()
│   └── helpers.php      Fungsi bantu: layout header/footer, label kematangan, log aktivitas, dsb.
├── assets/
│   ├── css/style.css    Tampilan (sidebar, kartu statistik, badge level)
│   └── js/script.js     Interaksi form penilaian, konfirmasi hapus, pencarian
├── database/            (folder untuk backup/dump database)
├── uploads/              (folder penyimpanan bukti dukung, jika diunggah)
├── database.sql          Skema tabel + data awal (seed)
└── README.md
```

## Peran Pengguna

| Peran     | Akses |
|-----------|-------|
| **admin**    | Semua menu: dashboard, instrumen (kelola), penilaian, rekap, **pengguna**, **log aktivitas** |
| **operator** | dashboard, instrumen (lihat), penilaian, rekap (data miliknya) |

## Instalasi (XAMPP / Laragon / LAMP)

1. Salin folder `ikasandi` ke direktori web server, misalnya `htdocs/IKASANDI_Admin`.
2. Buat database dengan mengimpor `database.sql` melalui phpMyAdmin atau CLI:
   ```bash
   mysql -u root -p < database.sql
   ```
   Skrip ini otomatis membuat database `ikasandi`, seluruh tabel, serta mengisi data domain/kategori/pertanyaan instrumen IKASANDI (Domain I–IV) dari instrumen resmi.
3. Sesuaikan kredensial database pada `function/config.php` bila perlu (`DB_HOST`, `DB_USER`, `DB_PASS`, `DB_NAME`).
4. Pastikan folder `uploads/` dapat ditulis oleh web server (`chmod 755 uploads`).
5. Buka `http://localhost/IKASANDI_Admin/index.php` (otomatis diarahkan ke halaman login).

### Akun Default

| Username    | Password      | Peran    |
|-------------|---------------|----------|
| `admin`     | `admin123`    | admin    |
| `operator1` | `operator123` | operator |

> Password akun awal akan otomatis di-hash ulang (bcrypt) oleh sistem saat login pertama kali. **Segera ubah password default setelah instalasi.**

## Alur Penggunaan Singkat

1. **Login** menggunakan username & password (`index.php?page=login`).
2. Dari **Dashboard**, lihat ringkasan total instrumen, progres pengisian tahun berjalan, dan tingkat kematangan.
3. Buka **Instrumen IKASANDI** untuk melihat/menambah/mengubah/menghapus domain, kategori, dan pertanyaan (khusus admin).
4. Buka **Penilaian**, pilih topik/kategori dan tahun, isi skor 0–5 untuk tiap pertanyaan sesuai deskripsi indeks kematangan, lalu simpan.
5. Buka **Rekap IKASANDI** untuk melihat, mencari, mengubah, menghapus, atau mengunduh (CSV) hasil penilaian.
6. Admin dapat mengelola akun pada menu **Pengguna**, dan memantau seluruh aktivitas pada menu **Log Aktivitas**.

## Catatan Teknis

- Seluruh halaman (login, dashboard, instrumen, penilaian, rekap, pengguna, log, download, logout) sekarang berada dalam satu berkas `index.php`, diproses lewat `switch ($page)`.
- Navigasi internal (sidebar, tombol aksi, redirect) menggunakan format `index.php?page=...` alih-alih nama file terpisah seperti sebelumnya.
- Form dengan metode `GET` (filter/pencarian) menyertakan input tersembunyi `<input type="hidden" name="page" value="...">` agar parameter `page` tetap terbawa saat form disubmit.
- Password disimpan menggunakan `password_hash()` (bcrypt) dan diverifikasi dengan `password_verify()`.
- Seluruh query menggunakan **prepared statement** (mysqli) untuk mencegah SQL Injection.
- Output ditampilkan melalui fungsi `e()` (alias `htmlspecialchars`) untuk mencegah XSS.
- Layout (sidebar, topbar) dirender lewat `renderHeader()` / `renderFooter()` di `function/helpers.php` agar konsisten di semua halaman.
