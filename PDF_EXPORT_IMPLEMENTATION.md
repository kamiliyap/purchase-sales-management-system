# Export PDF Transaksi dan Detail Permintaan Pembelian

Implementasi 19 Oktober 2026, berdasarkan keputusan pengguna setelah PDF_EXPORT_AUDIT.md.

## Hasil

DomPDF terpasang melalui barryvdh/laravel-dompdf **3.1.2**, engine dompdf/dompdf **3.1.6**, dengan versi terkunci pada composer.lock. Lima dokumen memiliki template Blade masing-masing: Permintaan Pembelian, Purchase Order, Penerimaan Barang, Invoice Pembelian, dan Invoice Penjualan. Kwitansi ditunda; tidak ada endpoint atau kesimpulan pembayaran terverifikasi dari status Lunas.

Semua PDF memakai A4 portrait (595,28 ? 841,89 pt), margin 20 mm atas/bawah dan 16 mm samping, teks hitam, latar putih, garis tabel, nomor/tanggal/status dokumen, area tanda tangan, serta footer bernomor halaman. Header tabel diulang untuk halaman berikutnya. Tombol Preview PDF membuka PDF inline pada tab baru; Download PDF mengirim attachment dengan nama file yang disanitasi. Layout dashboard tidak diubah.

## Identitas perusahaan

config/company.php berisi PT Sinar Niaga Teknologi ? Distributor Perangkat IT. Header secara eksplisit menyatakan identitas perusahaan simulasi untuk pembelajaran, bukan identitas legal perusahaan. Alamat, email, telepon, NPWP dan logo tidak dibuatkan data fiktif: semuanya kosong secara default dan hanya muncul jika dikonfigurasi.

Konfigurasi melalui .env, mengikuti contoh .env.example:

- PDF_COMPANY_NAME dan PDF_COMPANY_BUSINESS
- PDF_COMPANY_ADDRESS, PDF_COMPANY_EMAIL, PDF_COMPANY_PHONE
- PDF_COMPANY_NPWP
- PDF_COMPANY_LOGO: path PNG/JPEG relatif terhadap public/, bukan URL

Jika config pernah dicache, jalankan `php artisan config:clear` setelah mengubah konfigurasi. Logo hanya dibaca dari file lokal di dalam public/. Remote resource, PHP tersemat dan JavaScript PDF dinonaktifkan. Teks database di-escape oleh Blade.

## Audit dan rancangan detail permintaan

Schema awal hanya menyimpan header permintaan. Penambahan memakai tabel baru detail_permintaan_pembelian; tabel dan migration lama tidak diubah. Field baru:

- id_detail_permintaan (PK), id_permintaan (FK), id_barang (FK)
- sku dan nama_barang (snapshot server dari m_barang)
- kuantitas (unsigned integer), tanggal_dibutuhkan (date), keterangan (nullable, maksimal 1000 karakter)
- timestamps

FK permintaan memakai cascade delete detail; FK barang restrict delete agar referensi tetap utuh. Relasi PermintaanPembelian.details, DetailPermintaanPembelian.permintaanPembelian/barang dan MBarang.detailPermintaanPembelian ditambahkan.

Permintaan baru wajib minimal satu detail, maksimal 100. Barang harus valid, kuantitas integer 1?2147483647, tanggal dibutuhkan tiap baris tidak boleh sebelum tanggal permintaan. SKU/nama kiriman frontend diabaikan. Header dan detail disimpan/diganti dalam transaksi dengan row lock barang, sehingga kegagalan detail membatalkan perubahan header juga. Aturan pemohon, penyetuju, approval dan larangan edit setelah dipakai PO tetap berjalan.

Permintaan lama tidak dihapus atau diisi detail buatan. PDF dan halaman detail menampilkan keterangan jika detail historis belum tercatat. Edit header permintaan lama tanpa detail tetap diperbolehkan jika tidak melanggar aturan approval/PO existing; detail dapat ditambahkan ketika informasi nyata tersedia. Permintaan yang sudah memiliki detail wajib mempertahankan minimal satu detail saat edit.

