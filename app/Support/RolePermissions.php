<?php

namespace App\Support;

use App\Models\User;

class RolePermissions
{
    public const ROLES = ['Admin', 'Purchasing', 'Sales', 'Finance'];

    public static function allows(User $user, string $route): bool
    {
        if (! in_array($user->role, self::ROLES, true)) {
            return false;
        }
        if (in_array($route, ['invoice-pembelian.create', 'invoice-pembelian.store'], true)) {
            return false;
        }
        $route = preg_replace('/\.(preview-pdf|download-pdf)$/', '.show', $route);
        if ($user->role === 'Admin') {
            return true;
        }
        [$module, $action] = array_pad(explode('.', $route, 2), 2, 'index');
        if ($module === 'dashboard') {
            return true;
        }
        $modules = match ($user->role) {
            'Purchasing' => ['supplier', 'barang', 'permintaan-pembelian', 'purchase-order', 'penerimaan-barang', 'invoice-pembelian', 'retur-pembelian'],
            'Sales' => ['customer', 'barang', 'sales-order', 'invoice-penjualan', 'retur-penjualan'],
            'Finance' => ['invoice-pembelian', 'invoice-penjualan'],
            default => [],
        };

        return in_array($module, $modules, true)
            || ($user->role === 'Finance'
                && in_array($module, ['purchase-order', 'penerimaan-barang', 'retur-pembelian', 'sales-order', 'retur-penjualan'], true)
                && in_array($action, ['index', 'show'], true));
    }
}
