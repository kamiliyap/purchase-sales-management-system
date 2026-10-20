<a href="{{ route($routeName.'.index') }}" class="back-link"><i class="bi bi-arrow-left me-2" aria-hidden="true"></i>Kembali ke {{ $title }}</a>
<section class="page-heading"><div><span class="eyebrow">PURCHASING</span><h1>{{ isset($document) ? 'Edit' : 'Tambah' }} {{ $title }}</h1><p>Lengkapi informasi dokumen pembelian Anda.</p></div></section>
<section class="surface-card form-card">
    <div class="card-heading"><div><h2>Informasi {{ $title }}</h2><p>Kolom bertanda <span class="text-danger">*</span> wajib diisi.</p></div><span class="stat-icon blue"><i class="bi bi-{{ $icon }}" aria-hidden="true"></i></span></div>
    <form method="POST" action="{{ isset($document) ? route($routeName.'.update', $document->getKey()) : route($routeName.'.store') }}">
        @csrf
        @isset($document) @method('PUT') @endisset
        @include('purchasing.partials.fields')
        <div class="form-actions"><a href="{{ route($routeName.'.index') }}" class="btn btn-outline-secondary">Batal</a><button class="btn btn-primary" type="submit"><i class="bi bi-check2 me-2" aria-hidden="true"></i>Simpan {{ $title }}</button></div>
    </form>
</section>
