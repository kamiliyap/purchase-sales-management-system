@extends('layouts.app')
@section('title', 'PDF Belum Dapat Dicetak')
@section('content')
<section class="page-heading"><div><span class="eyebrow">EXPORT PDF</span><h1>PDF belum dapat dicetak</h1><p>Data dokumen perlu ditinjau sebelum diterbitkan.</p></div></section>
<section class="surface-card form-card"><div class="alert alert-warning" role="alert">{{ $message }}</div><p>Nomor historis tidak diganti otomatis agar referensi transaksi tetap utuh. Minta Admin meninjau nomor pada dokumen sumber dan memastikan koreksi yang benar.</p><a class="btn btn-primary" href="{{ $documentUrl }}">Kembali ke Detail Dokumen</a></section>
@endsection
