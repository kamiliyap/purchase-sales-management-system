# Invoice Pembelian Otomatis

Aturan per 19 Oktober 2026 ini menggantikan aturan invoice manual / minimal satu penerimaan pada dokumentasi Purchasing sebelumnya.

## Audit schema dan keputusan tanggal

Audit migration, model, controller, serta `php artisan db:table invoice_pembelian` pada database lokal menemukan tabel InnoDB dengan field wajib: id_purchase_order, no_invoice, tanggal_invoice, jatuh_tempo, total_invoice decimal(15,2), status. Terdapat foreign key ke PO dan unique index invoice_pembelian_po_unique. Supplier diperoleh melalui relasi PO, bukan disalin dari request. Tidak ada termin pembayaran supplier atau tanggal jatuh tempo pada PO.

Keputusan: tanggal invoice adalah tanggal penerimaan paling akhir dari seluruh dokumen PO tersebut, bukan waktu simpan atau tanggal dari frontend. Jatuh tempo 30 hari kalender setelah tanggal invoice. Nomor menggunakan DocumentNumberService existing, pola INV-B/tahun/urutan (tahun mengikuti waktu penomoran Asia/Jakarta existing). Status awal Belum Lunas. Total disalin persis dari total_harga akhir PO yang dihitung server saat PO disimpan; tidak menghitung ulang dari harga master yang dapat berubah.

## Aturan bisnis

- Pembuatan PO tidak membuat invoice.
- Setiap simpan penerimaan (create/update) memeriksa seluruh penerimaan untuk PO terkait.
- Hanya kondisi Baik yang berarti barang telah diterima dan diverifikasi baik. Rusak tidak memenuhi kuantitas Baik.
- Semua barang PO harus terpenuhi; satu barang lengkap tidak boleh menutupi kekurangan barang lain.
- Jika barang sama muncul beberapa kali di detail PO, jumlah kewajibannya dijumlah per id_barang. Penerimaan tidak menyimpan referensi baris detail PO, sehingga kuantitas yang sama tidak boleh dipakai ulang untuk melengkapi masing-masing baris secara terpisah.
- PO tanpa detail tidak menghasilkan invoice otomatis.
- Penerimaan parsial tidak menghasilkan invoice. Invoice dibuat saat seluruh kuantitas barang Baik terpenuhi.
- Batas penerimaan kumulatif existing tetap mencakup Baik dan Rusak. Barang rusak yang diperbaiki/diverifikasi ulang perlu dikoreksi melalui edit dokumen penerimaan sebelum invoice; tidak ada alur barang pengganti melebihi PO atau retur gudang baru.
- Kondisi Baik pada form merupakan hasil verifikasi penerima yang login; tidak menambah tahap persetujuan lain.
- Penerimaan dan invoice berada dalam satu transaksi. PO dikunci lebih dahulu dengan lockForUpdate. Pembuatan invoice memeriksa kembali PO dan invoice existing di bawah lock, lalu memakai layanan penomoran existing. Unique index satu invoice per PO tetap menjadi perlindungan database. Retry transaksi untuk konflik/deadlock dibatasi lima kali.
- Kegagalan setelah insert invoice pun membatalkan header/detail penerimaan dan invoice. Pembaruan detail penerimaan juga dipulihkan saat rollback.
- PO memperoleh status Diinvoice dari relasi invoice existing, tanpa kolom status baru.
- Penerimaan yang sudah terkait invoice tetap tidak dapat diedit/dihapus.
- Tidak ada perubahan stok, Sales, pembayaran kompleks, atau inventory.

## UI dan akses

Form dan tombol pembuatan invoice manual dihapus. URL create/store lama ditolak 403 melalui RolePermissions/middleware untuk semua role; controller store juga menolak. Daftar invoice tetap memakai tema existing, menampilkan PO, nomor penerimaan, dan sumber Otomatis oleh sistem/Historis. Halaman detail menampilkan supplier/PO, tautan dokumen penerimaan, informasi otomatis, dan form perubahan status. Halaman penerimaan menautkan invoice terkait atau menjelaskan bahwa invoice menunggu seluruh barang Baik.

Admin, Purchasing, dan Finance tetap dapat melihat invoice serta mengubah status Belum Lunas/Lunas sesuai akses modul invoice existing. Endpoint update hanya menerima perubahan status; nominal, nomor, tanggal, PO, dan penanda otomatis tidak diambil dari request. Sales ditolak middleware/Gate. Finance tetap hanya dapat membaca dokumen penerimaan.

