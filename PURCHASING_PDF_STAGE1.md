# Tahap 1 ? Penyempurnaan PDF Purchasing

Selesai untuk Permintaan Pembelian, Purchase Order, Penerimaan Barang dan Invoice Pembelian. Struktur informasi mengacu form Maxy Academy Day 2 halaman 3?5; identitas dan gaya visual memakai PT Sinar Niaga Teknologi dengan penanda simulasi existing.

## Perubahan

- Keempat template memakai layout/CSS Purchasing bersama: A4 portrait, DejaVu Sans, judul 23px, teks 10px, tabel 9px, kop biru-putih, metadata dua kolom dan footer bernomor halaman. Layout PDF Sales tidak berubah.
- Purchase Order memakai label tanda tangan Penerima dan Perusahaan sesuai contoh materi. Nama tidak dikarang; area tetap kosong jika tidak tersimpan.
- Invoice Pembelian menampilkan telepon supplier hanya bila tersedia, tanggal invoice dan jatuh tempo asli, serta penjelasan pembayaran paling lambat tanggal tersimpan. Teks menjelaskan bahwa termin khusus tidak tercatat terpisah; tidak mengasumsikan jumlah hari atau mengubah jatuh tempo.
- Nominal mengikuti helper dan data tersimpan existing: kuantitas detail, harga, diskon nominal per baris, PPN PO dan total PO/invoice. Total invoice historis tidak ditimpa hasil perhitungan baru.
- Nomor kosong/placeholder seperti 0- pada PO atau referensi PO invoice diberi label ?Nomor historis perlu verifikasi (nilai tersimpan: ?)?, sehingga tidak disajikan tanpa penjelasan sebagai nomor resmi. Nilai asli database tidak diganti. Penolakan export penerimaan dengan nomor invalid tetap mengikuti pemeriksaan existing dan pesan aplikasi existing.
- Header tabel berulang dan baris tidak dipisah antarhalaman. Baris terakhir Permintaan Pembelian digabung dengan area tanda tangan agar tidak muncul halaman tanda tangan saja.
- Preview dan Download, authentication dan permission tetap menggunakan endpoint existing.

## Validasi

Seluruh suite: `php artisan test --compact` ? **108 test lulus, 1770 assertion**, durasi 19,84 detik pada run terakhir.

Test tambahan memeriksa peran tanda tangan PO, telepon supplier opsional, jatuh tempo tersimpan yang berbeda dari 30 hari, penjelasan termin, nominal invoice, placeholder historis berpenjelasan dan data tetap utuh. Pemeriksaan HTML nama pengguna disesuaikan dengan escaping Blade agar nama berapostrof tidak membuat test acak gagal.

Delapan contoh dirender dari database SQLite in-memory yang terisolasi, lalu diperiksa visual: empat PDF 1 item dan empat PDF 32 item, termasuk catatan panjang. Contoh memakai label CONTOH DATA UJI, bukan data transaksi asli. Tidak ada fixture yang ditulis ke database aplikasi. Penerimaan dan permintaan banyak item masing-masing 3 halaman; PO dan invoice masing-masing 2 halaman. Contoh 1 item masing-masing 1 halaman.

Seluruh halaman contoh dibuka sebagai gambar; tabel, pembungkusan catatan, footer, area tanda tangan dan penomoran halaman diperiksa. Ekstraksi teks memverifikasi semua SKU 1?32 tampil, ukuran semua halaman A4, dan total contoh PO/invoice Rp 220,89 untuk 1 item serta Rp 7.068,48 untuk 32 item.

## Contoh untuk evaluasi

| Dokumen | Satu item | Banyak item |
|---|---|---|
| Permintaan Pembelian | [1 item](storage/app/private/purchasing-stage-1/permintaan-pembelian-1-item.pdf) | [32 item](storage/app/private/purchasing-stage-1/permintaan-pembelian-banyak-item.pdf) |
| Purchase Order | [1 item](storage/app/private/purchasing-stage-1/purchase-order-1-item.pdf) | [32 item](storage/app/private/purchasing-stage-1/purchase-order-banyak-item.pdf) |
| Penerimaan Barang | [1 item](storage/app/private/purchasing-stage-1/penerimaan-barang-1-item.pdf) | [32 item](storage/app/private/purchasing-stage-1/penerimaan-barang-banyak-item.pdf) |
| Invoice Pembelian | [1 item](storage/app/private/purchasing-stage-1/invoice-pembelian-1-item.pdf) | [32 item](storage/app/private/purchasing-stage-1/invoice-pembelian-banyak-item.pdf) |

Preview PNG tiap halaman dan contact sheet tersedia pada folder yang sama, dengan nama `*-p1.png` dan `*-contact.png`.

## File berubah

Template diubah:

- resources/views/pdf/permintaan-pembelian.blade.php
- resources/views/pdf/purchase-order.blade.php
- resources/views/pdf/penerimaan-barang.blade.php
- resources/views/pdf/invoice-pembelian.blade.php

Partial layout/CSS Purchasing baru:

- resources/views/pdf/purchasing/layout.blade.php
- resources/views/pdf/purchasing/styles.blade.php
- resources/views/pdf/purchasing/number.blade.php
- resources/views/pdf/purchasing/order-lines.blade.php
- resources/views/pdf/purchasing/signatures.blade.php

Pengujian/dokumentasi:

- tests/Feature/TransactionPdfTest.php
- PURCHASING_PDF_STAGE1.md
- Artefak contoh pada storage/app/private/purchasing-stage-1/

Tidak ada perubahan database/migration, controller, service transaksi, business rule, invoice otomatis, stok, permission atau data historis. Tidak ada pengembangan Retur Pembelian pada tahap ini. Tahap berikutnya menunggu persetujuan pengguna.
