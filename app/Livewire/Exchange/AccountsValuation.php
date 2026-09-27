<?php
namespace App\Livewire\Exchange;

use App\Livewire\Concerns\WithDataTable;
use App\Models\Exchange\ExchangeTransaction;
use Livewire\Component;

/**
 * Settles a final valuation against a real `exchange_transactions` row.
 * Only rows in stage 'tested' (purity readings done, deduction computed)
 * are ready to be valued — staff pick one of those instead of just
 * searching by customer, since a customer can have multiple outstanding
 * exchanges. Settling moves the row straight to 'settled' and stamps
 * settled_by/settled_at (judgment call carried over unchanged from the
 * original component: the doc doesn't require a separate "mark valued"
 * step before "settle").
 */
class AccountsValuation extends Component
{
    use WithDataTable;

    public ?int $settleTransactionId = null;
    public float $finalValue = 0;
    public bool $showSettle = false;

    protected function sortableColumns(): array
    {
        return [
            'id' => 'exchange_transactions.id',
            'weight' => 'exchange_transactions.deductable_weight',
            'purity' => 'exchange_transactions.purity_averaged',
            'updated' => 'exchange_transactions.updated_at',
        ];
    }

    protected function defaultSort(): array
    {
        return ['updated', 'desc'];
    }

    public function openSettle(int $transactionId): void
    {
        $this->resetValidation();
        $this->settleTransactionId = $transactionId;
        $this->finalValue = 0;
        $this->showSettle = true;
    }

    public function settle(): void
    {
        $this->validate([
            'settleTransactionId' => 'required|exists:exchange_transactions,id',
            'finalValue' => 'required|numeric|min:0.01',
        ]);

        $transaction = ExchangeTransaction::where('stage', 'tested')->findOrFail($this->settleTransactionId);

        $transaction->update([
            'final_value' => $this->finalValue,
            'stage' => 'settled',
            'settled_by' => auth()->id(),
            'settled_at' => now(),
        ]);

        $this->showSettle = false;
        $this->dispatch('toast', message: "Recorded ₹" . number_format($this->finalValue, 2) . " against {$transaction->customer->name}'s exchange (#{$transaction->id}).", type: 'success');
        $this->reset(['settleTransactionId', 'finalValue']);
    }

    public function render()
    {
        $query = ExchangeTransaction::query()
            ->with('customer')
            ->where('stage', 'tested')
            ->when($this->search, fn ($q) => $q->whereHas('customer', function ($c) {
                $c->where('name', 'like', "%{$this->search}%")
                    ->orWhere('phone', 'like', "%{$this->search}%");
            }));

        $query = $this->applySorting($query)->orderByDesc('exchange_transactions.id');

        return view('livewire.exchange.accounts-valuation', [
            'readyTransactions' => $query->paginate($this->perPageValue()),
            'readyCount' => ExchangeTransaction::where('stage', 'tested')->count(),
        ])->layout('components.layouts.app', ['title' => 'Exchange — Final Valuation — Radharani Jewellery']);
    }
}
