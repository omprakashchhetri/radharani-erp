<?php
namespace App\Services;

use App\Models\Exchange\ExchangeApplication;
use App\Models\Exchange\ExchangeTransaction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * The value of a settled exchange as the customer's credit. Applying credit locks the exchange row and
 * checks what is left again, so the same value can never be spent twice from two screens.
 */
class ExchangeCredit
{
    /** Settled exchanges of a customer with credit left, oldest first. Each has a `credit` attribute. */
    public function available(int $customerId): Collection
    {
        return ExchangeTransaction::where('customer_id', $customerId)->where('stage', 'settled')->oldest('settled_at')->get()
            ->each(fn ($x) => $x->setAttribute('credit', $x->creditRemaining()))
            ->filter(fn ($x) => $x->credit > 0)->values();
    }

    public function total(int $customerId): float
    {
        return round($this->available($customerId)->sum('credit'), 2);
    }

    /** Spend credit on a sale, an order or a cash payout. Call inside the transaction that saves the thing it pays for. */
    public function apply(int $exchangeId, string $kind, ?int $appliedId, float $amount, ?string $note = null): ExchangeApplication
    {
        return DB::transaction(function () use ($exchangeId, $kind, $appliedId, $amount, $note) {
            $tx = ExchangeTransaction::lockForUpdate()->findOrFail($exchangeId);
            $left = $tx->creditRemaining();
            if ($amount <= 0 || $amount > $left + 0.005) {
                throw new InsufficientExchangeCredit($exchangeId, $left);
            }

            return ExchangeApplication::create([
                'exchange_transaction_id' => $tx->id, 'kind' => $kind, 'applied_id' => $appliedId,
                'amount' => round($amount, 2), 'note' => $note, 'user_id' => Auth::id(),
            ]);
        });
    }

    /** Give back what an order took (it was cancelled): one negative row per exchange it drew on. Returns the total returned. */
    public function returnOrderCredit(int $orderId, ?string $note = null): float
    {
        return DB::transaction(function () use ($orderId, $note) {
            $returned = 0.0;
            $net = ExchangeApplication::where('kind', 'order')->where('applied_id', $orderId)
                ->selectRaw('exchange_transaction_id, SUM(amount) as amount')->groupBy('exchange_transaction_id')->get();
            foreach ($net as $row) {
                if ((float) $row->amount > 0) {
                    ExchangeApplication::create([
                        'exchange_transaction_id' => $row->exchange_transaction_id, 'kind' => 'order', 'applied_id' => $orderId,
                        'amount' => -round((float) $row->amount, 2), 'note' => $note ?? 'Order cancelled, credit returned', 'user_id' => Auth::id(),
                    ]);
                    $returned += (float) $row->amount;
                }
            }

            return round($returned, 2);
        });
    }

    /** What an order has drawn from exchange credit, net of anything returned. */
    public function orderCredit(int $orderId): float
    {
        return round((float) ExchangeApplication::where('kind', 'order')->where('applied_id', $orderId)->sum('amount'), 2);
    }
}
