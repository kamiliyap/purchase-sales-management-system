# Authentication dan permission dasar

Login: `/login`. Logout menggunakan POST dengan CSRF. Tidak tersedia registrasi publik.

Migration baru `2026_10_16_000001_add_role_to_users_table.php` menambahkan `users.role` nullable. Migration bisnis lama tidak diubah. Pengguna tanpa role atau dengan role tidak dikenal mendapat 403 pada route bisnis.

## Menyiapkan akun

Jalankan dari terminal project, menggunakan alamat email pengguna yang sebenarnya:

```powershell
php artisan user:access "admin@example.com" Admin --name="Administrator"
php artisan user:access "purchasing@example.com" Purchasing
php artisan user:access "sales@example.com" Sales
php artisan user:access "finance@example.com" Finance
```

Jika email belum ada, perintah meminta nama dan password (minimal 12 karakter) beserta konfirmasi secara tersembunyi. Jika sudah ada, hanya role diubah; password tetap. Tidak dibuat akun/default password otomatis. Role tidak termasuk atribut mass assignable User.

## Matriks akses

| Role | Akses |
|---|---|
| Admin | Semua modul |
| Purchasing | Supplier, Barang, semua Purchasing |
| Sales | Customer, Barang, semua Sales |
| Finance | Invoice Pembelian/Penjualan; index/show PO, SO, Retur Pembelian/Penjualan |

Modul Sales aktif: Sales Order, Invoice Penjualan, dan Retur Penjualan menggunakan route/controller/view dengan grup middleware bisnis yang sama.

Semua route bisnis pada `routes/web.php` dikelompokkan dengan `auth` dan `module.permission`. Middleware kedua memanggil Gate `access-route`, yang menggunakan `RolePermissions`. Akses tanpa izin menghasilkan 403 meskipun URL diakses langsung. Menu dan tombol menggunakan Gate yang sama. Route Sales mendatang wajib ditempatkan di dalam grup yang sama.

Dashboard lengkap hanya untuk Admin. Role lain mendapat Workspace tanpa agregat lintas departemen.

Login memakai session guard Laravel, regenerasi session ID, pesan kegagalan umum, pembatasan percobaan per email/IP serta throttle route. Logout menghapus sesi dan memperbarui CSRF token.

## Pengujian

```powershell
php vendor/bin/phpunit --do-not-cache-result
```

Pengujian menggunakan SQLite `:memory:` sesuai `phpunit.xml`, bukan database bisnis MySQL. Jalankan satu proses test pada satu waktu karena cache Blade Windows dibagi bersama.

## Mengganti password sendiri

Jalankan `php artisan user:password "email-pengguna@example.com"` dari terminal project. Masukkan password minimal 12 karakter lalu ulangi untuk konfirmasi. Input disembunyikan dan password disimpan sebagai hash. Perintah tidak mengubah role pengguna.
