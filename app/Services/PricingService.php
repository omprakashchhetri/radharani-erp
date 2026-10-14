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
     * GST on one piece: the jewellery rate on everything but the hallmarking charge (metal, making, stones and
     * additional charges, less the discount), and the hallmarking rate on the hallmarking charge.
     *
     * @param  array  $b  a breakdown()
     */
    public function gstOnItem(array $b, float $jewelleryPercent, float $hallmarkPercent): array
    {
        return $this->gstOnBill([$b], 0.0, 0.0, $jewelleryPercent, $hallmarkPercent);
    }

    /**
     * GST on a whole bill. The bill's extra charges are taxed at the jewellery rate and its adjustment
     * (discount) comes off the jewellery value, never the hallmarking charge.
     *
     * @param  iterable<array>  $breakdowns  one breakdown() per piece
     * @return array{jewellery_base: float, hallmark_base: float, gst_jewellery: float, gst_hallmark: float, gst: float}
     */
    public function gstOnBill(iterable $breakdowns, float $extras, float $adjustment, float $jewelleryPercent, float $hallmarkPercent): array
    {
        $jewellery = 0.0;
        $hallmark = 0.0;
        foreach ($breakdowns as $b) {
            $hallmark += (float) $b['huid_charge'];
            $jewellery += max(0.0, (float) $b['total'] - (float) $b['huid_charge']);
        }
        $jewellery = max(0.0, $jewellery + $extras - $adjustment);

        $gj = round($jewellery * $jewelleryPercent / 100, 2);
        $gh = round($hallmark * $hallmarkPercent / 100, 2);

        return ['jewellery_base' => round($jewellery, 2), 'hallmark_base' => round($hallmark, 2), 'gst_jewellery' => $gj, 'gst_hallmark' => $gh, 'gst' => round($gj + $gh, 2)];
    }

    /**
     * The price as lines for a screen: what it is, a short note on how it was worked out, and the amount
     * (a discount is negative). The lines add up to the breakdown's total.
     *
     * @return list<array{label: string, note: ?string, amount: float}>
     */
    public function breakupLines(array $b): array
    {
        $lines = [['label' => ucfirst($b['metal']) . ' value', 'note' => number_format($b['weight'], 3) . ' g at ₹' . number_format($b['rate'], 2) . ' a gram', 'amount' => $b['metal_value']]];
        if ($b['making'] > 0) {
            $lines[] = ['label' => 'Making charge', 'note' => $b['making_rule']?->describe(), 'amount' => $b['making']];
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
            $lines[] = ['label' => 'Discount', 'note' => $b['discount_rule']?->describe(), 'amount' => -$b['discount']];
        }

        return $lines;
    }

    // Itemised so screens can show how the live price is built up. Provisional pending the
    // client's own Excel sheet (see docs/REQUIREMENTS.md "Still open").
    public function breakdown(Item $item): array
    {
        $metal = $item->metal ?: (str_contains(strtolower((string) $item->purity), 'silver') ? 'silver' : 'gold');
        $rateLog = RateLog::latestFor($metal, $item->purity ?: '-');
        $rate = (float) ($rateLog?->rate ?? 0);

        // Metal is charged on net weight (gross minus stones) when entered, else the piece's weight.
        $weight = (float) ($item->net_weight ?: $item->weight);
        $base = $weight * $rate;
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

        $hallmarkRule = $this->best('hallmark', $item, $metalValue);
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
        $discount = $discountRule ? min($subtotal, $discountRule->amount($weight, $metalValue, $subtotal)) : 0.0;

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
            'discount_rule' => $discountRule,
            'discount' => round($discount, 2),
            'total' => round($subtotal - $discount, 2),
        ];
    }
}
