# Audit Export PDF Dokumen Transaksi

Tanggal: 19 Oktober 2026. Tahap audit saja, sebelum implementasi sesuai permintaan pengguna. Tidak ada dependensi dipasang, migration dijalankan, endpoint ditambahkan, atau business rule diubah pada tahap ini.

## Dependensi

- Project memakai Laravel ^12.0, runtime PHP 8.2.12, Composer 2.8.4.
- composer.json/composer.lock belum memuat barryvdh/laravel-dompdf atau dompdf/dompdf.
- Ekstensi dom, mbstring, gd, fileinfo dan XML tersedia.
- Metadata upstream wrapper menyatakan PHP ^8.1, illuminate/support mencakup ^12, dan dompdf/dompdf ^3.1. Secara persyaratan platform sesuai; versi rilis stabil dan resolusi seluruh dependency perlu diperiksa Composer saat implementasi, bukan memakai dev-master.
- Sumber: https://github.com/barryvdh/laravel-dompdf dan https://raw.githubusercontent.com/barryvdh/laravel-dompdf/master/composer.json
- Rencana memakai Blade terpisah per dokumen, layout PDF bersama A4 portrait, CSS tabel sederhana, header/footer, teks hitam dan latar putih. Aset lokal; remote resource dan embedded PHP tidak diperlukan. Cetak memakai inline PDF di browser (pengguna menjalankan Print), download memakai attachment.

## Audit data aktual

Daftar tabel diperiksa hanya pada database purchase_sales melalui information_schema dan Schema::getColumnListing. Hasil sesuai migration/model: tidak ada tabel pembayaran, verifikasi pembayaran, detail permintaan pembelian, atau profil perusahaan.

1. Permintaan Pembelian: no_faktur sebagai nomor existing, tanggal, tanggal_diperlukan, pemohon, penyetuju, status tersedia. Tidak ada detail SKU, nama barang, kuantitas atau catatan kebutuhan. PDF header dan area tanda tangan bisa dibuat dari data asli; form pengajuan barang terperinci belum dapat dipenuhi. Detail PO sesudahnya tidak boleh dianggap sebagai detail permintaan asli.
2. Purchase Order: supplier, nomor PO, tanggal, detail snapshot SKU/nama/kuantitas, harga_unit, diskon, total detail, PPN nominal, total_harga tersedia. Persentase PPN tidak disimpan sebagai field PO; tampilkan nominal PPN yang tersimpan. Status diturunkan dari relasi invoice. Nama penandatangan PO belum disimpan; area tanda tangan bisa kosong dengan label peran.
3. Penerimaan Barang: nomor/tanggal, PO, detail barang/kuantitas/kondisi/keterangan, penerima tersedia. SKU/nama menggunakan snapshot detail PO terkait, sebagaimana halaman penerimaan existing. Tidak ada harga/total pada penerimaan; PDF tidak perlu menambahkan nilai transaksi fiktif. Tidak ada file tanda tangan digital.
4. Invoice Pembelian: nomor/tanggal, jatuh tempo, total_invoice, status, dibuat_otomatis, supplier dan detail melalui PO tersedia. Nominal menggunakan total invoice tersimpan; jangan memperbarui nominal historis saat export.
5. Invoice Penjualan: nomor/tanggal, jatuh tempo, total_invoice, status tersedia. Customer dan detail snapshot berasal dari SalesOrder. Detail memiliki harga_jual, diskon dan total_harga. Tidak menambahkan PPN yang tidak disimpan pada alur Sales.
6. Kwitansi: belum ada pembayaran terverifikasi. Status Lunas pada kedua invoice hanya status pencatatan. Tidak tersedia nomor pembayaran/kwitansi, jumlah dibayar, tanggal pembayaran, metode, referensi/bukti pembayaran, verified_by atau verified_at. Kwitansi sah tidak dapat dibuat berdasarkan data sekarang.