## Kompatibilitas dan migration

Migration baru `2026_10_19_000002_add_automatic_to_invoice_pembelian.php` menambahkan boolean dibuat_otomatis default false. Sudah dijalankan pada MySQL lokal. Migration lama tidak diubah. Invoice historis tidak diganti, dihitung ulang, dihapus, atau diberi nomor baru. Default false menandai histori tanpa menebak asal pembuatan dokumen lama.

Tidak ada backfill invoice otomatis secara massal untuk penerimaan lama. Pemeriksaan otomatis terjadi saat penerimaan disimpan. Jika PO sudah memiliki invoice historis, sistem mempertahankannya dan tidak membuat invoice kedua, termasuk saat penerimaan baru melengkapi PO tersebut. Termin dan nominal historis tetap utuh. Relasi retur ke invoice dipertahankan.

## File baru

- app/Services/AutomaticPurchaseInvoiceService.php
- database/migrations/2026_10_19_000002_add_automatic_to_invoice_pembelian.php
- tests/Feature/PurchaseInvoiceConcurrencyTest.php
- tests/Support/purchase-invoice-concurrency.php
- AUTOMATIC_PURCHASE_INVOICES.md

## File diubah

- app/Services/PenerimaanBarangService.php: memanggil pemeriksaan invoice dalam transaksi penerimaan; retry konflik.
- app/Models/InvoicePembelian.php: penanda otomatis, cast boolean, informasi sumber dan nomor penerimaan untuk daftar.
- app/Http/Controllers/InvoicePembelianController.php: meniadakan pembuatan manual, memuat relasi penerimaan, update status saja.
- app/Support/RolePermissions.php: menolak pembuatan manual pada endpoint dan Gate tombol.
- routes/web.php: menambahkan update invoice pembelian.
- resources/views/invoice-pembelian/index.blade.php: sumber dan dokumen penerimaan.
- resources/views/invoice-pembelian/show.blade.php: informasi otomatis/histori, tautan penerimaan dan form status.
- resources/views/purchase-order/show.blade.php: informasi invoice otomatis menggantikan tombol manual.
- resources/views/penerimaan-barang/form.blade.php: arti verifikasi Baik dan syarat invoice otomatis.
- resources/views/penerimaan-barang/show.blade.php: tautan invoice / informasi menunggu kelengkapan.
- tests/Feature/PenerimaanBarangTest.php: parsial, lengkap, Rusak, koreksi, detail barang sama, tanggal/jatuh tempo, penomoran, status PO, idempotensi, rollback dan histori.
- tests/Feature/PurchasingTest.php: alur invoice otomatis dan larangan manual.
- tests/Feature/PurchasingBusinessRulesTest.php: mempertahankan pengujian total server, status, constraint duplikat, histori, retur dan permission dengan alur otomatis.
- tests/Feature/DocumentNumberTest.php: mengeluarkan form invoice manual dari uji form; uji model penomoran existing tetap berjalan.
- tests/Feature/AuthenticationPermissionTest.php: endpoint invoice manual ditolak.
- PURCHASING_RULES.md: tautan ke aturan terbaru.

Dihapus: resources/views/invoice-pembelian/create.blade.php dan resources/views/invoice-pembelian/partials/form.blade.php (form manual yang tidak dipakai lagi).

## Pengujian

`php artisan test --compact`: **93 test lulus, 1405 assertion** (11,58 detik pada run terakhir).

Concurrency diuji dengan dua proses PHP terpisah pada database SQLite file terisolasi dalam mode WAL, termasuk overlap transaksi, retry penulisan, dua penerimaan, dan tepat satu invoice bernomor 0001. File uji dibersihkan setelah selesai. Pengujian bukan simulasi panggilan berurutan saja.

Batas verifikasi: akun MySQL aplikasi tidak berizin membuat database uji terpisah, sehingga uji concurrency InnoDB belum dijalankan. Row lock PO dan constraint unik MySQL diperiksa pada implementasi/schema; migration tambahan berhasil pada database MySQL lokal. Pengujian otomatis lainnya memakai SQLite sesuai phpunit.xml. Tidak ada pengujian mutasi pada data bisnis lokal. Semua test project, termasuk Sales, tetap lulus.
