<?php
namespace App\Livewire\Loyalty;

use App\Livewire\Concerns\WithDataTable;
use App\Models\Customer\LoyaltyTransaction;
use Livewire\Component;

class LoyaltyLedger extends Component
{
    use WithDataTable;

    protected function sortableColumns(): array
    {
        return [
            'created' => 'created_at',
            'points' => 'points',
        ];
    }

    protected function defaultSort(): array
    {
        return ['created', 'desc'];
    }

    public function render()
    {
        $query = LoyaltyTransaction::with('customer')
            ->when($this->search, fn ($q) => $q->whereHas('customer', fn ($c) => $c->where('name', 'like', "%{$this->search}%")
                ->orWhere('phone', 'like', "%{$this->search}%")));

        return view('livewire.loyalty.loyalty-ledger', [
            'transactions' => $this->applySorting($query)->paginate($this->perPageValue()),
            'stats' => [
                'earned' => LoyaltyTransaction::where('points', '>', 0)->sum('points'),
                'redeemed' => abs(LoyaltyTransaction::where('points', '<', 0)->sum('points')),
                'entries' => LoyaltyTransaction::count(),
            ],
        ])->layout('components.layouts.app', ['title' => 'Loyalty Ledger — Radharani Jewellery ERP']);
    }
}
