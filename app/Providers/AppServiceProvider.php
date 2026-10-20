<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(\App\Services\DocumentNumberService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \Illuminate\Support\Facades\Gate::define('access-route',
            fn (\App\Models\User $user, string $route) => \App\Support\RolePermissions::allows($user, $route));

        \Illuminate\Support\Facades\Gate::define('approve-purchase-request',
            fn (\App\Models\User $user, int $approverId) => $user->role === 'Admin'
                || ($user->role === 'Purchasing' && (int) $user->getKey() === $approverId));

        foreach ([\Illuminate\Database\Events\TransactionCommitted::class, \Illuminate\Database\Events\TransactionRolledBack::class] as $event) {
            \Illuminate\Support\Facades\Event::listen($event, function ($transaction) {
                if ($transaction->connection->transactionLevel() === 0) {
                    app(\App\Services\DocumentNumberService::class)->releaseConnection($transaction->connection);
                }
            });
        }
    }
}
