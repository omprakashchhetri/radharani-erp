<?php
namespace App\Models\Sales;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * One row per Indian financial year (Apr–Mar), holding the next sequential
 * invoice number to hand out. GST law requires sequential, gap-free
 * numbering, so the real number can only be known at the moment a sale is
 * actually finalized — see SaleVerificationQueue::verify(), which assigns
 * it under a row lock alongside the one other allowed post-insert change,
 * confirmed_by_accountant.
 */
class InvoiceCounter extends Model
{
    protected $fillable = ['financial_year', 'next_number'];

    public static function financialYearFor(Carbon $date): string
    {
        $startYear = $date->month >= 4 ? $date->year : $date->year - 1;

        return $startYear.'-'.substr((string) ($startYear + 1), -2);
    }

    /**
     * Atomically reserves and returns the next invoice number for the
     * financial year the given date falls in, formatted "INV/2026-27/00001".
     */
    public static function nextFor(Carbon $date): string
    {
        $fy = static::financialYearFor($date);

        // Ensures the row exists before locking it — only ever contended on
        // the first invoice of a new financial year, once a year.
        static::firstOrCreate(['financial_year' => $fy], ['next_number' => 1]);

        return DB::transaction(function () use ($fy) {
            $counter = static::where('financial_year', $fy)->lockForUpdate()->first();
            $number = $counter->next_number;
            $counter->increment('next_number');

            return sprintf('INV/%s/%05d', $fy, $number);
        });
    }
}
