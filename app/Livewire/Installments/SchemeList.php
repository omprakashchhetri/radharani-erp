<?php
namespace App\Livewire\Installments;

use App\Livewire\Concerns\WithDataTable;
use App\Models\Customer\InstallmentScheme;
use Livewire\Attributes\Url;
use Livewire\Component;

class SchemeList extends Component
{
    use WithDataTable;

    #[Url(except: '')]
    public string $statusFilter = '';

    protected function sortableColumns(): array
    {
        return [
            'amount' => 'monthly_amount',
            'started' => 'start_date',
            'status' => 'status',
        ];
    }

    protected function defaultSort(): array
    {
        return ['started', 'desc'];
    }

    protected function filterProperties(): array
    {
        return ['statusFilter'];
    }

    public function markStatus(int $id, string $status)
    {
        $scheme = InstallmentScheme::with('customer')->findOrFail($id);
        $scheme->update(['status' => $status]);
        $this->dispatch('toast', message: "{$scheme->customer->name}'s scheme marked ".ucfirst($status).'.', type: 'success');
    }

    public function render()
    {
        $query = InstallmentScheme::with('customer')
            ->when($this->statusFilter, fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->search, fn ($q) => $q->whereHas('customer', fn ($c) => $c->where('name', 'like', "%{$this->search}%")
                ->orWhere('phone', 'like', "%{$this->search}%")));

        return view('livewire.installments.scheme-list', [
            'schemes' => $this->applySorting($query)->paginate($this->perPageValue()),
            'stats' => [
                'active' => InstallmentScheme::where('status', 'active')->count(),
                'completed' => InstallmentScheme::where('status', 'completed')->count(),
                'defaulted' => InstallmentScheme::where('status', 'defaulted')->count(),
            ],
        ])->layout('components.layouts.app', ['title' => 'Installment Schemes — Radharani Jewellery ERP']);
    }
}
