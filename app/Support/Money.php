<?php

namespace App\Support;

class Money
{
    public static function format(float|int|string|null $value): string
    {
        $normalized = is_string($value) ? str_replace(',', '', trim($value)) : $value;
        $number = is_numeric($normalized) ? (float) $normalized : 0.0;

        return number_format($number, 1, '.', ',');
    }
}
