<?php
namespace App\Support;

class Money
{
    // Whole rupees with Indian digit grouping (1,23,45,678), matching the
    // browser's toLocaleString('en-IN') used by the storefront scripts.
    public static function inr(float|int $amount): string
    {
        $n = (string) (int) round(abs($amount));
        $sign = $amount < 0 ? '-' : '';
        if (strlen($n) <= 3) {
            return $sign.$n;
        }
        $last3 = substr($n, -3);
        $rest = substr($n, 0, -3);

        return $sign.preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest).','.$last3;
    }
}
