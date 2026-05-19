<?php

namespace App\Support;

class Money
{
    public static function format(float|int|string|null $value): string
    {
        $number = is_numeric($value) ? (float) $value : 0.0;

        return number_format($number, 1, '.', ',');
    }
}
