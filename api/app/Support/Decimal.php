<?php

namespace App\Support;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

final class Decimal
{
    public static function of(mixed $value): BigDecimal
    {
        if ($value === null || $value === '') {
            return BigDecimal::zero();
        }

        return BigDecimal::of((string) $value);
    }

    public static function money(BigDecimal|int|float|string $value): string
    {
        $decimal = $value instanceof BigDecimal ? $value : self::of($value);

        return (string) $decimal->toScale(2, RoundingMode::HalfUp);
    }

    public static function quantity(BigDecimal|int|float|string $value): string
    {
        $decimal = $value instanceof BigDecimal ? $value : self::of($value);

        return (string) $decimal->toScale(3, RoundingMode::HalfUp);
    }

    public static function percentage(BigDecimal|int|float|string $value): string
    {
        $decimal = $value instanceof BigDecimal ? $value : self::of($value);

        return (string) $decimal->toScale(4, RoundingMode::HalfUp);
    }
}
