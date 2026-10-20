# Aturan Purchasing

> Pembaruan 19 Oktober 2026: aturan Invoice Pembelian terbaru ada di [AUTOMATIC_PURCHASE_INVOICES.md](AUTOMATIC_PURCHASE_INVOICES.md). Invoice kini otomatis setelah seluruh barang PO diterima dan diverifikasi Baik; bagian invoice manual/minimal satu penerimaan di bawah merupakan riwayat implementasi sebelumnya.

## Supplier

Nama/email maksimal 100 karakter; alamat maksimal 200; telepon maksimal 20. Route resource tidak memiliki show. Supplier yang dipakai PO tidak dapat dihapus; transaksi, row lock, dan penanganan constraint menjaga integritas dengan pesan bisnis.

## Permintaan Pembelian

Pemohon otomatis dari akun login pada pembuatan dan tetap pada edit. Jika ID pemohon ikut dikirim, nilainya harus sama dengan identitas yang ditetapkan server dan merujuk user yang tersedia. Pengiriman ID lain ditolak.

Penyetuju dipilih dari user Admin/Purchasing. Pemberian status Disetujui/Ditolak memerlukan Gate approve-purchase-request: hanya penyetuju yang ditunjuk (Purchasing) atau Admin. Data persetujuan yang sudah final juga tidak dapat diedit oleh Purchasing lain. Tahap ini belum menerapkan kewajiban pemohon dan penyetuju berbeda.

Status: Draft, Diajukan, Disetujui, Ditolak. Permintaan yang sudah dipakai PO tidak dapat diedit/dihapus. Relationship pemohon/penyetuju merujuk User.

Data historis tidak diperbaiki dengan mengganti ID otomatis. Preflight database menemukan satu permintaan dengan user yang tidak tersedia. Edit/PO baru dari permintaan dengan identitas tidak valid ditolak; identitas historis tersebut perlu ditinjau terpisah. Foreign key user belum ditambahkan agar data lama tetap utuh.

## Purchase Order

Permintaan harus tersedia, berstatus Disetujui, mempunyai pemohon valid dan penyetuju ber-role Admin/Purchasing. Tanggal PO minimal tanggal permintaan. Aturan diperiksa kembali di dalam transaction dengan row lock, berlaku untuk create/update. Pilihan form hanya menampilkan permintaan Disetujui.

Status PO dihitung dari hubungan invoice: Terbuka (belum ada invoice) atau Diinvoice. Status dari frontend ditolak, tidak ada kolom status baru yang perlu disinkronkan. PO yang sudah memiliki invoice tetap tidak dapat diedit/dihapus.

## Full invoice

Satu PO maksimal satu invoice; relasi invoice() hasOne ditambahkan sementara invoices() dipertahankan untuk kompatibilitas view lama. Total selalu disalin dari total_harga PO yang dikunci di server. total_invoice dari request diabaikan, termasuk nilai yang dipalsukan; form menampilkan nilai readonly.

Tanggal invoice minimal tanggal PO; jatuh tempo minimal tanggal invoice. Status: Belum Lunas, Lunas. Ini pencatatan status sederhana, bukan modul pembayaran.

PO yang sudah mempunyai invoice tidak ditawarkan lagi di form. Double-submit ditolak dengan pesan bisnis. Unique index invoice_pembelian_po_unique memberi jaminan pada database. Migration baru berhenti jika menemukan duplikasi historis; tidak menghapus/menggabungkan invoice otomatis.

## Retur Pembelian

Status: Diajukan, Disetujui, Ditolak, Selesai. Validasi parent, tanggal, dan alasan tetap berlaku. Permission route tetap sesuai matriks role dasar; Finance hanya membaca retur.

Pilihan status per dokumen menggunakan PurchasingStatuses dan Rule::in. Tampilan menggunakan select menggantikan teks bebas. Layout utama tetap.

## File

Baru:
- app/Support/PurchasingStatuses.php: daftar status dan role penyetuju.
- database/migrations/2026_10_16_000002_enforce_one_purchase_invoice_per_order.php: unique index full invoice, preflight duplikasi, rollback indeks.
- tests/Feature/PurchasingBusinessRulesTest.php: batas Supplier, identitas, approval, status, kronologi, integritas invoice, dan data historis.
- PURCHASING_RULES.md: dokumentasi aturan dan batas tahap ini.

