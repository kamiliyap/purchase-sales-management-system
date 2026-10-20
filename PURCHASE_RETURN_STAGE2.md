# Tahap 2 ? Retur Pembelian dan Penerimaan Pengganti

Implementasi mengikuti rancangan yang disetujui pengguna: retur khusus Rusak bercabang dari Penerimaan Barang; pengganti memakai bukti penerimaan baru; invoice tetap satu full invoice per PO setelah seluruh kuantitas Baik terpenuhi.

## Hasil audit dan schema

Sebelumnya retur hanya header yang wajib merujuk invoice. Penerimaan membatasi seluruh Baik + Rusak terhadap PO, sehingga penerimaan pengganti tidak dapat dicatat setelah penerimaan awal penuh. Belum ada detail retur, kuota pengganti, atau transisi status yang ketat.

Migration baru **2026_10_19_000004_add_receipt_based_purchase_returns.php** sudah dijalankan pada MySQL lokal, batch 8. Tidak ada migration lama diubah.

- retur_pembelian: id_invoice_pembelian menjadi nullable; tambah id_penerimaan nullable untuk kompatibilitas histori, created_by, approved_by, shipped_by, tanggal_dikirim, subtotal, diskon, ppn, total_retur. Field baru historis tetap null.
- detail_retur_pembelian: PK id_detail_retur; referensi header retur, detail penerimaan, detail PO; snapshot SKU/nama; kuantitas; harga_unit; diskon; subtotal setelah diskon; ppn; total_harga termasuk PPN; alasan; timestamps.
- detail_penerimaan_barang: tambah id_detail_retur_asal nullable untuk baris pengganti.
- FK baru restrict delete melindungi jejak dokumen dan pengguna. Quantity memakai unsigned integer dengan validasi positif di request/service.
- Rollback migration ditolak jika terdapat retur baru berbasis penerimaan, detail retur, penerimaan pengganti, atau retur tanpa invoice. Tidak menghapus histori baru untuk mengembalikan schema lama.

Semua kolom lama header retur pada database lokal dibandingkan sebelum/sesudah migration: **nilainya identik**. Tidak ada backfill relasi, nominal atau detail buatan.

## Retur dan reservasi

Retur baru wajib merujuk satu dokumen penerimaan. Detail wajib berasal dari penerimaan itu dan berkondisi Rusak. Baris PO harus dari PO yang sama dan barang yang sama. Kuantitas integer minimal 1; maksimal barang Rusak sumber dikurangi seluruh reservasi aktif. Status Diajukan, Disetujui dan Selesai mengonsumsi kuota retur; Ditolak melepaskannya.

Pasangan detail penerimaan/baris PO yang sama tidak boleh diulang dalam satu request; pengguna diminta menggabungkan jumlahnya. Reservasi juga dihitung antar-dokumen agar pengajuan bersamaan tidak melampaui sumber.

Penerimaan awal belum menyimpan baris PO harga secara langsung. Jika satu barang muncul pada beberapa baris PO dengan harga berbeda, pilihan baris sumber harga ditampilkan eksplisit. Akumulasi retur dari penerimaan awal untuk sebuah baris PO tidak boleh melebihi jumlah baris tersebut. Retur ulang atas pengganti wajib mewarisi baris PO dari retur asal, sehingga harga tidak dapat dipindahkan ke baris lain.

Header dan detail dibuat dalam satu transaksi, dengan PO dikunci lebih dahulu, diikuti dokumen penerimaan dan detail sumber/PO dalam urutan ID. Seluruh operasi retur/status/pengganti pada PO yang sama memakai penguncian PO yang sama. Transaksi memiliki retry konflik/deadlock maksimal lima kali. Nomor RET-B memakai DocumentNumberService existing dan tidak menerima nomor dari frontend.

Tanggal retur minimal tanggal penerimaan. Alasan header maksimal 255 karakter dan alasan per barang maksimal 1000. Penerimaan yang sudah digunakan retur dikunci dari edit/delete, termasuk bila retur ditolak, agar bukti sumber tidak ditulis ulang.

## Status

| Dari | Ke | Dampak |
|---|---|---|
| Baru | Diajukan | Reservasi Rusak dibuat |
| Diajukan | Disetujui | Reservasi tetap; identitas penyetuju dicatat |
| Diajukan | Ditolak | Reservasi dilepas; tidak membuka kuota pengganti |
| Disetujui | Selesai | Barang dikonfirmasi sudah dikirim ke supplier; catat pengirim dan tanggal_dikirim |

Perpindahan lain, termasuk kembali ke status sebelumnya dan mengirim status yang sama, ditolak. Tidak ada endpoint delete retur atau edit detail. Dokumen final tetap utuh. Tanggal kirim wajib ketika menjadi Selesai dan tidak boleh sebelum tanggal retur. Selesai tidak berarti barang pengganti sudah diterima.

## Perhitungan nominal

Semua nominal diambil/dihitung server dari snapshot detail PO, bukan harga master terbaru atau frontend.

