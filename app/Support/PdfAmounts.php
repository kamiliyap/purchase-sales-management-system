<?php

namespace App\Support;

class PdfAmounts
{
    public static function money(mixed $value): string
    {
        $decimal = is_float($value) ? number_format($value, 2, '.', '') : (string) ($value ?? '0');
        [$whole, $fraction] = array_pad(explode('.', $decimal, 2), 2, '');

        return 'Rp '.number_format((int) $whole, 0, ',', '.').','.str_pad(substr($fraction, 0, 2), 2, '0');
    }

    public static function summary($details): array
    {
        $net = 0;
        $discount = 0;
        foreach ($details as $line) {
            $net += PurchasingAmounts::cents(is_float($line->total_harga) ? number_format($line->total_harga, 2, '.', '') : (string) $line->total_harga);
            $discount += PurchasingAmounts::cents(is_float($line->diskon) ? number_format($line->diskon, 2, '.', '') : (string) $line->diskon);
        }

        return ['subtotal' => PurchasingAmounts::decimal($net + $discount), 'diskon' => PurchasingAmounts::decimal($discount), 'net' => PurchasingAmounts::decimal($net)];
    }
}
