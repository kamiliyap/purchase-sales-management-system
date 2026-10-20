<?php

namespace App\Support;

class PurchasingStatuses
{
    public const REQUEST = ['Draft', 'Diajukan', 'Disetujui', 'Ditolak'];
    public const ORDER_OPEN = 'Terbuka';
    public const ORDER_INVOICED = 'Diinvoice';
    public const INVOICE = ['Belum Lunas', 'Lunas'];
    public const RETURN = ['Diajukan', 'Disetujui', 'Ditolak', 'Selesai'];
    public const APPROVER_ROLES = ['Admin', 'Purchasing'];

    public static function options(array $statuses): array
    {
        return array_combine($statuses, $statuses);
    }
}