Profil perusahaan: belum ada sumber terkonfigurasi untuk nama resmi, alamat, kontak, logo atau NPWP. APP_NAME/brand dashboard bukan bukti identitas perusahaan. Identitas perlu diberikan pengguna; rekomendasi config/company.php dan env, logo lokal opsional. Tidak memakai nama/alamat dummy.

## Permission yang direncanakan

Export harus mengikuti hak show dokumen dan seluruh endpoint memakai auth serta Gate/middleware existing. Jangan mengandalkan tombol.

| Dokumen | Admin | Purchasing | Sales | Finance |
|---|---|---|---|---|
| Permintaan Pembelian | Ya | Ya | Tidak | Tidak |
| Purchase Order | Ya | Ya | Tidak | Ya |
| Penerimaan Barang | Ya | Ya | Tidak | Ya |
| Invoice Pembelian | Ya | Ya | Tidak | Ya |
| Invoice Penjualan | Ya | Tidak | Ya | Ya |

Finance sekarang hanya mendapat index/show untuk PO dan penerimaan. Action export baru perlu disetarakan secara eksplisit ke permission show; menambahkan nama route pdf saja akan ditolak untuk Finance. Hak perubahan data tidak diperluas.

Kwitansi: jika tahap pembayaran disetujui, rekomendasi cetak hanya Admin/Finance setelah pembayaran terverifikasi. Matriks final dan siapa boleh verifikasi perlu diputuskan dahulu.

## Rencana implementasi yang dapat ditinjau

- Pasang wrapper DomPDF stabil dengan Composer, simpan lockfile.
- Controller/service export read-only menggunakan model binding dan eager loading relasi. Tidak mengambil nominal, HTML, template, URL aset atau path dokumen dari request.
- Template PDF per jenis dokumen dan shared layout/header/footer/signature. Tabel bergaris, rupiah, margin A4, nomor halaman dan header tabel pada halaman lanjutan.
- Tambah tombol Cetak PDF/Download PDF pada kelima halaman detail sesuai Gate, tanpa mengubah layout dashboard.
- Endpoint dokumen tidak ada menghasilkan 404; role ditolak 403; guest mengikuti login existing. Nilai frontend diabaikan. Dokumen lama dengan relasi/identitas hilang ditampilkan jujur atau export ditolak dengan pesan yang sesuai, tanpa mengganti identitas otomatis.
- Testing: kedua mode respons/application/pdf, nominal sesuai database termasuk invoice historis dan request nominal palsu, konten template, ID tidak valid/404, semua kombinasi role, guest, dan dokumen multi-halaman. Kwitansi perlu test penolakan pembayaran belum terverifikasi jika modulnya disetujui.
- Jalankan seluruh test project setelah implementasi. Pada tahap audit ini tidak ada pengujian fitur PDF karena implementasi belum dimulai.

## Keputusan yang diperlukan sebelum implementasi

1. Identitas perusahaan asli: nama resmi, alamat, telepon/email; logo dan NPWP bila diperlukan. Nama penandatangan PO/invoice dapat berupa area kosong berlabel peran, sementara nama pemohon/penyetuju/penerima memakai user tersimpan.
2. Permintaan Pembelian: pilih export data header yang tersedia dengan keterangan bahwa detail barang belum dicatat, atau setujui penambahan detail permintaan melalui migration/form/validation terpisah. Opsi kedua mengubah scope input Purchasing dan perlu persetujuan eksplisit. Jangan mengisi barang dari PO seolah data pengajuan asli.
3. Kwitansi: rekomendasi tunda sampai tersedia pembayaran terverifikasi, sambil menyelesaikan lima PDF dokumen yang lain. Alternatifnya setujui modul pembayaran minimal terlebih dahulu: kaitan invoice pembelian/penjualan, nominal, tanggal, metode, referensi/bukti, status verifikasi, pemeriksa dan waktu verifikasi, serta penomoran kwitansi. Perlu menentukan cakupan pembelian/penjualan, pembayaran parsial, dan role verifikator. Tidak menafsirkan Lunas sebagai verifikasi atau membangun alur pembayaran tanpa keputusan tersebut.