Tanggal_diperlukan pada header tetap dipertahankan untuk kompatibilitas. Tanggal_dibutuhkan per baris dicatat terpisah; tidak mengganti field historis tersebut.

Dari halaman detail permintaan Disetujui tersedia tombol Buat PO dari Detail Permintaan. Endpoint create PO menerima id_permintaan yang valid/Disetujui dan mengisi daftar barang serta jumlah awal. Harga beli master hanya saran awal yang dapat diedit; diskon awal nol, perhitungan final tetap oleh server PO existing. Pemilihan supplier, harga, diskon, PPN dan tanggal PO tetap melalui form PO. Tidak menambahkan aturan larangan barang di luar permintaan atau batas kuantitas PO baru. Permintaan historis tanpa detail tetap dapat dipakai PO melalui form existing.

## Sumber data PDF dan nominal

| Dokumen | Isi khusus |
|---|---|
| Permintaan Pembelian | Snapshot SKU/nama, kuantitas, tanggal dibutuhkan, keterangan; tanda tangan pemohon/penyetuju dengan nama user tersimpan |
| Purchase Order | Supplier, tanggal dibutuhkan, harga/diskon/total baris, PPN nominal tersimpan, total PO, tanda tangan Purchasing/supplier |
| Penerimaan Barang | Referensi PO, SKU/nama dari snapshot detail PO, kuantitas, Baik/Rusak, keterangan dan nama penerima |
| Invoice Pembelian | Supplier/PO/penerimaan terkait, rincian PO, tanggal/jatuh tempo, total invoice tersimpan, status, sumber otomatis bila ada |
| Invoice Penjualan | Customer/Sales Order, rincian pesanan, harga/diskon, total invoice tersimpan, tanggal/jatuh tempo dan status |

Subtotal sebelum diskon adalah penjumlahan total detail tersimpan ditambah diskon baris; diskon merupakan nominal per baris, bukan persentase. Nilai uang dijumlah pada satuan sen di server. PPN hanya ditampilkan pada PO/Invoice Pembelian sesuai field PO, bukan ditambahkan ke Sales. Permintaan dan penerimaan tidak diberi nilai transaksi fiktif. Total tagihan historis selalu memakai total_invoice tersimpan, termasuk bila berbeda dari rincian order; rincian diberi catatan sumbernya. Nominal, jenis dokumen, mode download, template dan lokasi file tidak diambil dari query frontend.

Nama pemohon/penyetuju/penerima berasal dari relasi user tersimpan; jika identitas historis hilang, area tanda tangan dibiarkan kosong. Penandatangan lain memakai area kosong berlabel peran karena nama persetujuannya belum dicatat. Tidak membuat tanda tangan digital atau menyatakan Draft telah disetujui.

## Endpoint dan permission

Setiap modul memiliki GET `/{modul}/{id}/pdf/preview` dan GET `/{modul}/{id}/pdf/download`, dengan nama route modul.preview-pdf dan modul.download-pdf. Parameter ID mengikuti konvensi route modul existing. Semua berada pada middleware auth dan module.permission. RolePermissions menyetarakan kedua action export dengan izin show; Finance tidak mendapat hak mutasi PO/penerimaan tambahan.

| Dokumen | Admin | Purchasing | Sales | Finance |
|---|---|---|---|---|
| Permintaan Pembelian | Ya | Ya | Tidak | Tidak |
| Purchase Order | Ya | Ya | Tidak | Ya |
| Penerimaan Barang | Ya | Ya | Tidak | Ya |
| Invoice Pembelian | Ya | Ya | Tidak | Ya |
| Invoice Penjualan | Ya | Tidak | Ya | Ya |

Role yang tidak berhak mendapat 403, guest diarahkan ke login, ID invalid/tidak ditemukan mendapat 404. Respons PDF menggunakan private/no-store. Tidak ada file PDF sensitif yang dipublikasikan di public/. Bisnis Sales dan service invoice otomatis tidak diubah; pada Sales hanya view detail invoice diberi tombol dan endpoint export read-only.

