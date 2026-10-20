@extends('layouts.app')

@section('title', 'Barang')

@section('content')
    <section class="page-heading">
        <div><span class="eyebrow">MASTER DATA</span><h1>Barang</h1><p>Kelola katalog barang, harga beli, dan stok bisnis Anda.</p></div>
        <a href="{{ route('barang.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-2" aria-hidden="true"></i>Tambah Barang</a>
    </section>
    <section class="surface-card supplier-card" aria-labelledby="barang-list-title">
        <div class="list-toolbar">
            <div class="d-flex align-items-center gap-2"><h2 id="barang-list-title">Daftar Barang</h2><span class="count-badge">{{ $barangs->count() }}</span></div>
            <div class="search-field"><i class="bi bi-search" aria-hidden="true"></i><label class="visually-hidden" for="barang-search">Cari barang</label><input id="barang-search" class="form-control" type="search" placeholder="Cari SKU, nama, atau satuan..." autocomplete="off"></div>
        </div>
        <div class="table-responsive">
            <table class="table supplier-table align-middle mb-0">
                <thead><tr><th scope="col">NO.</th><th scope="col">SKU</th><th scope="col">BARANG</th><th scope="col">SATUAN</th><th scope="col" class="text-end">HARGA BELI</th><th scope="col" class="text-end">STOK</th><th scope="col" class="text-end">AKSI</th></tr></thead>
                <tbody>
                    @forelse ($barangs as $barang)
                        <tr data-barang-row>
                            <td class="text-muted">{{ $loop->iteration }}</td>
                            <td class="text-break">{{ $barang->sku }}</td>
                            <td><div class="supplier-identity"><span class="supplier-avatar teal" aria-hidden="true"><i class="bi bi-box-seam"></i></span><a class="supplier-name" href="{{ route('barang.show', $barang->id_barang) }}">{{ $barang->nama_barang }}</a></div></td>
                            <td>{{ $barang->satuan }}</td>
                            <td class="text-end text-nowrap">Rp {{ number_format($barang->harga_beli, 2, ',', '.') }}</td>
                            <td class="text-end"><span class="badge {{ $barang->stok > 0 ? 'bg-success-subtle text-success-emphasis' : 'bg-warning-subtle text-warning-emphasis' }}">{{ number_format($barang->stok, 0, ',', '.') }}</span></td>
                            <td><div class="table-actions"><a href="{{ route('barang.edit', $barang->id_barang) }}" class="btn btn-edit btn-sm" aria-label="Edit barang {{ $barang->nama_barang }}"><i class="bi bi-pencil-square me-1" aria-hidden="true"></i>Edit</a><form action="{{ route('barang.destroy', $barang->id_barang) }}" method="POST" data-confirm-delete="barang">@csrf @method('DELETE')<button type="submit" class="btn btn-delete btn-sm" aria-label="Hapus barang {{ $barang->nama_barang }}"><i class="bi bi-trash3 me-1" aria-hidden="true"></i>Hapus</button></form></div></td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><div class="empty-state"><span><i class="bi bi-box-seam" aria-hidden="true"></i></span><h3>Mulai dengan barang pertama</h3><p>Belum ada data barang. Tambahkan barang ke katalog Anda.</p><a href="{{ route('barang.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Tambah Barang</a></div></td></tr>
                    @endforelse
                    <tr id="barang-no-results" hidden><td colspan="7" class="text-center py-5 text-muted">Tidak ada barang yang cocok dengan pencarian Anda.</td></tr>
                </tbody>
            </table>
        </div>
        <div class="list-footer"><span id="barang-result-count" role="status" aria-live="polite">{{ $barangs->count() }} barang tercatat</span><span>Master Data / Barang</span></div>
    </section>
@endsection
