@can('access-route', $routeName)
@if (\Illuminate\Support\Facades\Route::has($routeName))
    <a class="sidebar-link {{ request()->routeIs($routeName, str_replace('.index', '.*', $routeName)) ? 'active' : '' }}" href="{{ route($routeName) }}" @if(request()->routeIs(str_replace('.index', '.*', $routeName))) aria-current="page" @endif><i class="bi bi-{{ $icon }}" aria-hidden="true"></i><span>{{ $label }}</span></a>
@else
    <button type="button" class="sidebar-link unavailable" disabled aria-label="{{ $label }} — segera tersedia"><i class="bi bi-{{ $icon }}" aria-hidden="true"></i><span>{{ $label }}</span><small>Segera</small></button>
@endif

@endcan
