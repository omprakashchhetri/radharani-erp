<?php
namespace App\Services;

use App\Models\Customer\Customer;
use App\Models\Customer\LoyaltyTransaction;
use App\Models\Customer\LoyaltySetting;

// Loyalty is fully manual, by design (Requirement #15) — award/redeem are
// deliberate staff actions (LoyaltyAward screen, checkout redemption), never
// auto-calculated from a sale. There is intentionally no awardForSale()-style
// method here: that would auto-credit points on every sale, which is exactly
// what the spec forbids. Don't add one back without re-reading Requirement #15.
class LoyaltyService
{
    // Rates come from loyalty_settings (owner-editable via
    // LoyaltySettingsManager) instead of hardcoded constants — this is
    // exactly what makes them tunable without a code deploy.

    public function redeem(Customer $customer, int $points, ?int $saleId = null): bool
    {
        $settings = LoyaltySetting::current();

        if ($points < $settings->min_redeemable_points) return false;
        if ($customer->loyalty_points < $points) return false;

        $this->credit($customer, -$points, 'redemption', $saleId);
        return true;
    }

    // ₹ value of a given point count at today's settings — used to show
    // "your points are worth ₹X" on the customer portal and at checkout.
    public function rupeeValueOf(int $points): float
    {
        return round($points * LoyaltySetting::current()->point_value_in_rupees, 2);
    }

    private function credit(Customer $customer, int $points, string $reason, ?int $relatedSaleId): void
    {
        LoyaltyTransaction::create([
            'customer_id' => $customer->id,
            'points' => $points,
            'reason' => $reason,
            'related_sale_id' => $relatedSaleId,
        ]);

        $customer->increment('loyalty_points', $points);
    }
}
