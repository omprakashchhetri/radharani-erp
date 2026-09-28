<?php
namespace App\Livewire\Purchase;

use App\Livewire\Concerns\WithDataTable;
use App\Models\Purchase\Vendor;
use Livewire\Attributes\Url;
use Livewire\Component;

class VendorManager extends Component
{
    use WithDataTable;

    #[Url(except: '')]
    public string $typeFilter = '';

    public bool $showForm = false;
    public ?int $editingId = null;

    public string $name = '';
    public string $type = 'karigar';
    public string $phone = '';
    public string $address = '';
    public string $balance = '0';

    protected function sortableColumns(): array
    {
        return [
            'name' => 'name',
            'type' => 'type',
            'balance' => 'balance',
        ];
    }

    protected function defaultSort(): array
    {
        return ['name', 'asc'];
    }

    protected function filterProperties(): array
    {
        return ['typeFilter'];
    }

    protected function rules(): array
    {
        return [
            'name' => 'required|string|max:100',
            'type' => 'required|in:karigar,supplier,hallmark_center',
            'phone' => 'nullable|string|max:15',
            'address' => 'nullable|string|max:255',
            'balance' => 'required|numeric',
        ];
    }

    public function create(): void
    {
        $this->resetValidation();
        $this->cancel();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $this->resetValidation();
        $v = Vendor::findOrFail($id);
        $this->editingId = $v->id;
        $this->name = $v->name;
        $this->type = $v->type;
        $this->phone = (string) $v->phone;
        $this->address = (string) $v->address;
        $this->balance = (string) $v->balance;
        $this->showForm = true;
    }

    public function getTotalPurchasedProperty(): float
    {
        if (! $this->editingId) {
            return 0;
        }

        return (float) Vendor::find($this->editingId)?->purchases()->sum('total_amount');
    }

    public function save(): void
    {
        $this->validate();

        Vendor::updateOrCreate(['id' => $this->editingId], [
            'name' => $this->name,
            'type' => $this->type,
            'phone' => $this->phone,
            'address' => $this->address,
            'balance' => $this->balance,
        ]);

        $message = $this->editingId ? 'Vendor updated.' : 'Vendor added.';
        $this->showForm = false;
        $this->cancel();
        $this->dispatch('toast', message: $message, type: 'success');
    }

    public function cancel(): void
    {
        $this->reset(['editingId', 'name', 'phone', 'address']);
        $this->type = 'karigar';
        $this->balance = '0';
    }

    public function render()
    {
        $query = Vendor::query()
            ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%")
                ->orWhere('phone', 'like', "%{$this->search}%"))
            ->when($this->typeFilter, fn ($q) => $q->where('type', $this->typeFilter));

        return view('livewire.purchase.vendor-manager', [
            'vendors' => $this->applySorting($query)->paginate($this->perPageValue()),
        ])->layout('components.layouts.app', ['title' => 'Vendors — Radharani Jewellery']);
    }
}
