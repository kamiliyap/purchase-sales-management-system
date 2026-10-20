# Modul Sales

## Business rule

Sales Order menggunakan customer dari m_customer dan 1-100 detail barang dari m_barang. Barang duplikat ditolak dengan distinct. Quantity integer 1 sampai 2147483647. Harga jual diisi untuk transaksi ini karena Master Barang tidak mempunyai harga jual. Harga/diskon nonnegatif, maksimal dua desimal, mengikuti decimal(15,2): maksimal 9999999999999.99.

Diskon adalah nominal untuk satu baris, bukan persen. Total baris = quantity x harga jual - diskon; diskon tidak boleh melebihi nilai bruto. Total SO adalah penjumlahan total baris. Semua nominal dihitung dalam integer sen dengan pemeriksaan overflow. Harga jual tidak otomatis menggunakan harga beli. Tidak ada PPN tambahan karena schema Sales tidak memiliki kolom PPN.

Header dan detail disimpan atomik dalam transaction. Customer/barang diperiksa lagi dan dikunci. Snapshot SKU/nama diambil dari master di server. Total/snapshot/nomor dokumen dari frontend tidak dipercaya. Nomor menggunakan DocumentNumberService existing.

Status SO Terbuka/Diinvoice berasal dari keberadaan invoice, tanpa kolom status tambahan. SO yang sudah memiliki invoice tidak dapat diedit/dihapus. Penghapusan SO tanpa invoice menghapus detail melalui cascade existing.

Invoice Penjualan menggunakan full invoice: satu SO maksimal satu invoice. Total disalin dari SO yang dikunci; total_invoice dari request diabaikan. SO harus memiliki customer, detail valid, dan total header/baris yang konsisten. SO kosong atau tidak konsisten dari data lama ditolak tanpa diperbaiki otomatis. Tanggal invoice minimal tanggal SO; jatuh tempo minimal tanggal invoice. Status: Belum Lunas, Lunas. Ini pencatatan status, bukan implementasi pembayaran.

Invoice ganda dicegah oleh row lock/pemeriksaan existing invoice dan unique index invoice_penjualan_so_unique. Pilihan invoice hanya mencantumkan SO yang memiliki detail dan belum memiliki invoice; pemeriksaan lengkap tetap di server. Migration baru menolak duplikasi historis tanpa menghapus/menggabungkan data.

Retur terhubung dengan Invoice Penjualan. Tanggal retur minimal tanggal invoice. Alasan wajib, maksimal 255 karakter. Status dibatasi Rule::in: Diajukan, Disetujui, Ditolak, Selesai. Retur mengikuti schema header existing; tidak menambahkan detail retur atau mutasi stok.

## Permission

Semua route memakai auth + module.permission yang memanggil Gate access-route. Form Request juga memeriksa Gate. Akses tanpa izin 403 termasuk POST/PUT/DELETE; bukan hanya pembatasan sidebar.

| Role | Sales Order | Invoice Penjualan | Retur Penjualan |
|---|---|---|---|
| Admin | CRUD | index/create/store/show | index/create/store/show |
| Sales | CRUD | index/create/store/show | index/create/store/show |
| Finance | index/show | index/create/store/show | index/show |
| Purchasing | Ditolak | Ditolak | Ditolak |

Sales tetap mengakses Customer dan Barang sesuai permission existing. Matrix RolePermissions sudah mencakup Sales, sehingga tidak perlu diubah. Sidebar existing memakai Route::has + Gate; tiga menu Sales otomatis aktif saat route tersedia. Tidak ada route/menu Detail Sales Order terpisah.

## Arsitektur

Controller berfokus pada HTTP/redirect/view. Tiga Form Request membatasi input. SalesOrderService menangani penyimpanan atomik dan validitas SO untuk invoice; SalesAmounts menghitung integer sen. SalesStatuses memusatkan status. SalesMoney menormalkan nilai DECIMAL dari MySQL dan REAL dari SQLite agar pembacaan nilai maksimum tidak dibulatkan oleh konversi float ke string PHP. Kalkulasi transaksi tetap integer.