Diubah:
- app/Http/Controllers/SupplierController.php: validasi panjang dan perlindungan delete.
- app/Http/Controllers/PermintaanPembelianController.php: identitas, approval, status, dan larangan edit setelah PO.
- app/Http/Controllers/PurchaseOrderController.php: filter/pemeriksaan kelayakan permintaan dan penolakan status frontend.
- app/Http/Controllers/InvoicePembelianController.php: full invoice, nominal server, status, dan filter PO.
- app/Http/Controllers/ReturPembelianController.php: whitelist status.
- app/Models/PermintaanPembelian.php: relasi pemohon/penyetuju.
- app/Models/PurchaseOrder.php: status otomatis dan relasi invoice tunggal.
- app/Providers/AppServiceProvider.php: Gate persetujuan.
- routes/web.php: resource Supplier tanpa show.
- resources/views/permintaan-pembelian/partials/form.blade.php: pemohon readonly, pilihan penyetuju/status.
- resources/views/permintaan-pembelian/index.blade.php: nama pengguna.
- resources/views/permintaan-pembelian/show.blade.php: nama pengguna.
- resources/views/purchase-order/index.blade.php: status otomatis.
- resources/views/purchase-order/show.blade.php: status dan tombol invoice hanya sebelum invoice tersedia.
- resources/views/invoice-pembelian/partials/form.blade.php: total readonly, status, dan keterangan PO tersedia.
- resources/views/retur-pembelian/partials/form.blade.php: pilihan status.
- resources/views/purchasing/partials/fields.blade.php: field tampilan/read-only dan penghapusan datalist status umum.
- tests/Feature/PurchasingTest.php: fixture pengguna valid, permintaan approved, nominal invoice server.
- tests/Feature/DocumentNumberTest.php: fixture pengguna valid dan PO tanpa invoice untuk preview form.

Migration bisnis lama dan modul Sales tidak diubah. Pengujian utama menggunakan SQLite :memory: dari phpunit.xml.


## Penerimaan Barang (19 Oktober 2026)

Audit migration dan model awal: belum ada modul/tabel penerimaan; detail PO menyimpan snapshot SKU/nama serta kuantitas dan mengizinkan beberapa baris untuk barang yang sama. Database lokal menggunakan MySQL 8.0.43; migration lama sudah dijalankan. Migration tambahan penerimaan telah dijalankan tanpa mengubah migration lama atau mengisi ulang dokumen historis.

Alur: Permintaan Pembelian ? Purchase Order ? Penerimaan Barang ? Invoice Pembelian ? Retur Pembelian (jika ada). Sales tidak diubah.

Satu PO dapat memiliki beberapa penerimaan, sehingga tidak ada unique constraint pada id_purchase_order. Setiap penerimaan minimal satu item, kuantitas integer 1?2147483647, kondisi tepat Baik/Rusak, keterangan maksimal 1000 karakter. Barang harus ada di detail PO. Kuantitas PO dijumlah per barang, termasuk jika barang sama muncul di beberapa baris PO. Total semua penerimaan untuk barang tersebut, termasuk kondisi Rusak, tidak boleh melebihi jumlah PO. Update mengecualikan nilai lama dokumen yang sedang diperbarui. Penerimaan parsial dan pemisahan kondisi lewat beberapa baris diperbolehkan.

Tanggal penerimaan minimal tanggal PO. PO penerimaan tidak dapat diganti saat edit. Nomor PB/tahun/urutan memakai layanan penomoran existing, unik dan tidak dapat diganti lewat request. Penerima berasal dari akun login yang valid; ID pengguna lain ditolak. Identitas penerima awal tetap saat edit. Nama penerima memenuhi pencatatan penerima; tahap ini tidak menambahkan unggahan tanda tangan.

Seluruh header/detail create, replace saat update, serta delete memakai transaksi. PO dikunci terlebih dahulu untuk menyelaraskan operasi penerimaan dengan pembuatan invoice. PO yang memiliki penerimaan tidak dapat diedit atau dihapus. Penerimaan yang PO-nya sudah memiliki invoice tidak dapat diedit/dihapus, agar bukti penerimaan tidak hilang. Penerimaan lanjutan masih diperbolehkan setelah invoice selama total tidak melewati PO.

FK header ke PO dan user serta detail ke barang memakai restrict delete. Detail dihapus melalui cascade header. Nomor penerimaan memiliki unique index; kuantitas memakai unsigned integer dan kondisi memakai enum. Batas positif, keanggotaan barang PO, tanggal dan batas kumulatif diperiksa aplikasi di dalam transaksi; bukan constraint lintas tabel pada database.

Admin/Purchasing: CRUD. Finance: index/show. Sales: seluruh endpoint ditolak 403. Guest diarahkan ke login mengikuti autentikasi existing. Proteksi melalui middleware module.permission dan otorisasi Form Request, selain sidebar/Gate.

