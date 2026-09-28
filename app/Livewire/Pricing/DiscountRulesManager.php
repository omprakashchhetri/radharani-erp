<?php
namespace App\Livewire\Pricing;

use App\Livewire\Concerns\WithDataTable;
use App\Models\Pricing\DiscountRule;
use App\Models\Stock\Box;
use App\Models\Stock\Item;
use App\Models\Stock\Packet;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;

class DiscountRulesManager extends Component
{
    use WithDataTable;

    #[Url(except: '')]
    public string $scopeFilter = '';

    #[Url(except: '')]
    public string $activeFilter = '';

    // Add / edit modal
    public bool $showForm = false;
    public ?int $editingId = null;

    public string $scope = 'category';
    public ?int $scopeRefId = null;
    public string $category = '';
    public ?float $minWeight = null;
    public ?float $maxWeight = null;
    public string $discountType = 'percentage';
    public float $value = 0;
    public bool $active = true;
    public ?string $validFrom = null;
    public ?string $validTo = null;

    protected function sortableColumns(): array
    {
        return [
            'scope' => 'scope',
            'value' => 'value',
            'created' => 'created_at',
        ];
    }

    protected function defaultSort(): array
    {
        return ['created', 'desc'];
    }

    protected function filterProperties(): array
    {
        return ['scopeFilter', 'activeFilter'];
    }

    protected function rules(): array
    {
        return [
            'scope' => 'required|in:item,category,box,packet,weight_tier',
            'scopeRefId' => 'nullable|integer',
            'category' => 'nullable|string|max:50',
            'minWeight' => 'nullable|numeric|min:0',
            'maxWeight' => 'nullable|numeric|min:0',
            'discountType' => 'required|in:flat,percentage',
            'value' => 'required|numeric|min:0',
            'validFrom' => 'nullable|date',
            'validTo' => 'nullable|date',
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
        $r = DiscountRule::findOrFail($id);
        $this->editingId = $r->id;
        $this->scope = $r->scope;
        $this->scopeRefId = $r->scope_ref_id;
        $this->category = (string) $r->category;
        $this->minWeight = $r->min_weight;
        $this->maxWeight = $r->max_weight;
        $this->discountType = $r->discount_type;
        $this->value = $r->value;
        $this->active = $r->active;
        $this->validFrom = $r->valid_from?->toDateString();
        $this->validTo = $r->valid_to?->toDateString();
        $this->showForm = true;
    }

    public function save(): void
    {
        $data = $this->validate();

        DiscountRule::updateOrCreate(['id' => $this->editingId], [
            'scope' => $data['scope'],
            'scope_ref_id' => in_array($data['scope'], ['item', 'packet', 'box']) ? $data['scopeRefId'] : null,
            'category' => $data['scope'] === 'category' ? $data['category'] : null,
            'min_weight' => $data['scope'] === 'weight_tier' ? $data['minWeight'] : null,
            'max_weight' => $data['scope'] === 'weight_tier' ? $data['maxWeight'] : null,
            'discount_type' => $data['discountType'],
            'value' => $data['value'],
            'active' => $this->active,
            'valid_from' => $data['validFrom'] ?: null,
            'valid_to' => $data['validTo'] ?: null,
            'created_by' => $this->editingId ? DiscountRule::find($this->editingId)->created_by : Auth::id(),
        ]);

        $message = $this->editingId ? 'Discount rule updated.' : 'Discount rule created.';
        $this->showForm = false;
        $this->cancel();
        $this->dispatch('toast', message: $message, type: 'success');
    }

    public function deactivate(int $id): void
    {
        DiscountRule::findOrFail($id)->update(['active' => false]);
        $this->dispatch('toast', message: 'Discount rule deactivated.', type: 'success');
    }

    public function cancel(): void
    {
        $this->reset(['editingId', 'scopeRefId', 'category', 'minWeight', 'maxWeight', 'value', 'validFrom', 'validTo']);
        $this->scope = 'category';
        $this->discountType = 'percentage';
        $this->active = true;
    }

    public function render()
    {
        $query = DiscountRule::query()
            ->when($this->scopeFilter, fn ($q) => $q->where('scope', $this->scopeFilter))
            ->when($this->activeFilter !== '', fn ($q) => $q->where('active', $this->activeFilter === 'active'))
            ->when($this->search, fn ($q) => $q->where('category', 'like', "%{$this->search}%"));

        return view('livewire.pricing.discount-rules-manager', [
            'rules' => $this->applySorting($query)->paginate($this->perPageValue()),
            'items' => Item::orderByDesc('id')->limit(50)->get(),
            'packets' => Packet::orderBy('code')->get(),
            'boxes' => Box::orderBy('code')->get(),
            'stats' => [
                'total' => DiscountRule::count(),
                'active' => DiscountRule::where('active', true)->count(),
                'category' => DiscountRule::where('scope', 'category')->count(),
                'weightTier' => DiscountRule::where('scope', 'weight_tier')->count(),
            ],
        ])->layout('components.layouts.app', ['title' => 'Discount Rules — Radharani Jewellery']);
    }
}
