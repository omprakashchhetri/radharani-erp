<?php
namespace App\Services;

use App\Models\Movement\RateLog;
use App\Models\Pricing\PricingRule;
use App\Models\Pricing\TaxSetting;
use App\Models\Stock\Item;
use Illuminate\Support\Collection;

class PricingService
{
    /** @var Collection<int, PricingRule>|null */
    private ?Collection $rules = null;

    // Price is never stored on the item: always computed live from the latest rate and the
    // current rules. This is what makes a rate update instantly reprice every product.
    public function priceFor(Item $item): float
    {
        return $this->breakdown($item)['total'];
    }

    /** Active rules, loaded once per request. Call after a rule changes in the same request. */
    public function forgetRules(): void
    {
        $this->rules = null;
    }

    private function rules(): Collection
    {
        return $this->rules ??= PricingRule::with('item:id,huid_code,internal_code')->where('active', true)->get()->filter->isCurrentlyValid()->values();
    }

    /** The most specific matching rule of a kind (a product's own setting is handled by the caller). */
    private function best(string $kind, Item $item, float $metalValue): ?PricingRule
    {
        return $this->rules()->where('kind', $kind)
            ->filter(fn (PricingRule $r) => $r->matches($item, $metalValue))
            ->sortByDesc(fn (PricingRule $r) => $r->rank() * 1000000 + $r->id)->first();
    }

    /** The GST rates a bill starts with (set by the owner): [jewellery %, hallmarking %]. */
    public function gstRates(): array
    {
        $t = TaxSetting::current();

        return [(float) $t->jewellery_percent, (float) $t->hallmark_percent];
    }

    /**
     * GST on one piece: the jewellery rate on metal, making and stones (less any discount rule), and the
     * hallmarking rate on the hallmarking charge. Additional charges are not taxed (the client's sheet).
     *
     * @param  array  $b  a breakdown()
     */
    public function gstOnItem(array $b, float $jewelleryPercent, float $hallmarkPercent): array
    {
        return $this->gstOnBill([$b], $jewelleryPercent, $hallmarkPercent);
    }

    /**
     * GST on a whole bill, worked piece by piece off each breakdown's taxable part. The bill's own extra
     * charges and its adjustment never enter it: both come after the GST (see billTotals()).
     *
     * @param  iterable<array>  $breakdowns  one breakdown() per piece
     * @return array{jewellery_base: float, hallmark_base: float, gst_jewellery: float, gst_hallmark: float, gst: float}
     */
    public function gstOnBill(iterable $breakdowns, float $jewelleryPercent, float $hallmarkPercent, float $reduceJewellery = 0.0): array
    {
        $jewellery = 0.0;
        $hallmark = 0.0;
        foreach ($breakdowns as $b) {
            $hallmark += (float) $b['huid_charge'];
            $jewellery += (float) ($b['taxable'] ?? max(0.0, (float) $b['total'] - (float) $b['huid_charge']));
        }

        $jewellery = max(0.0, $jewellery - $reduceJewellery); // the bill's adjustment comes off the making charge, so off the taxed part too
        $gj = round($jewellery * $jewelleryPercent / 100, 2);
        $gh = round($hallmark * $hallmarkPercent / 100, 2);

        return ['jewellery_base' => round($jewellery, 2), 'hallmark_base' => round($hallmark, 2), 'gst_jewellery' => $gj, 'gst_hallmark' => $gh, 'gst' => round($gj + $gh, 2)];
    }

