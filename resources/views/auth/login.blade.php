<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | PurchaseSales</title>
    <link href="{{ asset('assets/vendor/bootstrap/bootstrap.min.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/css/login.css') }}" rel="stylesheet">
</head>
<body>
    <main class="login-layout">
        <aside class="login-branding" aria-label="Tentang PurchaseSales">
            <img class="login-branding-image" src="{{ asset('assets/images/purchasesales-login-branding.png') }}" alt="PurchaseSales — Purchasing &amp; Sales Management System: purchase management, sales management, inventory and stock control, reports and business insights." fetchpriority="high">
        </aside>
        <section class="login-panel" aria-labelledby="login-title">
            <div class="login-form-container">
                <p class="login-eyebrow">SELAMAT DATANG</p>
                <h1 id="login-title">Login Purchase Sales</h1>
                <p class="login-description">Masukkan email dan password untuk mengakses akun Anda.</p>
                @if ($errors->any())
                    <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
                @endif
                <form method="POST" action="{{ route('login.store') }}">
                    @csrf
                    <div class="mb-3"><label for="email" class="form-label">Email</label><input id="email" name="email" type="email" class="form-control" value="{{ old('email') }}" maxlength="255" autocomplete="username" required autofocus></div>
                    <div class="mb-4"><label for="password" class="form-label">Password</label><input id="password" name="password" type="password" class="form-control" autocomplete="current-password" required></div>
                    <button class="btn btn-primary w-100 login-submit" type="submit">Login</button>
                </form>
            </div>
        </section>
    </main>
</body>
</html>
