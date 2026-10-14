<?php
namespace App\Livewire\Exchange;

use App\Models\Exchange\ExchangeTransaction;
use App\Services\ExchangeCredit;
use App\Services\InsufficientExchangeCredit;
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

    public bool $showCash = false;
    public $cashAmount = '';
    public string $cashNote = '';

    public function mount(ExchangeTransaction $transaction): void
    {
        $this->transaction = $transaction->load('customer', 'settler', 'creator');
    }

    public function openCash(): void
    {
        abort_unless(auth()->user()?->can('exchange.manage'), 403);
        $this->resetValidation();
        $this->cashAmount = (string) $this->transaction->creditRemaining();
        $this->cashNote = '';
        $this->showCash = true;
    }

    // The customer takes the value in cash instead of spending it: recorded so the credit never stays open.
    public function payCash(): void
    {
        abort_unless(auth()->user()?->can('exchange.manage'), 403);
        $this->validate(['cashAmount' => 'required|numeric|min:0.01', 'cashNote' => 'nullable|string|max:255'], [], ['cashAmount' => 'amount']);

        try {
            app(ExchangeCredit::class)->apply($this->transaction->id, 'cash', null, (float) $this->cashAmount, $this->cashNote ?: 'Paid out in cash');
        } catch (InsufficientExchangeCredit $e) {
            $this->addError('cashAmount', $e->getMessage());

            return;
        }

        $this->showCash = false;
        $this->dispatch('toast', message: '₹' . number_format((float) $this->cashAmount, 2) . ' recorded as paid out in cash.', type: 'success');
    }

    public function render()
    {
        return view('livewire.exchange.transaction-detail', [
            'credit' => $this->transaction->creditRemaining(),
            'applications' => $this->transaction->applications()->with('user:id,name')->latest('id')->get(),
        ])
            ->layout('components.layouts.app', ['title' => 'Exchange #' . $this->transaction->id . ' — Radharani Jewellery']);
    }
}
