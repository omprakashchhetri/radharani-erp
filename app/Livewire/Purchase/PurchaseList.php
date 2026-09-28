<?php
namespace App\Livewire\Purchase;

use App\Livewire\Concerns\WithDataTable;
use App\Models\Purchase\Purchase;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Purchase List / Payment Status — read view.
 *
 * CLAUDE.md rule 1 forbids ever updating a purchases row once written.
 * payment_status therefore cannot be changed here after entry (that would
 * be an UPDATE to a purchases row) — same conflict already flagged and
 * worked around the same way in Sales' Verification Queue. A real status
 * change needs a correction/new-row mechanism approved by an owner, which
 * doesn't exist in the schema yet, so the status shown here is exactly
 * what was recorded at New Purchase Entry and cannot be edited from this
 * screen.
 */
class PurchaseList extends Component
{
    use WithDataTable;

    #[Url(except: '')]
    public string $statusFilter = '';

    #[Url(except: '')]
    public string $typeFilter = '';

    protected function sortableColumns(): array
    {
        return [
            'vendor' => 'vendor_id',
            'amount' => 'total_amount',
            'created' => 'created_at',
        ];
    }

    protected function defaultSort(): array
    {
        return ['created', 'desc'];
    }

    protected function filterProperties(): array
    {
        return ['statusFilter', 'typeFilter'];
    }

    public function render()
    {
        $query = Purchase::with('vendor')
            ->when($this->statusFilter, fn ($q) => $q->where('payment_status', $this->statusFilter))
            ->when($this->typeFilter, fn ($q) => $q->where('type', $this->typeFilter))
            ->when($this->search, fn ($q) => $q->where('invoice_number', 'like', "%{$this->search}%")
                ->orWhereHas('vendor', fn ($v) => $v->where('name', 'like', "%{$this->search}%")));

        return view('livewire.purchase.purchase-list', [
            'purchases' => $this->applySorting($query)->paginate($this->perPageValue()),
            'stats' => [
                'total' => Purchase::count(),
                'finishedProduct' => Purchase::where('type', 'finished_product')->count(),
                'rawMaterial' => Purchase::where('type', 'raw_material')->count(),
                'totalSpend' => Purchase::sum('total_amount'),
            ],
        ])->layout('components.layouts.app', ['title' => 'Purchases — Radharani Jewellery']);
    }
}