- Harga/unit = harga_unit baris PO.
- Diskon retur = diskon nominal baris PO ? qty retur / qty baris PO.
- Subtotal netto = harga/unit ? qty retur ? diskon retur.
- PPN retur = nominal PPN PO ? subtotal netto retur / jumlah total_harga seluruh detail PO.
- Total baris = subtotal netto + PPN alokasi; total header menjumlah baris tersimpan.

Pembulatan HALF_UP ke sen dilakukan per baris memakai aritmetika decimal/integer (Brick Math), termasuk perkalian yang dapat melampaui kapasitas integer antara. Tidak menebak tarif pajak. PPN PO nol menghasilkan alokasi nol. Dasar alokasi tidak valid dan nominal melampaui kapasitas database ditolak.

Ini valuasi dokumen retur, bukan credit note/refund. Pembulatan per dokumen parsial tidak merekonsiliasi atau mengubah invoice. Invoice, pembayaran, dan stok tidak dikurangi otomatis.

## Pengganti dan pencegahan double counting

- Setiap baris pengganti wajib merujuk detail retur berstatus Selesai untuk PO dan barang yang sama.
- Kuota pengganti = qty detail retur ? seluruh qty pengganti yang telah dicatat untuk detail tersebut, termasuk pengganti Rusak.
- Satu retur dapat dipenuhi sebagian melalui beberapa penerimaan.
- Tanggal pengganti minimal tanggal barang retur dikirim ke supplier.
- Kuota penerimaan normal tetap dihitung hanya dari baris tanpa id_detail_retur_asal, maksimal qty PO. Retur tidak membebaskan kuota penerimaan normal.
- Pengganti tidak memakai kuota normal; ia memakai kuota retur Selesai tersendiri. Referensi asal disimpan permanen.
- Pengganti Rusak menghabiskan kuota induknya, tetapi tidak menambah kuantitas Baik. Ia dapat menjadi sumber retur baru; setelah dikirim, retur baru membuka kuota generasi selanjutnya.
- Penerimaan pengganti tidak dapat diedit atau dihapus. Bukti penerimaan lama tidak dihapus, tidak diganti kondisinya, dan tidak dipakai ulang dengan identitas baru.
- Halaman detail penerimaan dan retur menampilkan tautan asal/pengganti dan sisa kuota sehingga rantai dapat ditelusuri.

Contoh: PO 10; penerimaan 7 Baik + 3 Rusak; retur 3 selesai dikirim; pengganti 1 Baik menghasilkan 8 Baik tanpa invoice; pengganti 2 Baik menghasilkan 10 Baik dan satu invoice. Jika 3 pengganti Rusak, total Baik tetap 7 dan harus melalui retur generasi berikutnya, bukan menambah kuota retur induk lagi.

## Invoice dan kompatibilitas historis

AutomaticPurchaseInvoiceService dan unique constraint satu invoice per PO tetap dipertahankan. Syaratnya tetap semua jumlah per barang terpenuhi dari baris Baik. Retur, status Selesai dan baris Rusak tidak dihitung sebagai Baik. Penerimaan pengganti memanggil pemeriksaan existing dalam transaksi penerimaan yang sama. Kegagalan insert invoice membatalkan penerimaan pengganti beserta pemakaian kuotanya.

Retur baru menyimpan referensi invoice bila sudah ada saat retur dibuat; bila belum ada, referensinya null. Tidak ada pengisian otomatis kemudian. Relasi PO tetap dapat ditelusuri dari penerimaan. Invoice historis tetap utuh, termasuk status Lunas dan nominalnya. Penerimaan pengganti pada PO yang sudah memiliki invoice tidak membuat invoice kedua.

Retur historis tanpa id_penerimaan tetap dibaca melalui invoice existing dan bersifat read-only. Detail/nominal yang belum tercatat tidak dibuat dari perkiraan. PDF historis menampilkan informasi ketidaktersediaan secara eksplisit.

## UI, permission dan PDF

Form retur memilih penerimaan Rusak, menampilkan nomor/tanggal penerimaan, PO, supplier dan identitas perusahaan. Pengguna memilih sumber harga PO dan jumlah/alasan barang. Status awal selalu Diajukan. Form perubahan status hanya menyediakan langkah selanjutnya. Retur Selesai menyediakan tombol Catat Penerimaan Pengganti; form penerimaan juga dapat memilih retur Selesai dalam PO terkait.

Admin/Purchasing mengelola retur dan penerimaan; Finance hanya index/show/Preview PDF/Download PDF retur, sesuai permission existing yang memetakan export ke show. Sales ditolak. Middleware auth/module.permission, Form Request dan pemeriksaan role service melindungi endpoint. RolePermissions tidak diubah.

PDF Retur memakai layout corporate Purchasing A4 biru-putih. Memuat referensi nomor/tanggal penerimaan, PO/invoice bila tersedia, pembeli/perusahaan, supplier/alamat/kontak, detail SKU/nama/qty/harga/diskon/PPN/total/alasan, status, tanggal kirim, dan area tanda tangan dengan nama pembuat/pengirim jika tersimpan. Tidak membuat tanda tangan digital.

