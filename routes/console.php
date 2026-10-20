<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('user:access {email} {role} {--name=}', function () {
    $email = $this->argument('email');
    $role = $this->argument('role');
    $validator = \Illuminate\Support\Facades\Validator::make(
        ['email' => $email, 'role' => $role],
        ['email' => ['required', 'email', 'max:255'], 'role' => ['required', \Illuminate\Validation\Rule::in(\App\Support\RolePermissions::ROLES)]]
    );
    if ($validator->fails()) {
        $this->error($validator->errors()->first());
        return 1;
    }
    $user = \App\Models\User::where('email', $email)->first();
    if (! $user) {
        $name = $this->option('name') ?: $this->ask('Nama pengguna');
        $password = $this->secret('Password baru (minimal 12 karakter)');
        $confirmation = $this->secret('Ulangi password');
        $validator = \Illuminate\Support\Facades\Validator::make(
            ['name' => $name, 'password' => $password, 'password_confirmation' => $confirmation],
            ['name' => ['required', 'string', 'max:255'], 'password' => ['required', 'string', 'min:12', 'max:4096', 'confirmed']]
        );
        if ($validator->fails()) {
            $this->error($validator->errors()->first());
            return 1;
        }
        $user = new \App\Models\User(['name' => $name, 'email' => $email, 'password' => $password]);
    }
    $user->role = $role;
    $user->save();
    $this->info('Akses pengguna berhasil ditetapkan: '.$role);
    return 0;
})->purpose('Buat akun atau tetapkan role; password akun lama tidak diubah');

Artisan::command('user:password {email}', function () {
    $user = \App\Models\User::where('email', $this->argument('email'))->first();
    if (! $user) {
        $this->error('Akun tidak ditemukan.');
        return 1;
    }
    $password = $this->secret('Password baru (minimal 12 karakter)');
    $confirmation = $this->secret('Ulangi password');
    $validator = \Illuminate\Support\Facades\Validator::make(
        ['password' => $password, 'password_confirmation' => $confirmation],
        ['password' => ['required', 'string', 'min:12', 'max:4096', 'confirmed']]
    );
    if ($validator->fails()) {
        $this->error($validator->errors()->first());
        return 1;
    }
    $user->password = $password;
    $user->remember_token = \Illuminate\Support\Str::random(60);
    $user->save();
    $this->info('Password berhasil diperbarui.');
    return 0;
})->purpose('Ganti password akun melalui input tersembunyi');