    /**
     * The whole bill: pieces (metal, making less its discount, stones, hallmarking, their additional charges),
     * then the bill's adjustment, which comes off the MAKING CHARGE only (the client's rule: there is no profit
     * or loss on the metal, everything is on the making), so the GST falls with it. Then GST on the taxable
     * part, the bill's extra charges (untaxed) and the round-off to a whole rupee as a line of its own.
     * $adjustType: flat, percent (of the making charge), or final (the price to charge; the making charge is
     * reduced by what makes up the difference, GST included).
     *
     * @param  iterable<array>  $breakdowns
     * @return array{pieces: float, making: float, jewellery_base: float, hallmark_base: float, gst_jewellery: float, gst_hallmark: float, gst: float, cgst: float, sgst: float, extras: float, gross: float, round_off: float, rounded_gross: float, adjustment: float, total: float}
     */
    public function billTotals(iterable $breakdowns, float $extras, string $adjustType, float $adjustValue, float $jewelleryPercent, float $hallmarkPercent): array
    {
        $breakdowns = collect($breakdowns);
        $pieces = round($breakdowns->sum(fn ($b) => (float) $b['total']), 2);
        $making = round($breakdowns->sum(fn ($b) => (float) ($b['making_net'] ?? max(0.0, (float) $b['making'] - (float) ($b['discount'] ?? 0)))), 2);

        $withAdjustment = function (float $x) use ($breakdowns, $pieces, $extras, $jewelleryPercent, $hallmarkPercent) {
            $gst = $this->gstOnBill($breakdowns, $jewelleryPercent, $hallmarkPercent, $x);
            $gross = round($pieces - $x + $extras + $gst['gst'], 2);

            return [$gst, $gross, round($gross, 0)];
        };

        $adjustment = match ($adjustType) {
            'percent' => $making * min(100, max(0, $adjustValue)) / 100,
            'final' => max(0.0, $withAdjustment(0.0)[2] - max(0, $adjustValue)) / (1 + $jewelleryPercent / 100),
            default => max(0, $adjustValue),
        };
        $adjustment = round(min($making, max(0, $adjustment)), 2);

        [$gst, $gross, $rounded] = $withAdjustment($adjustment);
        $cgst = round($gst['gst'] / 2, 2);

        return $gst + [
            'pieces' => $pieces,
            'making' => $making,
            'cgst' => $cgst,
            'sgst' => round($gst['gst'] - $cgst, 2),
            'extras' => round($extras, 2),
            'gross' => $gross,
            'round_off' => round($rounded - $gross, 2),
            'rounded_gross' => $rounded,
            'adjustment' => $adjustment,
            'total' => $rounded,
        ];
    }

    /**
     * The price as lines for a screen: what it is, a short note on how it was worked out, and the amount
     * (a discount is negative). The lines add up to the breakdown's total.
     *
     * @return list<array{label: string, note: ?string, amount: float}>
     */
    public function breakupLines(array $b): array
    {
        $lines = [$b['metal'] === 'diamond'
            ? ['label' => 'Diamond (MRP)', 'note' => number_format($b['weight'], 3) . ' g', 'amount' => $b['metal_value']]
            : ['label' => ucfirst($b['metal']) . ' value', 'note' => number_format($b['weight'], 3) . ' g at ₹' . number_format($b['rate'], 2) . ' a gram', 'amount' => $b['metal_value']]];
        if ($b['making'] > 0) {
            $pct = $b['metal_value'] > 0 ? rtrim(rtrim(number_format($b['making'] / $b['metal_value'] * 100, 2), '0'), '.') . '% of the metal value' : null;
            $lines[] = ['label' => 'Making charge', 'note' => trim(($b['making_rule']?->describe() ? $b['making_rule']->describe() . ' · ' : '') . $pct, ' ·'), 'amount' => $b['making']];
        }
        if ($b['stone_value'] > 0) {
            $d = $b['diamond'] ?? null;
            $lines[] = $d
                ? ['label' => 'Diamond', 'note' => trim(rtrim(rtrim(number_format($d['carat'], 3), '0'), '.') . ' ct' . ($d['cut'] ? ' · ' . $d['cut'] : '')), 'amount' => $b['stone_value']]
                : ['label' => 'Stones', 'note' => null, 'amount' => $b['stone_value']];
        }
        if ($b['huid_charge'] > 0) {
            $lines[] = ['label' => 'Hallmarking', 'note' => $b['hallmark_rule']?->describe(), 'amount' => $b['huid_charge']];
        }
        foreach ($b['additional_lines'] as $a) {
            $lines[] = ['label' => $a['name'], 'note' => $a['rule']->describe(), 'amount' => $a['amount']];
        }
        if ($b['discount'] > 0) {
            $lines[] = ['label' => 'Discount on making charge', 'note' => $b['discount_rule']?->describe(), 'amount' => -$b['discount']];
        }

        return $lines;
    }