## Migration dan data historis

Migration baru **database/migrations/2026_10_19_000003_create_detail_permintaan_pembelian_table.php** sudah berhasil dijalankan pada MySQL lokal. Tidak ada backfill, perubahan nomor dokumen lama, penghapusan header permintaan, perubahan stok, nominal invoice atau status transaksi oleh fitur export. Tidak ada perubahan migration bisnis lama.

## Pengujian

`php artisan test --compact`: **102 test lulus, 1642 assertion**, durasi 22,88 detik pada run terakhir.

Cakupan tambahan: render PDF asli kelima dokumen dalam kedua mode; seluruh role; guest; 404; query manipulasi nominal/jenis/mode; snapshot barang setelah master berubah; nominal subtotal/diskon/PPN/total; nominal invoice historis; penolakan kwitansi; A4 dan beberapa halaman; escape HTML; opsi logo remote ditolak; CRUD dan validasi detail permintaan; prefill PO; kompatibilitas permintaan lama; rollback create/update header dan detail. Suite lama termasuk Sales dan invoice otomatis tetap lulus. File PHP yang diubah dirapikan dengan Pint. Composer selesai tanpa security vulnerability advisories pada instalasi.

## Contoh PDF dari database lokal

Contoh diekspor read-only dari dokumen asli yang tersedia, bukan dokumen baru/dummy. Nomor dan informasi historis dipertahankan apa adanya:

- [Permintaan Pembelian](storage/app/private/pdf-examples/permintaan-pembelian-08888xx.pdf)
- [Purchase Order](storage/app/private/pdf-examples/purchase-order-0-.pdf)
- [Invoice Pembelian](storage/app/private/pdf-examples/invoice-pembelian-INV-B-2026-0001.pdf)
- [Invoice Penjualan](storage/app/private/pdf-examples/invoice-penjualan-INV-J-2026-0001.pdf)
- [Preview gambar Invoice Pembelian](storage/app/private/pdf-examples/invoice-preview.png)

Semua contoh berukuran A4 dan berhasil dibuka/diekstrak teksnya; contoh Invoice Pembelian juga diperiksa visual. Database lokal belum memiliki Penerimaan Barang, sehingga tidak dibuat contoh penerimaan fiktif. Render PDF Penerimaan Barang telah lulus dalam database pengujian terisolasi. Permintaan lokal lama belum memiliki detail, sehingga contoh PDF-nya menampilkan pemberitahuan historis yang sesuai.

## Daftar file

Baru:

- app/Models/DetailPermintaanPembelian.php
- app/Http/Controllers/TransactionPdfController.php
- app/Services/TransactionPdfService.php
- app/Support/PdfAmounts.php
- config/company.php
- database/migrations/2026_10_19_000003_create_detail_permintaan_pembelian_table.php
- resources/views/permintaan-pembelian/partials/item-row.blade.php
- resources/views/permintaan-pembelian/partials/details.blade.php
- public/assets/js/purchase-request.js
- resources/views/pdf/layout.blade.php
- resources/views/pdf/buttons.blade.php
- resources/views/pdf/signatures.blade.php
- resources/views/pdf/order-lines.blade.php
- resources/views/pdf/totals.blade.php
- resources/views/pdf/permintaan-pembelian.blade.php
- resources/views/pdf/purchase-order.blade.php
- resources/views/pdf/penerimaan-barang.blade.php
- resources/views/pdf/invoice-pembelian.blade.php
- resources/views/pdf/invoice-penjualan.blade.php
- tests/Feature/TransactionPdfTest.php
- PDF_EXPORT_IMPLEMENTATION.md
- Contoh PDF/gambar pada storage/app/private/pdf-examples (artefak lokal)

Diubah:

