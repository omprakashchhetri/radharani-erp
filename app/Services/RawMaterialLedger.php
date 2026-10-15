<?php
namespace App\Services;

use App\Models\Movement\RawMetalEntry;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The only writer of the raw-metal ledger. Rows are never edited or deleted: metal comes in as one row,
 * leaves as one row, and moves between holders (shop, karigar, refinery) as two rows that share a
 * transfer_id, written together. A holder can never be taken below zero.
 *
 * A "leg" is ['holder' => 'shop'|'karigar'|'refinery', 'holder_id' => ?int, 'metal', 'carat', 'kind'].
 */
class RawMaterialLedger
{
    public const EPSILON = 0.0005;

    public static function leg(string $holder, ?int $holderId, string $metal, ?string $carat, string $kind): array
    {
        return ['holder' => $holder, 'holder_id' => $holderId, 'metal' => $metal, 'carat' => RawMetalEntry::normalizeCarat($carat), 'kind' => $kind];
    }

    public function shop(string $metal, ?string $carat, string $kind = 'refined'): array
    {
        return self::leg('shop', null, $metal, $carat, $kind);
    }

    public function balance(array $leg): float
    {
        return (float) $this->query($leg)->sum('weight');
    }

    /** Metal coming in from outside (a purchase, an exchange, an opening balance). */
    public function receive(array $to, float $weight, string $source, ?int $sourceId = null, ?string $note = null): RawMetalEntry
    {
        return $this->write($to, abs($weight), $source, $sourceId, $note, null);
    }

    /** Metal leaving (paid out, used up). Refused when the holder does not have it. */
    public function release(array $from, float $weight, string $source, ?int $sourceId = null, ?string $note = null): RawMetalEntry
    {
        return DB::transaction(function () use ($from, $weight, $source, $sourceId, $note) {
            $this->assertHas($from, abs($weight));

            return $this->write($from, -abs($weight), $source, $sourceId, $note, null);
        });
    }

    /**
     * Metal moving from one holder to another, as two rows with one transfer_id. The weight in can
     * differ from the weight out (a refinery loss); the difference stays visible in the ledger.
     *
     * @return array{0: RawMetalEntry, 1: RawMetalEntry}
     */
    public function transfer(array $from, array $to, float $weightOut, string $source, ?int $sourceId = null, ?string $note = null, ?float $weightIn = null): array
    {
        return DB::transaction(function () use ($from, $to, $weightOut, $weightIn, $source, $sourceId, $note) {
            $this->assertHas($from, abs($weightOut));
            $id = (string) Str::uuid();

            return [
                $this->write($from, -abs($weightOut), $source, $sourceId, $note, $id),
                $this->write($to, abs($weightIn ?? $weightOut), $source, $sourceId, $note, $id),
            ];
        });
    }

    /** Whether a source has already posted (so a settled exchange or a returned batch can not post twice). */
    public function hasPosted(string $source, int $sourceId): bool
    {
        return RawMetalEntry::where('source_type', $source)->where('source_id', $sourceId)->exists();
    }

    /** What a karigar still holds for one batch: the advance less what was used or given back. */
    public function heldForBatch(int $vendorId, int $batchId, string $metal, ?string $carat): float
    {
        return (float) RawMetalEntry::where('holder_type', 'karigar')->where('holder_id', $vendorId)->where('metal', $metal)
            ->where('purity', RawMetalEntry::normalizeCarat($carat))->where('kind', 'refined')
            ->whereIn('source_type', ['karigar_advance', 'karigar_used', 'karigar_return'])->where('source_id', $batchId)->sum('weight');
    }

    private function assertHas(array $leg, float $weight): void
    {
        $have = $this->balance($leg);
        if ($weight > $have + self::EPSILON) {
            throw new InsufficientRawMaterial($leg, $have);
        }
    }

    private function query(array $leg)
    {
        return RawMetalEntry::where('holder_type', $leg['holder'])
            ->when($leg['holder_id'], fn ($q, $id) => $q->where('holder_id', $id), fn ($q) => $q->whereNull('holder_id'))
            ->where('metal', $leg['metal'])->where('purity', $leg['carat'])->where('kind', $leg['kind']);
    }

    private function write(array $leg, float $signedWeight, string $source, ?int $sourceId, ?string $note, ?string $transferId): RawMetalEntry
    {
        return RawMetalEntry::create([
            'metal' => $leg['metal'],
            'purity' => $leg['carat'],
            'weight' => round($signedWeight, 3),
            'holder_type' => $leg['holder'],
            'holder_id' => $leg['holder_id'],
            'kind' => $leg['kind'],
            'transfer_id' => $transferId,
            'source_type' => $source,
            'source_id' => $sourceId,
            'note' => $note,
            'user_id' => Auth::id(),
        ]);
    }
}
