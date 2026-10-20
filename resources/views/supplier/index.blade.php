@extends('layouts.app')
@section('title', 'Supplier')
@section('content')
<section class="page-heading"><div><span class="eyebrow">MASTER DATA</span><h1>Supplier</h1><p>Kelola mitra pemasok untuk mendukung pembelian bisnis Anda.</p></div><a href="{{ route('supplier.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-2" aria-hidden="true"></i>Tambah Supplier</a></section>
<section class="surface-card supplier-card" aria-labelledby="supplier-list-title">
    <div class="list-toolbar"><div class="d-flex align-items-center gap-2"><h2 id="supplier-list-title">Daftar Supplier</h2><span class="count-badge">{{ $suppliers->count() }}</span></div><div class="search-field"><i class="bi bi-search" aria-hidden="true"></i><label class="visually-hidden" for="supplier-search">Cari supplier</label><input id="supplier-search" class="form-control" type="search" placeholder="Cari nama, alamat, atau kontak..." autocomplete="off"></div></div>
    <div class="table-responsive"><table class="table supplier-table align-middle mb-0"><thead><tr><th scope="col">NO.</th><th scope="col">SUPPLIER</th><th scope="col">ALAMAT</th><th scope="col">TELEPON</th><th scope="col">EMAIL</th><th scope="col" class="text-end">AKSI</th></tr></thead><tbody>
    @forelse ($suppliers as $supplier)
        <tr data-supplier-row><td class="text-muted">{{ $loop->iteration }}</td><td><div class="supplier-identity"><span class="supplier-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($supplier->nama_supplier, 0, 1)) }}</span><span class="supplier-name">{{ $supplier->nama_supplier }}</span></div></td><td class="address-cell">{{ $supplier->alamat }}</td><td class="text-nowrap">{{ $supplier->telepon }}</td><td>{{ $supplier->email }}</td><td><div class="table-actions"><a href="{{ route('supplier.edit', $supplier->id_supplier) }}" class="btn btn-edit btn-sm" aria-label="Edit supplier {{ $supplier->nama_supplier }}"><i class="bi bi-pencil-square me-1" aria-hidden="true"></i>Edit</a><form action="{{ route('supplier.destroy', $supplier->id_supplier) }}" method="POST" data-confirm-delete>@csrf @method('DELETE')<button type="submit" class="btn btn-delete btn-sm" aria-label="Hapus supplier {{ $supplier->nama_supplier }}"><i class="bi bi-trash3 me-1" aria-hidden="true"></i>Hapus</button></form></div></td></tr>
    @empty
        <tr><td colspan="6"><div class="empty-state"><span><i class="bi bi-truck" aria-hidden="true"></i></span><h3>Mulai dengan supplier pertama</h3><p>Belum ada data supplier. Tambahkan mitra pemasok Anda.</p><a href="{{ route('supplier.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Tambah Supplier</a></div></td></tr>
    @endforelse
        <tr id="supplier-no-results" hidden><td colspan="6" class="text-center py-5 text-muted">Tidak ada supplier yang cocok dengan pencarian Anda.</td></tr>
    </tbody></table></div>
    <div class="list-footer"><span id="supplier-result-count" role="status" aria-live="polite">{{ $suppliers->count() }} supplier tercatat</span><span>Master Data / Supplier</span></div>
</section>
@endsection
