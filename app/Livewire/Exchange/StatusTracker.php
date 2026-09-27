<?php
namespace App\Livewire\Exchange;

use App\Livewire\Concerns\WithDataTable;
use App\Models\Exchange\ExchangeTransaction;
use Livewire\Attributes\Url;
use Livewire\Component;

class StatusTracker extends Component
{
    use WithDataTable;

    #[Url(as: 'stage', except: '')]
    public string $stageFilter = '';

    public const STAGES = [
        'received' => 'Received',
        'melted' => 'Melted',
        'tested' => 'Tested',
        'valued' => 'Valued',
        'settled' => 'Settled',
    ];

    protected function sortableColumns(): array
    {
        return [
            'id' => 'exchange_transactions.id',
            'weight' => 'exchange_transactions.gross_weight',
            'stage' => 'exchange_transactions.stage',
            'updated' => 'exchange_transactions.updated_at',
        ];
    }

    protected function defaultSort(): array
    {
        return ['updated', 'desc'];
    }

    protected function filterProperties(): array
    {
        return ['stageFilter'];
    }

    public function render()
    {
        $query = ExchangeTransaction::query()
            ->with('customer')
            ->when($this->search, fn ($q) => $q->whereHas('customer', function ($c) {
                $c->where('name', 'like', "%{$this->search}%")
                    ->orWhere('phone', 'like', "%{$this->search}%");
            }))
            ->when($this->stageFilter, fn ($q) => $q->where('stage', $this->stageFilter));

        $query = $this->applySorting($query)->orderByDesc('exchange_transactions.id');

        return view('livewire.exchange.status-tracker', [
            'transactions' => $query->paginate($this->perPageValue()),
            'stats' => ExchangeTransaction::query()
                ->selectRaw('stage, count(*) as total')
                ->groupBy('stage')
                ->pluck('total', 'stage'),
        ])->layout('components.layouts.app', ['title' => 'Exchange — Status Tracker — Radharani Jewellery']);
    }
}
