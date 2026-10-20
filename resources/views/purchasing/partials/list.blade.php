<section class="page-heading"><div><span class="eyebrow">PURCHASING</span><h1>{{ $title }}</h1><p>{{ $description }}</p></div>@can('access-route', $routeName.'.create')<a class="btn btn-primary" href="{{ route($routeName.'.create') }}"><i class="bi bi-plus-lg me-2" aria-hidden="true"></i>Tambah {{ $title }}</a>@endcan</section>
<section class="surface-card supplier-card">
    <div class="list-toolbar"><div class="d-flex align-items-center gap-2"><h2>Daftar {{ $title }}</h2><span class="count-badge">{{ $records->count() }}</span></div></div>
    <div class="table-responsive"><table class="table supplier-table align-middle mb-0"><thead><tr><th scope="col">NO.</th>@foreach($columns as $key => $label)<th scope="col">{{ $label }}</th>@endforeach<th scope="col" class="text-end">AKSI</th></tr></thead><tbody>
        @forelse($records as $record)
            <tr><td>{{ $loop->iteration }}</td>
                @foreach($columns as $key => $label)
                    <td @class(['text-nowrap' => in_array($key, $moneyFields ?? []) || str_contains($key, 'tanggal')])>
                        @if($loop->first)<a class="supplier-name" href="{{ route($routeName.'.show', $record->getKey()) }}">{{ data_get($record, $key) }}</a>
                        @elseif(in_array($key, $moneyFields ?? []))Rp {{ number_format(data_get($record, $key), 2, ',', '.') }}
                        @elseif($key === 'status')<span class="badge bg-primary-subtle text-primary-emphasis">{{ $record->status }}</span>
                        @else{{ data_get($record, $key) }}@endif
                    </td>
                @endforeach
                <td><div class="table-actions"><a class="btn btn-edit btn-sm" href="{{ route($routeName.'.show', $record->getKey()) }}">Detail</a>
                    @if(\Illuminate\Support\Facades\Route::has($routeName.'.edit') && \Illuminate\Support\Facades\Gate::allows('access-route', $routeName.'.edit'))<a class="btn btn-edit btn-sm" href="{{ route($routeName.'.edit', $record->getKey()) }}">Edit</a>@endif
                    @if(\Illuminate\Support\Facades\Route::has($routeName.'.destroy') && \Illuminate\Support\Facades\Gate::allows('access-route', $routeName.'.destroy'))<form action="{{ route($routeName.'.destroy', $record->getKey()) }}" method="POST" data-confirm-delete="{{ $title }}">@csrf @method('DELETE')<button type="submit" class="btn btn-delete btn-sm">Hapus</button></form>@endif
                </div></td>
            </tr>
        @empty
            <tr><td colspan="{{ count($columns) + 2 }}"><div class="empty-state"><span><i class="bi bi-{{ $icon }}" aria-hidden="true"></i></span><h3>Belum ada {{ $title }}</h3><p>Tambahkan dokumen pertama untuk memulai.</p>@can('access-route', $routeName.'.create')<a class="btn btn-primary btn-sm" href="{{ route($routeName.'.create') }}">Tambah {{ $title }}</a>@endcan</div></td></tr>
        @endforelse
    </tbody></table></div>
    <div class="list-footer"><span>{{ $records->count() }} dokumen tercatat</span><span>Purchasing / {{ $title }}</span></div>
</section>
