<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#3a57e8">
    <title>@yield('title', 'Dashboard') · Purchase Sales</title>
    <link href="{{ asset('assets/vendor/bootstrap/bootstrap.min.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/vendor/bootstrap-icons/font/bootstrap-icons.min.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/css/dashboard.css') }}" rel="stylesheet">
    @stack('styles')
</head>
<body>
    <a class="skip-link" href="#main-content">Lewati ke konten utama</a>
    <aside class="offcanvas-lg offcanvas-start app-sidebar" tabindex="-1" id="appSidebar" aria-labelledby="sidebarLabel">
        <div class="sidebar-brand">
            <a href="{{ route('dashboard') }}" class="brand-link" id="sidebarLabel">
                <span class="brand-mark"><i class="bi bi-intersect" aria-hidden="true"></i></span>
                <span>Purchase<span class="brand-secondary">Sales</span><small>BUSINESS WORKSPACE</small></span>
            </a>
            <button type="button" class="btn-close d-lg-none" data-bs-dismiss="offcanvas" data-bs-target="#appSidebar" aria-label="Tutup menu"></button>
        </div>
        <div class="sidebar-scroll">
            <nav aria-label="Navigasi utama">
                <p class="nav-heading">WORKSPACE</p>
                <a class="sidebar-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}" @if(request()->routeIs('dashboard')) aria-current="page" @endif><i class="bi bi-grid-1x2" aria-hidden="true"></i><span>Dashboard</span></a>
                <p class="nav-heading">MASTER DATA</p>
                @can('access-route', 'supplier.index')
                <a class="sidebar-link {{ request()->routeIs('supplier.*') ? 'active' : '' }}" href="{{ route('supplier.index') }}" @if(request()->routeIs('supplier.*')) aria-current="page" @endif><i class="bi bi-truck" aria-hidden="true"></i><span>Supplier</span></a>
                @endcan
                @foreach ([['Customer', 'people', 'customer.index'], ['Barang', 'box-seam', 'barang.index']] as [$label, $icon, $routeName])
                    @include('layouts.sidebar-item')
                @endforeach
                <p class="nav-heading">PURCHASING</p>
                @foreach ([['Permintaan Pembelian', 'clipboard-check', 'permintaan-pembelian.index'], ['Purchase Order', 'bag-check', 'purchase-order.index'], ['Penerimaan Barang', 'box-seam', 'penerimaan-barang.index'], ['Invoice Pembelian', 'receipt', 'invoice-pembelian.index'], ['Retur Pembelian', 'box-arrow-left', 'retur-pembelian.index']] as [$label, $icon, $routeName])
                    @include('layouts.sidebar-item')
                @endforeach
                <p class="nav-heading">SALES</p>
                @foreach ([['Sales Order', 'cart3', 'sales-order.index'], ['Invoice Penjualan', 'file-earmark-text', 'invoice-penjualan.index'], ['Retur Penjualan', 'arrow-return-left', 'retur-penjualan.index']] as [$label, $icon, $routeName])
                    @include('layouts.sidebar-item')
                @endforeach
            </nav>
            <div class="sidebar-note"><span class="note-icon"><i class="bi bi-layers" aria-hidden="true"></i></span><strong>Satu tempat, lebih teratur.</strong><p>Kelola pembelian dan penjualan bisnis Anda.</p></div>
        </div>
    </aside>

    <div class="app-main">
        <header class="app-topbar">
            <div class="d-flex align-items-center gap-3">
                <button type="button" class="icon-button d-lg-none" data-bs-toggle="offcanvas" data-bs-target="#appSidebar" aria-controls="appSidebar" aria-label="Buka menu"><i class="bi bi-list" aria-hidden="true"></i></button>
                <div><span class="topbar-eyebrow">Purchase Sales / Workspace</span><div class="topbar-title">@yield('title', 'Dashboard')</div></div>
            </div>
            <div class="topbar-right"><span class="workspace-badge"><span class="status-dot"></span>Business Workspace</span><span>{{ auth()->user()->name }} ? {{ auth()->user()->role }}</span><form method="POST" action="{{ route('logout') }}">@csrf<button type="submit" class="btn btn-outline-secondary btn-sm">Logout</button></form></div>
        </header>

        <main id="main-content" class="page-content" tabindex="-1">
            @if (session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="status"><i class="bi bi-check-circle me-2" aria-hidden="true"></i>{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup pemberitahuan"></button></div>
            @endif
            @if ($errors->any())
                <div class="alert alert-danger" role="alert"><strong>Periksa kembali data Anda.</strong><ul class="mb-0 mt-2">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
            @endif
            @yield('content')
        </main>
        <footer class="app-footer"><span>© {{ now('Asia/Jakarta')->year }} Purchase Sales</span><span>Purchase & sales management</span></footer>
    </div>

    <script src="{{ asset('assets/vendor/bootstrap/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ asset('assets/js/dashboard.js') }}" defer></script>
    @stack('scripts')
</body>
</html>
