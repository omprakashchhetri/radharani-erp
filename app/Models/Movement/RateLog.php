<?php
namespace App\Models\Movement;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/** One rate entry for a metal at a carat. Rates are only ever added, so the history stays. */
class RateLog extends Model
{
    public $timestamps = false;
    // $timestamps=false also disables Eloquent's default created_at
    // date-casting, so it needs an explicit cast or ->format() calls on it
    // (RateHistoryLog) fatal-error on a raw string.
    protected $casts = ['created_at' => 'datetime'];
    protected $fillable = ['metal', 'purity', 'rate', 'source', 'updated_by', 'created_at'];

    // The carats a rate is entered for, per metal (8 Oct change list, 11.1).
    // The one list of carats: item purity, purchases, karigar and hallmark forms, pricing rules and the exchange
    // presets all read it (ItemForm::PURITIES is built from it), so a new carat is added here and nowhere else.
    public const CARATS = [
        'gold' => ['24K', '22K', '18K', '14K', '9K'],
        'silver' => ['99.9', '92.5', 'Default'],   // "Default" is a label only (no fineness)
        'diamond' => ['24K', '22K', '18K', '14K', '9K'], // same details as gold; the piece is priced at its MRP, so no daily rate
    ];

    /** The carats that have a daily rate (diamond pieces carry their own MRP instead). */
    public static function rateCarats(): array
    {
        return array_diff_key(self::CARATS, ['diamond' => true]);
    }

    // Which carat stands for a metal where only one number fits (header chips, the website strip).
    private const HEADLINE = ['gold' => ['22K', '24K', '18K', '14K', '9K'], 'silver' => ['92.5', '99.9', 'Default']];

    protected static function booted()
    {
        static::creating(fn ($log) => $log->created_at ??= now());
    }

    private static function norm(?string $purity): ?string
    {
        $p = trim((string) $purity);

        return $p === '' ? null : strtoupper($p);
    }

    /** Latest rate for a metal at a carat. Without a carat, the headline carat's rate. */
    public static function latestFor(string $metal, ?string $purity = null): ?self
    {
        $purity = static::norm($purity);
        if ($purity === null) {
            return static::headline($metal);
        }

        return static::where('metal', $metal)->whereRaw('UPPER(purity) = ?', [$purity])->latest('created_at')->latest('id')->first();
    }

    public static function headline(string $metal): ?self
    {
        foreach (self::HEADLINE[$metal] ?? [] as $carat) {
            $log = static::latestFor($metal, $carat);
            if ($log && (float) $log->rate > 0) {
                return $log;
            }
        }

        // Rates from before per-carat entry have no carat.
        return static::where('metal', $metal)->whereNull('purity')->latest('created_at')->latest('id')->first();
    }

    /** The rate a piece is priced at: its own carat's, 0 when that carat has none. */
    public static function rateFor(string $metal, ?string $purity): float
    {
        return (float) (static::latestFor($metal, $purity ?: '-')?->rate ?? 0);
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
