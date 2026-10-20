<?php

namespace App\Casts;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

class SalesMoney implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }
        // SQLite returns REAL values; PHP's default float-to-string precision can
        // round a valid decimal(15,2) maximum up. MySQL DECIMAL stays a string.
        $decimal = is_float($value) ? number_format($value, 2, '.', '') : (string) $value;

        return (string) BigDecimal::of($decimal)->toScale(2, RoundingMode::HALF_UP);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        return $value;
    }
}
