<?php
namespace App\Livewire\Sales;

use App\Livewire\Concerns\WithDataTable;
use App\Models\Sales\Sale;
use Livewire\Attributes\Url;
use Livewire\Component;

class SalesHistory extends Component
{
    use WithDataTable;

    #[Url(except: '')]
    public string $status = ''; // '' | verified | reserved

    protected function sortableColumns(): array
    {
        return [
            'invoice' => 'invoice_number',
            'created' => 'created_at',
            'total' => 'total',
        ];
    }

    protected function defaultSort(): array
    {
        return ['created', 'desc'];
    }

    protected function filterProperties(): array
    {
        return ['status'];
    }

    public function render()
    {
        $query = Sale::with('customer')
            ->when($this->search, fn ($q) => $q
                ->where('invoice_number', 'like', "%{$this->search}%")
                ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$this->search}%")))
            ->when($this->status === 'verified', fn ($q) => $q->where('confirmed_by_accountant', true))
            ->when($this->status === 'reserved', fn ($q) => $q->where('confirmed_by_accountant', false));

        return view('livewire.sales.sales-history', [
            'sales' => $this->applySorting($query)->paginate($this->perPageValue()),
            'stats' => [
                'total' => Sale::count(),
                'verified' => Sale::where('confirmed_by_accountant', true)->count(),
                'reserved' => Sale::where('confirmed_by_accountant', false)->count(),
                'todayTotal' => Sale::whereDate('created_at', today())->sum('total'),
            ],
        ])->layout('components.layouts.app', ['title' => 'Sales History — Radharani Jewellery']);
    }
}
