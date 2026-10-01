<?php
namespace App\Services;

use App\Models\Stock\Item;
use App\Models\Movement\RateLog;
use App\Models\Pricing\DiscountRule;

class PricingService
{
    // Price is never stored on the item — always computed live from the
    // latest rate log. This is what makes a rate update instantly
    // reprice every product with zero writes.
    public function priceFor(Item $item): float
    {
        return $this->breakdown($item)['total'];
    }

    // Same calculation as priceFor(), itemised so screens (e.g. Item Detail)
    // can show how the live price is built up. Provisional pending the
    // client's Excel sheet (see docs/REQUIREMENTS.md "Still open").
    public function breakdown(Item $item): array
    {
        // items.metal is authoritative; the purity-string guess only covers
        // legacy rows created before the metal column existed.
        $metal = $item->metal ?: (str_contains(strtolower((string) $item->purity), 'silver') ? 'silver' : 'gold');
        $rateLog = RateLog::latestFor($metal);
        $rate = (float) ($rateLog?->rate ?? 0);

        // Metal is charged on net weight (gross minus stones) when it's been
        // entered; otherwise the piece's recorded weight, as before.
        $weight = (float) ($item->net_weight ?: $item->weight);
        $base = $weight * $rate;

        // Three confirmed making-charge types (Requirement #10).
        $making = match ($item->making_type) {
            'percentage' => $base * ((float) $item->making_value / 100),
            'flat_per_gram' => (float) $item->making_value * $weight,
            default => (float) $item->making_value, // flat_per_piece (and legacy per_piece)
        };

        $huidCharge = $item->huid_code ? 45 : 0;
        $stoneValue = (float) $item->stone_value;

        $subtotal = round($base + $making + $stoneValue + $huidCharge, 2);
        $total = $subtotal;

        // Automatic discount rules (item > packet > box > category > weight
        // tier precedence) are applied here. This is separate from any
        // manual discount an accountant enters at checkout (sales.discount) —
        // that one stacks on top of this, applied in the Sales module.
        $rule = DiscountRule::bestFor($item);
        if ($rule) {
            $total = $rule->apply($subtotal);
        }

        return [
            'metal' => $metal,
            'rate' => $rate,
            'rate_at' => $rateLog?->created_at,
            'weight' => $weight,
            'metal_value' => round($base, 2),
            'making' => round($making, 2),
            'stone_value' => round($stoneValue, 2),
            'huid_charge' => $huidCharge,
            'subtotal' => $subtotal,
            'discount_rule' => $rule,
            'discount' => round($subtotal - $total, 2),
            'total' => $total,
        ];
    }
}