Invoice baru wajib memiliki minimal satu penerimaan. Penerimaan parsial atau seluruhnya Rusak tetap memenuhi rule sederhana tugas; tahap ini belum menambahkan aturan invoice berbasis jumlah barang baik. Total invoice tetap total PO dari server, bukan total frontend atau nilai penerimaan. Satu PO maksimal satu invoice tetap dijaga constraint existing. Pilihan PO di form invoice hanya PO dengan penerimaan dan tanpa invoice.

Invoice historis tanpa penerimaan tetap dapat dibaca, dan retur terhadap invoice historis tetap mengikuti alur existing. Tidak ada penerimaan fiktif, backfill, penghapusan atau perubahan nominal data lama. PO lama tanpa invoice perlu penerimaan nyata sebelum invoice baru dibuat. Tidak ada perubahan stok otomatis atau modul gudang.

Retur tetap belongsTo InvoicePembelian agar scope dan data lama terjaga. Rekomendasi lanjutan: pertahankan invoice untuk hubungan keuangan, lalu tambahkan detail retur yang merujuk detail penerimaan untuk asal barang/kondisi dan validasi kuantitas retur; lakukan lewat migration terpisah setelah kebutuhan disepakati.

### File baru

- app/Http/Controllers/PenerimaanBarangController.php
- app/Http/Requests/StorePenerimaanBarangRequest.php
- app/Http/Requests/UpdatePenerimaanBarangRequest.php
- app/Services/PenerimaanBarangService.php
- app/Models/PenerimaanBarang.php
- app/Models/DetailPenerimaanBarang.php
- database/migrations/2026_10_19_000001_create_penerimaan_barang_tables.php (tabel penerimaan_barang dan detail_penerimaan_barang)
- resources/views/penerimaan-barang/index.blade.php
- resources/views/penerimaan-barang/form.blade.php
- resources/views/penerimaan-barang/show.blade.php
- public/assets/js/penerimaan-barang.js
- tests/Feature/PenerimaanBarangTest.php

### File yang diubah

- app/Models/PurchaseOrder.php: hasMany penerimaanBarang.
- app/Services/DocumentNumberService.php: nomor PB.
- app/Support/RolePermissions.php: izin modul penerimaan.
- app/Http/Controllers/PurchaseOrderController.php: larangan edit setelah penerimaan.
- app/Http/Controllers/InvoicePembelianController.php: pilihan PO dan syarat penerimaan.
- routes/web.php: resource penerimaan di middleware existing.
- resources/views/layouts/app.blade.php: sidebar Purchasing.
- resources/views/purchase-order/show.blade.php: tautan penerimaan, batas tombol edit/invoice.
- resources/views/invoice-pembelian/partials/form.blade.php: petunjuk kelayakan PO.
- tests/Feature/PurchasingTest.php: penerimaan sebelum invoice pada fixture alur.
- tests/Feature/PurchasingBusinessRulesTest.php: fixture PO untuk uji invoice memiliki penerimaan.
- tests/Feature/DocumentNumberTest.php: fixture pilihan PO untuk preview invoice.
- PURCHASING_RULES.md: hasil audit dan laporan implementasi.

### Validasi

Seluruh suite `php artisan test --compact`: **87 test lulus, 1351 assertion**. Test penerimaan mencakup multi-item, barang di luar PO, kuantitas berlebih/kumulatif, kondisi, tanggal, identitas penerima, akses semua endpoint per role, guest, edit/delete, proteksi PO, syarat invoice, nominal server, rollback header/detail create/update, stok tetap, dan invoice historis. Migration berhasil dijalankan pada MySQL lokal; pengujian otomatis memakai SQLite :memory: sesuai konfigurasi proyek. File PHP yang disentuh dirapikan dengan Pint.


## Export PDF dan detail permintaan

Implementasi terbaru menambahkan detail barang permintaan dan lima export PDF read-only. Rancangan, migration, permission, kompatibilitas data lama, pengujian dan contoh ada di [PDF_EXPORT_IMPLEMENTATION.md](PDF_EXPORT_IMPLEMENTATION.md). Aturan invoice otomatis tetap seperti sebelumnya.


## Tahap 2 ? Retur dari Penerimaan dan Pengganti

Aturan retur berbasis invoice pada bagian historis di atas digantikan untuk transaksi baru oleh [PURCHASE_RETURN_STAGE2.md](PURCHASE_RETURN_STAGE2.md). Retur baru khusus Rusak merujuk penerimaan, memakai reservasi dan transisi Diajukan ? Disetujui ? Selesai atau Diajukan ? Ditolak. Penerimaan pengganti merujuk detail retur Selesai; invoice historis tetap utuh.
