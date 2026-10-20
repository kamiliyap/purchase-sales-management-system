@extends('layouts.app')
@section('title', 'Customer')
@section('content')
<section class="page-heading"><div><span class="eyebrow">MASTER DATA</span><h1>Customer</h1><p>Kelola mitra pelanggan untuk mendukung penjualan bisnis Anda.</p></div><a href="{{ route('customer.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-2" aria-hidden="true"></i>Tambah Customer</a></section>
<section class="surface-card supplier-card" aria-labelledby="customer-list-title">
    <div class="list-toolbar"><div class="d-flex align-items-center gap-2"><h2 id="customer-list-title">Daftar Customer</h2><span class="count-badge">{{ $customers->count() }}</span></div><div class="search-field"><i class="bi bi-search" aria-hidden="true"></i><label class="visually-hidden" for="customer-search">Cari customer</label><input id="customer-search" class="form-control" type="search" placeholder="Cari nama, alamat, atau kontak..." autocomplete="off"></div></div>
    <div class="table-responsive"><table class="table supplier-table align-middle mb-0"><thead><tr><th scope="col">NO.</th><th scope="col">CUSTOMER</th><th scope="col">ALAMAT</th><th scope="col">TELEPON</th><th scope="col">EMAIL</th><th scope="col" class="text-end">AKSI</th></tr></thead><tbody>
    @forelse ($customers as $customer)
        <tr data-customer-row><td class="text-muted">{{ $loop->iteration }}</td><td><div class="supplier-identity"><span class="supplier-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($customer->nama_customer, 0, 1)) }}</span><a class="supplier-name" href="{{ route('customer.show', $customer->id_customer) }}">{{ $customer->nama_customer }}</a></div></td><td class="address-cell">{{ $customer->alamat }}</td><td class="text-nowrap">{{ $customer->telepon }}</td><td>{{ $customer->email }}</td><td><div class="table-actions"><a href="{{ route('customer.edit', $customer->id_customer) }}" class="btn btn-edit btn-sm" aria-label="Edit customer {{ $customer->nama_customer }}"><i class="bi bi-pencil-square me-1" aria-hidden="true"></i>Edit</a><form action="{{ route('customer.destroy', $customer->id_customer) }}" method="POST" data-confirm-delete="customer">@csrf @method('DELETE')<button type="submit" class="btn btn-delete btn-sm" aria-label="Hapus customer {{ $customer->nama_customer }}"><i class="bi bi-trash3 me-1" aria-hidden="true"></i>Hapus</button></form></div></td></tr>
    @empty
        <tr><td colspan="6"><div class="empty-state"><span><i class="bi bi-people" aria-hidden="true"></i></span><h3>Mulai dengan customer pertama</h3><p>Belum ada data customer. Tambahkan mitra pelanggan Anda.</p><a href="{{ route('customer.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Tambah Customer</a></div></td></tr>
    @endforelse
        <tr id="customer-no-results" hidden><td colspan="6" class="text-center py-5 text-muted">Tidak ada customer yang cocok dengan pencarian Anda.</td></tr>
    </tbody></table></div>
    <div class="list-footer"><span id="customer-result-count" role="status" aria-live="polite">{{ $customers->count() }} customer tercatat</span><span>Master Data / Customer</span></div>
</section>
@endsection
