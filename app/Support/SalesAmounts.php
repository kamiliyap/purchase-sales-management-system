<?php

namespace App\Support;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SalesAmounts
{
    public const MAX = 999999999999999;

    public static function rules(): array
    {
        $money = ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999999.99'];

        return [
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.id_barang' => ['required', 'integer', 'distinct', 'exists:m_barang,id_barang'],
            'items.*.kuantitas' => ['required', 'integer', 'min:1', 'max:2147483647'],
            'items.*.harga_jual' => $money,
            'items.*.diskon' => ['sometimes', ...$money],
        ];
    }

    public static function cents(string $value): int
    {
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');

        return (int) $whole * 100 + (int) str_pad($fraction, 2, '0');
    }

    public static function decimal(int $value): string
    {
        return intdiv($value, 100).'.'.str_pad((string) ($value % 100), 2, '0', STR_PAD_LEFT);
    }

    public static function calculate(array $items): array
    {
        Validator::make(['items' => $items], self::rules())->validate();
        $total = 0;
        foreach ($items as $index => &$item) {
            $price = self::cents((string) $item['harga_jual']);
            $discount = self::cents((string) ($item['diskon'] ?? 0));
            $qty = (int) $item['kuantitas'];
            if ($price > intdiv(self::MAX, $qty)) {
                throw ValidationException::withMessages(["items.$index.harga_jual" => 'Nilai baris barang melebihi kapasitas nominal database.']);
            }
            $gross = $price * $qty;
            if ($discount > $gross) {
                throw ValidationException::withMessages(["items.$index.diskon" => 'Diskon tidak boleh melebihi kuantitas ? harga jual.']);
            }
            $line = $gross - $discount;
            if ($total > self::MAX - $line) {
                throw ValidationException::withMessages(['items' => 'Total Sales Order melebihi kapasitas nominal database.']);
            }
            $total += $line;
            $item['harga_jual'] = self::decimal($price);
            $item['diskon'] = self::decimal($discount);
            $item['total_harga'] = self::decimal($line);
        }
        unset($item);

        return ['items' => $items, 'total_harga' => self::decimal($total)];
    }
}