Komponen/view Sales terpisah mengikuti pola Purchasing agar modul Purchasing tidak perlu direfactor. Dashboard dan aset Purchasing tetap digunakan tanpa perubahan desain. Tidak ada perubahan inventory/warehouse/payment/multi-gudang.

## Database dan data historis

Menggunakan empat tabel existing: sales_order, detail_sales_order, invoice_penjualan, retur_penjualan. Tidak menambah tabel. Hanya migration baru 2026_10_16_000003_enforce_one_sales_invoice_per_order.php untuk unique id_sales_order pada invoice_penjualan. Migration bisnis lama tidak diubah.

Preflight MySQL menemukan 0 SO, 0 detail, 0 invoice, 0 retur; tidak ada duplikasi invoice/barang atau SO kosong yang perlu diperbaiki. Data Purchasing historis tidak diubah.

## Pengujian

Jalankan seluruh project dengan php vendor/bin/phpunit --do-not-cache-result. phpunit.xml menggunakan SQLite :memory:, bukan MySQL bisnis. Cakupan baru: CRUD/multi-item, permission endpoint tiap role, snapshot/total server, validasi, duplicate barang, kapasitas nominal maksimum sampai invoice, rollback detail/invoice, status otomatis, full invoice dan constraint database, penolakan legacy SO invalid, serta retur/tanggal/status. JavaScript baru diperiksa dengan node --check.

## File baru

- app/Http/Controllers/SalesOrderController.php
- app/Http/Controllers/InvoicePenjualanController.php
- app/Http/Controllers/ReturPenjualanController.php
- app/Http/Requests/SalesOrderRequest.php
- app/Http/Requests/InvoicePenjualanRequest.php
- app/Http/Requests/ReturPenjualanRequest.php
- app/Services/SalesOrderService.php
- app/Support/SalesAmounts.php
- app/Support/SalesStatuses.php
- app/Casts/SalesMoney.php
- database/migrations/2026_10_16_000003_enforce_one_sales_invoice_per_order.php
- public/assets/js/sales-order.js
- public/assets/js/sales-documents.js
- tests/Feature/SalesTest.php
- SALES_RULES.md
- resources/views/sales-order/create.blade.php
- resources/views/sales-order/edit.blade.php
- resources/views/sales-order/index.blade.php
- resources/views/sales-order/partials/form.blade.php
- resources/views/sales-order/partials/item-row.blade.php
- resources/views/sales-order/show.blade.php
- resources/views/invoice-penjualan/create.blade.php
- resources/views/invoice-penjualan/index.blade.php
- resources/views/invoice-penjualan/partials/form.blade.php
- resources/views/invoice-penjualan/show.blade.php
- resources/views/retur-penjualan/create.blade.php
- resources/views/retur-penjualan/index.blade.php
- resources/views/retur-penjualan/partials/form.blade.php
- resources/views/retur-penjualan/show.blade.php
- resources/views/sales/partials/detail-fields.blade.php
- resources/views/sales/partials/document-form.blade.php
- resources/views/sales/partials/fields.blade.php
- resources/views/sales/partials/list.blade.php

## File diubah

- routes/web.php: Mendaftarkan tiga resource Sales di dalam auth + module.permission.
- app/Models/SalesOrder.php: Status otomatis dari invoice, relasi hasOne invoice, dan cast nominal.
- app/Models/DetailSalesOrder.php: Cast harga/diskon/total untuk nilai dua desimal yang konsisten.
- app/Models/InvoicePenjualan.php: Cast total invoice untuk MySQL dan SQLite.
- tests/Feature/AuthenticationPermissionTest.php: Menyesuaikan assertion Sales dari route belum tersedia menjadi aktif.
- AUTHENTICATION.md: Memperbarui status implementasi modul Sales.
