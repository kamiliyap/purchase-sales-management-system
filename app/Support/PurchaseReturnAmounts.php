<?php

namespace App\Support;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Validation\ValidationException;

class PurchaseReturnAmounts
{
    public static function cents(mixed $value): int
    {
        return PurchasingAmounts::cents(is_float($value) ? number_format($value, 2, '.', '') : (string) $value);
    }

    private static function share(int $cents, int $part, int $whole): int
    {
        if ($whole <= 0) {
            throw ValidationException::withMessages(['items' => 'Dasar alokasi nominal PO tidak valid.']);
        }

        return BigDecimal::of($cents)->multipliedBy($part)->dividedBy($whole, 0, RoundingMode::HALF_UP)->toInt();
    }

    public static function line($line, int $quantity, int $poNet, int $poTax): array
    {
        $price = self::cents($line->harga_unit);
        $discount = self::share(self::cents($line->diskon), $quantity, $line->kuantitas);
        $grossAmount = BigDecimal::of($price)->multipliedBy($quantity);
        if ($price < 0 || $grossAmount->isGreaterThan(PurchasingAmounts::MAX)) {
            throw ValidationException::withMessages(['items' => 'Nominal retur melebihi kapasitas database.']);
        }
        $gross = $grossAmount->toInt();
        $net = $gross - $discount;
        if ($net < 0 || $gross > PurchasingAmounts::MAX) {
            throw ValidationException::withMessages(['items' => 'Nominal retur melebihi batas atau PO tidak valid.']);
        }
        $tax = $poTax === 0 ? 0 : self::share($poTax, $net, $poNet);
        if ($tax < 0 || $net + $tax > PurchasingAmounts::MAX) {
            throw ValidationException::withMessages(['items' => 'Total retur tidak valid.']);
        }

        return ['harga_unit' => PurchasingAmounts::decimal($price), 'diskon' => PurchasingAmounts::decimal($discount), 'subtotal' => PurchasingAmounts::decimal($net), 'ppn' => PurchasingAmounts::decimal($tax), 'total_harga' => PurchasingAmounts::decimal($net + $tax)];
    }
}