    // Itemised so screens can show how the live price is built up. Provisional pending the
    // client's own Excel sheet (see docs/REQUIREMENTS.md "Still open").
    public function breakdown(Item $item): array
    {
        $metal = $item->metal ?: (str_contains(strtolower((string) $item->purity), 'silver') ? 'silver' : 'gold');
        $isDiamond = $metal === 'diamond';
        $rateLog = $isDiamond ? null : RateLog::latestFor($metal, $item->purity ?: '-');
        $rate = (float) ($rateLog?->rate ?? 0);

        // Metal is charged on net weight (gross minus stones) when entered, else the piece's weight.
        $weight = (float) ($item->net_weight ?: $item->weight);
        $base = $isDiamond ? (float) $item->mrp : $weight * $rate; // a diamond piece is priced at its MRP
        $metalValue = round($base, 2); // the price range for every rule is measured on this alone

        // Making charge: a value set on the piece itself is its own product-level setting;
        // otherwise the most specific rule (product, category, price range) decides.
        $makingRule = null;
        if ((float) $item->making_value > 0) {
            $making = match ($item->making_type) {
                'percentage' => $base * ((float) $item->making_value / 100),
                'flat_per_gram' => (float) $item->making_value * $weight,
                default => (float) $item->making_value,
            };
        } else {
            $makingRule = $this->best('making', $item, $metalValue);
            $making = $makingRule?->amount($weight, $metalValue) ?? 0.0;
        }

        // The hallmarking charge is for pieces that carry a HUID only (the client's sheet).
        $hallmarkRule = $item->huid_code ? $this->best('hallmark', $item, $metalValue) : null;
        $hallmark = $hallmarkRule?->amount($weight, $metalValue) ?? 0.0;

        // Additional charges can be several: the most specific rule for each named charge applies.
        $additionalLines = [];
        $this->rules()->where('kind', 'additional')->filter(fn (PricingRule $r) => $r->matches($item, $metalValue))
            ->groupBy(fn (PricingRule $r) => mb_strtolower((string) $r->name))
            ->each(function (Collection $group) use (&$additionalLines, $weight, $metalValue) {
                $r = $group->sortByDesc(fn (PricingRule $r) => $r->rank() * 1000000 + $r->id)->first();
                $additionalLines[] = ['name' => $r->name ?: 'Additional charge', 'amount' => $r->amount($weight, $metalValue), 'rule' => $r];
            });
        $additional = round(array_sum(array_column($additionalLines, 'amount')), 2);

        $stoneValue = (float) $item->stone_value;
        $subtotal = round($base + $making + $stoneValue + $hallmark + $additional, 2);

        $discountRule = $this->best('discount', $item, $metalValue);
        // A discount comes off the making charge only, never the metal (the client's rule).
        $discount = $discountRule ? min($making, $discountRule->amount($weight, $metalValue, $making)) : 0.0;

        return [
            'metal' => $metal,
            'rate' => $rate,
            'rate_at' => $rateLog?->created_at,
            'weight' => $weight,
            'metal_value' => $metalValue,
            'making' => round($making, 2),
            'making_rule' => $makingRule,
            'stone_value' => round($stoneValue, 2),
            'diamond' => $item->diamond_carat !== null ? ['carat' => (float) $item->diamond_carat, 'cut' => $item->diamond_cut] : null,
            'huid_charge' => round($hallmark, 2),
            'hallmark_rule' => $hallmarkRule,
            'additional' => $additional,
            'additional_lines' => $additionalLines,
            'subtotal' => $subtotal,
            'taxable' => round(max(0.0, $base + $making + $stoneValue - $discount), 2), // what the jewellery GST rate applies to
            'discount_rule' => $discountRule,
            'discount' => round($discount, 2),
            'making_net' => round($making - $discount, 2),
            'total' => round($subtotal - $discount, 2),
        ];
    }
}
