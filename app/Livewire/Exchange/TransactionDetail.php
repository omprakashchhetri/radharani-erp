<?php
namespace App\Livewire\Exchange;

use App\Models\Exchange\ExchangeTransaction;
use Livewire\Component;

/**
 * Read-only record of one exchange, step by step. Exists because none of
 * the values entered during the New Entry wizard (net weight, purity
 * readings, deduction, final value) are shown anywhere again once that
 * wizard is closed — Status Tracker only ever showed the current stage.
 * There's no per-step timestamp to build a real timeline from (only
 * created_at/updated_at/settled_at exist on the row), so this is laid
 * out as a step-by-step breakdown rather than a fabricated history feed.
 */
class TransactionDetail extends Component
{
    public ExchangeTransaction $transaction;

    public function mount(ExchangeTransaction $transaction): void
    {
        $this->transaction = $transaction->load('customer', 'settler', 'creator');
    }

    public function render()
    {
        return view('livewire.exchange.transaction-detail')
            ->layout('components.layouts.app', ['title' => 'Exchange #' . $this->transaction->id . ' — Radharani Jewellery']);
    }
}
