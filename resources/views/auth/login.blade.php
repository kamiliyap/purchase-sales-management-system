<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login ? Purchase Sales</title>
    <link href="{{ asset('assets/vendor/bootstrap/bootstrap.min.css') }}" rel="stylesheet">
</head>
<body class="bg-light">
    <main class="container py-5">
        <div class="row justify-content-center"><div class="col-md-6 col-lg-4">
            <section class="card shadow-sm"><div class="card-body p-4">
                <h1 class="h4 mb-4">Login Purchase Sales</h1>
                @if ($errors->any())
                    <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
                @endif
                <form method="POST" action="{{ route('login.store') }}">
                    @csrf
                    <div class="mb-3"><label for="email" class="form-label">Email</label><input id="email" name="email" type="email" class="form-control" value="{{ old('email') }}" maxlength="255" autocomplete="username" required autofocus></div>
                    <div class="mb-4"><label for="password" class="form-label">Password</label><input id="password" name="password" type="password" class="form-control" autocomplete="current-password" required></div>
                    <button class="btn btn-primary w-100" type="submit">Login</button>
                </form>
            </div></section>
        </div></div>
    </main>
</body>
</html>
