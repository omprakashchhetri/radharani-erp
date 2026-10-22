<?php
namespace App\Services;

use App\Models\Customer\Customer;
use App\Models\Customer\CustomerAdvance;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * A customer's advance: money paid in with no order behind it. The balance is the sum of its rows. Using it
 * locks the customer row and checks again, so the same money can never be spent from two screens.
 */
class AdvanceService
{
    public function balance(int $customerId): float
    {
        return round((float) CustomerAdvance::where('customer_id', $customerId)->sum('amount'), 2);
    }

    public function receive(int $customerId, float $amount, string $mode, ?string $occasion = null, ?string $note = null): CustomerAdvance
    {
        return CustomerAdvance::create([
            'customer_id' => $customerId, 'kind' => 'received', 'amount' => abs(round($amount, 2)), 'mode' => $mode,
            'occasion' => $occasion ?: null, 'note' => $note ?: null, 'user_id' => Auth::id(),
        ]);
    }

    /** Spend advance on a sale. Call inside the transaction that saves the sale. */
    public function useOnSale(int $customerId, int $saleId, float $amount): CustomerAdvance
    {
        return DB::transaction(function () use ($customerId, $saleId, $amount) {
            Customer::lockForUpdate()->findOrFail($customerId);
            $left = $this->balance($customerId);
            if ($amount <= 0 || $amount > $left + 0.005) {
                throw new InsufficientAdvance($left);
            }

            return CustomerAdvance::create([
                'customer_id' => $customerId, 'kind' => 'used', 'amount' => -round($amount, 2), 'sale_id' => $saleId,
                'note' => "Used on sale #{$saleId}", 'user_id' => Auth::id(),
            ]);
        });
    }
}
