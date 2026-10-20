<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

class PurchasingAmounts
{
    public const MAX = 999999999999999;

    public static function cents(string $value): int
    {
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');

        return (int) $whole * 100 + (int) str_pad($fraction, 2, '0');
    }

    public static function decimal(int $cents): string
    {
        return intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }

    public static function calculate(array $items, string $rate): array
    {
        $subtotal = 0;
        $quantity = 0;
        foreach ($items as $index => &$item) {
            $unit = self::cents((string) $item['harga_unit']);
            $discount = self::cents((string) $item['diskon']);
            $qty = (int) $item['kuantitas'];
            if ($unit > intdiv(self::MAX, $qty)) {
                throw ValidationException::withMessages(["items.$index.harga_unit" => 'Nilai baris barang melebihi batas nominal yang dapat disimpan.']);
            }
            $gross = $unit * $qty;
            if ($discount > $gross) {
                throw ValidationException::withMessages(["items.$index.diskon" => 'Diskon tidak boleh melebihi kuantitas × harga unit.']);
            }
            $item['harga_unit'] = self::decimal($unit);
            $item['diskon'] = self::decimal($discount);
            $item['total_harga'] = self::decimal($gross - $discount);
            $subtotal += $gross - $discount;
            $quantity += $qty;
        }
        unset($item);
        if ($subtotal > self::MAX || $quantity > 2147483647) {
            throw ValidationException::withMessages(['items' => 'Total nominal atau jumlah barang melebihi batas yang dapat disimpan.']);
        }
        $basisPoints = self::cents($rate);
        $tax = intdiv($subtotal, 10000) * $basisPoints
            + intdiv(($subtotal % 10000) * $basisPoints + 5000, 10000);
        if ($subtotal + $tax > self::MAX) {
            throw ValidationException::withMessages(['ppn_rate' => 'Total termasuk PPN melebihi batas nominal yang dapat disimpan.']);
        }

        return [
            'items' => $items, 'jumlah' => $quantity,
            'subtotal' => self::decimal($subtotal), 'ppn' => self::decimal($tax),
            'total_harga' => self::decimal($subtotal + $tax),
        ];
    }
}