## Pengujian

Seluruh suite `php artisan test --compact`: **127 test lulus, 2078 assertion**, durasi 34,10 detik pada run terakhir.

Cakupan baru meliputi:

- Semua 16 pasangan perpindahan status, reservasi dan pelepasan kuota, tanggal kirim.
- Barang Baik/luar sumber/baris PO invalid ditolak; qty berlebih, duplicate pair, qty pecahan/nol ditolak.
- Pengganti bertahap; pengganti Rusak dan retur ulang selama tiga generasi; kuota normal terpisah dari kuota pengganti; sumber harga tetap pada retur ulang.
- Nominal server, pembulatan sen, perkalian besar, multi-item, manipulasi nominal frontend.
- Rollback header/detail retur, perubahan status, penerimaan pengganti dan invoice; kuota tetap dapat dipakai setelah rollback.
- Permission semua role, PDF asli dalam kedua mode, dokumen historis, migration up/down yang aman, foreign key dan invoice historis tidak berubah.
- Dua proses pengajuan retur bersamaan: hanya satu berhasil ketika kuota tidak cukup untuk keduanya.
- Tiga proses penerimaan pengganti bersamaan: dua berhasil sesuai kuota, satu ditolak; tepat satu invoice tercipta.

Concurrency memakai proses PHP terpisah dan database SQLite file terisolasi dengan WAL, bukan sekadar simulasi berurutan. Row locking MySQL diimplementasikan dan migration telah diuji pada MySQL lokal; pengujian concurrency InnoDB penuh belum dilakukan karena akun database lokal tidak memiliki izin membuat database uji terpisah. Test tidak menulis fixture ke data bisnis lokal.

Suite lama termasuk Sales tetap lulus. Dua file test Purchasing lama menyesuaikan fixture agar invoice-only return diperlakukan sebagai histori, sedangkan create/transisi baru diuji melalui alur penerimaan.

## Hasil pemeriksaan PDF

Contoh diekspor dari fixture dalam SQLite in-memory, diberi label CONTOH DATA UJI; bukan transaksi asli dan tidak ditulis ke database aplikasi.

- [Retur 1 item](storage/app/private/purchase-return-stage-2/retur-pembelian-1-item.pdf): 1 halaman, total Rp 222,00.
- [Retur 24 item](storage/app/private/purchase-return-stage-2/retur-pembelian-banyak-item.pdf): 3 halaman, total Rp 5.328,00.
- [Preview 1 item](storage/app/private/purchase-return-stage-2/retur-pembelian-1-item-p1.png).
- [Contact sheet banyak item](storage/app/private/purchase-return-stage-2/retur-pembelian-banyak-item-contact.png).

Seluruh halaman berukuran A4. Semua SKU muncul dalam ekstraksi teks. Pemeriksaan visual menunjukkan header tabel berulang, alasan panjang wrap, tabel tidak melewati margin, total dan tanda tangan terbaca. Test PDF multi-item juga memeriksa ukuran A4 dan jumlah halaman.

## File baru

- database/migrations/2026_10_19_000004_add_receipt_based_purchase_returns.php
- app/Models/DetailReturPembelian.php
- app/Http/Requests/StoreReturPembelianRequest.php
- app/Http/Requests/UpdateReturPembelianStatusRequest.php
- app/Services/ReturPembelianService.php
- app/Support/PurchaseReturnAmounts.php
- public/assets/js/purchase-return.js
- resources/views/retur-pembelian/partials/details.blade.php
- resources/views/pdf/retur-pembelian.blade.php
- tests/Feature/PurchaseReturnTest.php
- tests/Feature/PurchaseReturnConcurrencyTest.php
- tests/Support/purchase-return-concurrency.php
- PURCHASE_RETURN_STAGE2.md
- Artefak contoh PDF/PNG di storage/app/private/purchase-return-stage-2/

## File diubah

- app/Models/ReturPembelian.php
- app/Models/PenerimaanBarang.php
- app/Models/DetailPenerimaanBarang.php
- app/Http/Controllers/ReturPembelianController.php
- app/Http/Controllers/PenerimaanBarangController.php
- app/Http/Requests/StorePenerimaanBarangRequest.php
- app/Services/PenerimaanBarangService.php
- app/Services/TransactionPdfService.php
- routes/web.php
- resources/views/retur-pembelian/partials/form.blade.php
- resources/views/retur-pembelian/index.blade.php
- resources/views/retur-pembelian/show.blade.php
- resources/views/penerimaan-barang/form.blade.php
- resources/views/penerimaan-barang/show.blade.php
- resources/views/invoice-pembelian/show.blade.php
- tests/Feature/PurchasingTest.php
- tests/Feature/PurchasingBusinessRulesTest.php
- PURCHASING_RULES.md

Modul Sales, stok otomatis, desain dashboard utama dan migration lama tidak diubah. Pengembangan berhenti setelah Tahap 2; tidak menambahkan pembayaran, refund atau credit note.
