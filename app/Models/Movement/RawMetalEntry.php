<?php
namespace App\Models\Movement;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * The raw-metal ledger (the Raw Material module). One row per movement of metal, signed (+ in, - out),
 * for a holder (the shop, a karigar, the refinery), a metal, a carat and a kind. Rows are only ever
 * added: a correction is a new, opposite row. Balances are always summed, never stored.
 * Post through App\Services\RawMaterialLedger, which also moves metal between holders as two rows.
 */
class RawMetalEntry extends Model
{
    protected $fillable = ['metal', 'purity', 'weight', 'holder_type', 'holder_id', 'kind', 'transfer_id', 'source_type', 'source_id', 'note', 'user_id'];

    public const HOLDERS = ['shop' => 'In shop', 'karigar' => 'With karigars', 'refinery' => 'At the refinery'];

    public const KINDS = [
        'refined' => 'Refined',
        'old_gold' => 'Old metal from exchange',
        'scrap' => 'Scrap',
    ];

    public const SOURCES = [
        'purchase' => 'Purchase',
        'exchange' => 'Exchange',
        'refinery_out' => 'Sent to refinery',
        'refinery_return' => 'Back from refinery',
        'karigar_advance' => 'Karigar advance',
        'karigar_payment' => 'Paid to karigar',
        'karigar_used' => 'Used in pieces',
        'karigar_return' => 'Metal back from karigar',
        'reclassify' => 'Reclassified',
        'adjustment' => 'Adjustment',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /** Carat key as stored: trimmed and upper-cased ("22k" and "22K" are one balance); blank is "-". */
    public static function normalizeCarat(?string $carat): string
    {
        $c = strtoupper(trim((string) $carat));

        return $c === '' ? '-' : $c;
    }

    /**
     * The carat a measured purity (in percent, e.g. 91.6) stands for. A purity close enough to a standard
     * carat takes its label (91.6 is 22K, 99.9 is 24K, 92.5 silver is 92.5). Anything else keeps its own
     * figure as "76.2%", which is what old gold usually is. Titanium has no percent, so it is "-".
     */
    public static function caratFor(string $metal, $percent): string
    {
        if (! is_numeric($percent) || (float) $percent <= 0) {
            return '-';
        }
        $p = (float) $percent;

        if ($metal === 'gold') {
            $karat = $p / 100 * 24;
            foreach (RateLog::CARATS['gold'] as $label) {
                if (abs($karat - (float) $label) <= 0.25) {
                    return $label;
                }
            }
        } elseif ($metal === 'silver' || $metal === 'platinum') {
            foreach (RateLog::CARATS[$metal] as $label) {
                $target = $metal === 'platinum' ? (float) $label / 10 : (float) $label; // platinum labels are parts per thousand
                if (abs($p - $target) <= 0.5) {
                    return $label;
                }
            }
        } else {
            return '-';
        }

        return rtrim(rtrim(number_format($p, 1, '.', ''), '0'), '.') . '%';
    }

    /** Fine metal in a weight at a carat label (22K, 99.9, 76.2%), or null when the label says nothing about purity. */
    public static function fineWeight(string $metal, string $carat, float $weight): ?float
    {
        $fraction = null;
        if (str_ends_with($carat, '%')) {
            $fraction = (float) $carat / 100;
        } elseif ($metal === 'gold' && preg_match('/^(\d+(\.\d+)?)K$/', $carat, $m)) {
            $fraction = (float) $m[1] / 24;
        } elseif ($metal === 'silver' && is_numeric($carat)) {
            $fraction = (float) $carat / 100;
        } elseif ($metal === 'platinum' && is_numeric($carat)) {
            $fraction = (float) $carat / 1000;
        }

        return $fraction === null ? null : round($weight * $fraction, 3);
    }

    /** Kept for the older call sites: metal into the shop, refined. New code uses RawMaterialLedger. */
    public static function record(string $metal, ?string $purity, float $weight, string $sourceType, ?int $sourceId = null, ?string $note = null): self
    {
        return static::create([
            'metal' => $metal,
            'purity' => static::normalizeCarat($purity),
            'weight' => $weight,
            'holder_type' => 'shop',
            'kind' => 'refined',
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'note' => $note,
            'user_id' => Auth::id(),
        ]);
    }

    /** What the shop has in hand to give or pay: shop, refined, by metal and carat. */
    public static function balances()
    {
        return static::selectRaw('metal, purity, SUM(weight) as weight')->where('holder_type', 'shop')->where('kind', 'refined')
            ->groupBy('metal', 'purity')->orderBy('metal')->orderBy('purity')->get();
    }

    public static function balanceFor(string $metal, ?string $purity): float
    {
        return (float) static::where('holder_type', 'shop')->where('kind', 'refined')
            ->where('metal', $metal)->where('purity', static::normalizeCarat($purity))->sum('weight');
    }
}
