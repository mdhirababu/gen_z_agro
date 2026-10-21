<?php

namespace App\Support;

class Money
{
    /** ৳ ৫,০০০ বা ৳ ৫,০০০.৫০ (পয়সা থাকলে দেখায়) */
    public static function fmt(float|string|int|null $value): string
    {
        $value = (float) $value;

        return '৳ ' . number_format($value, abs($value - round($value)) < 0.005 ? 0 : 2);
    }
}