- composer.json dan composer.lock
- .env.example
- app/Models/PermintaanPembelian.php
- app/Models/MBarang.php
- app/Http/Controllers/PermintaanPembelianController.php
- app/Http/Controllers/PurchaseOrderController.php
- app/Support/RolePermissions.php
- routes/web.php
- resources/views/permintaan-pembelian/partials/form.blade.php
- resources/views/permintaan-pembelian/show.blade.php
- resources/views/purchase-order/partials/form.blade.php
- resources/views/purchase-order/show.blade.php
- resources/views/penerimaan-barang/show.blade.php
- resources/views/invoice-pembelian/show.blade.php
- resources/views/invoice-penjualan/show.blade.php
- tests/Feature/PurchasingTest.php
- tests/Feature/PurchasingBusinessRulesTest.php
- tests/Feature/DocumentNumberTest.php
- PURCHASING_RULES.md


## Penyempurnaan PDF Penerimaan Barang

Susunan diperbarui mengikuti rincian form Maxy Academy yang diberikan pengguna. Lampiran visual tidak tersedia pada pesan, sehingga kesamaan visual dengan contoh asli belum dapat diverifikasi.

- Header identitas perusahaan existing, judul Penerimaan Barang, Nomor Penerimaan, Tanggal Penerimaan dan Nomor Order Pembelian (PO). Semua nomor ditampilkan sesuai string database, tanpa normalisasi atau penomoran ulang.
- Nomor kosong dan placeholder yang hanya terdiri dari nol/pemisah (contoh 0-, 0, ---) pada penerimaan maupun PO menolak export dengan HTTP 422 dan pesan peninjauan data sumber. Data sumber tidak diubah. Format nomor historis tidak dipaksa mengikuti pola nomor baru.
- Tabel enam kolom: SKU, Nama Barang, Kuantitas, Baik, Rusak, Keterangan. Header Kondisi Barang membentang di atas dua subkolom Baik/Rusak.
- Baris detail digabung berdasarkan id_barang dan keterangan yang sama. Barang dengan catatan berbeda tetap terpisah agar informasi tidak hilang. Baik/Rusak merupakan penjumlahan kuantitas berdasarkan kondisi setiap baris. Kuantitas tampilan adalah Baik + Rusak untuk kelompok dalam dokumen penerimaan ini, bukan total PO atau dokumen penerimaan lain.
- SKU/nama tetap dari snapshot detail PO. Jika informasi historis tidak tersedia, ditampilkan keterangan tidak tersedia; tidak diganti nama master saat ini atau data buatan.
- Footer isi dokumen memuat nama penerima dari database, ruang tanda tangan kosong, serta tanggal penerimaan asli. Tidak ditambahkan nama penyerah barang yang tidak tersimpan.
- A4 portrait dengan tabel lebar tetap, garis tabel, header tabel berulang, pembungkusan teks, pencegahan pemisahan baris dan blok tanda tangan antarhalaman. Preview/Download dan permission existing dipertahankan.

Tidak ada perubahan business rule, migration, database, stok atau permission.

File diubah pada penyempurnaan ini:

1. app/Services/TransactionPdfService.php ? agregasi read-only khusus penerimaan dan penolakan nomor placeholder/data kondisi invalid.
2. resources/views/pdf/penerimaan-barang.blade.php ? header, tabel kondisi dua tingkat dan footer penerima.
3. resources/views/pdf/layout.blade.php ? slot opsional judul/metadata serta CSS khusus; template lain tetap memakai tampilan default.
4. tests/Feature/TransactionPdfTest.php ? agregasi, jumlah kondisi, nomor asli/placeholder, catatan berbeda, data tetap utuh dan render banyak halaman A4.
5. PDF_EXPORT_IMPLEMENTATION.md ? laporan penyempurnaan.

Hasil terbaru `php artisan test --compact`: **105 test lulus, 1738 assertion**, durasi 30,02 detik. Pengujian mencakup seluruh suite project, kedua mode PDF, permission, dan tiga test tambahan khusus tampilan penerimaan. Pengujian beberapa halaman memeriksa render, ukuran A4 dan jumlah halaman; bukan jaminan untuk data historis arbitrer yang melebihi batas input existing.
