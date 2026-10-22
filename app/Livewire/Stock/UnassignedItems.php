<?php
namespace App\Livewire\Stock;

use App\Models\Stock\Item;
use App\Models\Stock\ItemCategory;
use App\Models\Stock\Packet;
use App\Support\CsvDownload;
use App\Support\StockExport;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Pieces that are in no packet yet (8 Oct change list, 4.2), grouped into batches by the
 * time they were entered. They are on hold: not counted as stock, not on the website and not
 * for sale until they are put in a packet. Any piece can be edited at any time (one by one or a
 * whole selection at once), and a wrong batch can be deleted; every change is logged through
 * the item's own history.
 */
class UnassignedItems extends Component
{
    public string $search = '';

    /** @var array<int, string> */
    public array $selected = [];

    #[Url(as: 'incomplete', except: false)]
    public bool $incompleteOnly = false;

    public bool $showMove = false;
    public bool $showEdit = false;
    public string $editMetal = '';
    public string $editPurity = '';
    public ?int $editCategoryId = null;
    public string $editMakingType = '';
    public $editMakingValue = '';
    public ?int $moveToPacketId = null;

    #[On('item-saved')]
    public function refreshList(): void {}

    public function openMove(): void
    {
        $this->resetValidation();
        $this->moveToPacketId = null;
        $this->showMove = true;
    }

    // One by one, not a mass update, so each move lands in the piece's history.
    public function moveSelected(): void
    {
        $this->validate(['moveToPacketId' => 'required|exists:packets,id,deleted_at,NULL'], ['moveToPacketId.required' => 'Choose a packet.'], ['moveToPacketId' => 'packet']);

        $items = Item::whereNull('packet_id')->whereIn('id', $this->selected)->get();
        foreach ($items as $item) {
            $item->update(['packet_id' => $this->moveToPacketId]);
        }

        $code = Packet::find($this->moveToPacketId)->code;
        $this->showMove = false;
        $this->selected = [];
        $this->dispatch('toast', message: "{$items->count()} piece(s) moved to {$code}.", type: 'success');
    }

    public function openEdit(): void
    {
        $this->resetValidation();
        $this->reset(['editMetal', 'editPurity', 'editCategoryId', 'editMakingType', 'editMakingValue']);
        $this->showEdit = true;
    }

    public function updatedEditMetal(): void
    {
        $this->editPurity = '';
        $this->editCategoryId = null;
    }

    // Fills in what was filled in and leaves the rest of each piece alone. One by one, so each change is in the piece's history.
    public function saveEdit(): void
    {
        abort_unless(Auth::user()?->can('stock.manage'), 403);
        $this->validate([
            'editMetal' => ['nullable', 'in:' . implode(',', array_keys(ItemForm::METALS))],
            'editPurity' => ['nullable', 'string', 'max:10'],
            'editCategoryId' => ['nullable', 'exists:item_categories,id'],
            'editMakingType' => ['nullable', 'in:percentage,flat_per_piece,flat_per_gram'],
            'editMakingValue' => ['nullable', 'numeric', 'min:0'],
        ]);

        $changes = array_filter([
            'metal' => $this->editMetal ?: null,
            'purity' => trim($this->editPurity) ?: null,
            'making_type' => $this->editMakingType ?: null,
        ], fn ($v) => $v !== null);
        if (is_numeric($this->editMakingValue) && $this->editMakingValue !== '') {
            $changes['making_value'] = (float) $this->editMakingValue;
        }
        $category = $this->editCategoryId ? ItemCategory::find($this->editCategoryId) : null;
        if ($category) {
            $changes += ['category' => $category->name, 'category_id' => $category->id, 'metal' => $category->metal];
        }
        if (! $changes) {
            $this->addError('editMetal', 'Fill in at least one thing to change.');

            return;
        }

        $items = Item::whereNull('packet_id')->whereIn('id', $this->selected)->get();
        foreach ($items as $item) {
            $metal = $changes['metal'] ?? $item->metal;
            $purity = $changes['purity'] ?? null;
            if ($purity !== null && ! in_array($purity, ItemForm::PURITIES[$metal] ?? [], true)) {
                $this->addError('editPurity', "{$purity} is not a purity for " . ($metal ?: 'a piece with no metal') . " ({$item->label}). Choose the metal as well.");

                return;
            }
        }
        foreach ($items as $item) {
            $item->update($changes);
        }

        $this->showEdit = false;
        $this->dispatch('toast', message: "{$items->count()} piece(s) updated.", type: 'success');
    }

    // Deletes (soft) what was entered by mistake. Sold pieces and pieces out of the store are never removed.
    public function deleteSelected(): void
    {
        abort_unless(Auth::user()?->can('stock.manage'), 403);

        $deleted = 0;
        $kept = [];
        foreach (Item::whereNull('packet_id')->whereIn('id', $this->selected)->get() as $item) {
            if ($item->deletionBlocker()) {
                $kept[] = $item->label;

                continue;
            }
            $item->delete();
            $deleted++;
        }

        $this->selected = [];
        $this->dispatch('toast', message: "{$deleted} piece(s) deleted." . ($kept ? ' Kept: ' . implode(', ', array_slice($kept, 0, 5)) . '.' : ''), type: $kept ? 'warning' : 'success');
    }

    public function toggleBatch(string $key, bool $on): void
    {
        $ids = $this->query()->get(['id', 'entry_batch_id', 'created_at'])
            ->filter(fn ($i) => $this->batchKey($i) === $key)->pluck('id')->map(fn ($id) => (string) $id)->all();

        $this->selected = $on
            ? array_values(array_unique(array_merge($this->selected, $ids)))
            : array_values(array_diff($this->selected, $ids));
    }

    // One batch as a CSV: every piece entered at that time, ready to open in Excel. Not limited to what the page shows.
    public function exportBatch(string $key)
    {
        $items = $this->query()->with('packet.box')->get()->filter(fn ($i) => $this->batchKey($i) === $key)->values();
        abort_if($items->isEmpty(), 404);

        return CsvDownload::stream('unassigned-batch-' . $items->first()->created_at->format('Ymd-Hi') . '.csv', StockExport::HEADER, $items->map(fn ($i) => StockExport::row($i)));
    }

    // Every batch on the page (and matching the search) in one file, with a column saying which batch each piece is from.
    public function exportAll()
    {
        $items = $this->query()->with('packet.box')->get();

        return CsvDownload::stream('unassigned-items-' . now()->format('Ymd-Hi') . '.csv', array_merge(['Batch'], StockExport::HEADER),
            $items->sortBy(fn ($i) => $i->created_at->format('YmdHi'))->map(fn ($i) => array_merge([$i->entry_batch_id ? 'Import ' . $i->created_at->format('j M Y, g:i a') : 'Entered ' . $i->created_at->format('j M Y, g:i a')], StockExport::row($i))));
    }

    private function query()
    {
        return Item::query()->whereNull('packet_id')->where('status', '!=', 'sold')
            ->when($this->incompleteOnly, fn ($q) => $q->where(fn ($q) => $q->whereNull('purity')->orWhere('purity', '')->orWhereNull('metal')->orWhereNull('category')->orWhere('category', '')->orWhere('weight', '<=', 0)))
            ->when($this->search, fn ($q) => $q->where(fn ($q) => $q
                ->where('huid_code', 'like', "%{$this->search}%")
                ->orWhere('internal_code', 'like', "%{$this->search}%")
                ->orWhere('category', 'like', "%{$this->search}%")
                ->orWhere('description', 'like', "%{$this->search}%")))
            ->orderByDesc('created_at')->orderByDesc('id');
    }

    // Pieces without a batch (added one by one) group by the minute they were entered.
    private function batchKey(Item $item): string
    {
        return $item->entry_batch_id ? 'b' . $item->entry_batch_id : 'm' . $item->created_at->format('YmdHi');
    }

    public function render()
    {
        $items = $this->query()->limit(600)->get();

        $batches = $items->groupBy(fn ($i) => $this->batchKey($i))->map(fn ($rows, $key) => [
            'key' => $key,
            'label' => $rows->first()->created_at->format('j M Y, g:i a'),
            'kind' => str_starts_with($key, 'b') ? 'Import' : 'Entered one by one',
            'rows' => $rows,
            'weight' => $rows->sum('weight'),
            'ids' => $rows->pluck('id')->map(fn ($id) => (string) $id)->all(),
        ])->values();

        return view('livewire.stock.unassigned-items', [
            'batches' => $batches,
            'editPurities' => ItemForm::PURITIES[$this->editMetal] ?? [],
            'editCategories' => $this->editMetal ? ItemCategory::active()->forMetal($this->editMetal)->orderBy('sort_order')->orderBy('name')->get(['id', 'name']) : collect(),
            'total' => $items->count(),
            'packets' => $this->showMove ? Packet::with('box:id,code')->orderBy('code')->get(['id', 'code', 'label', 'box_id'])->groupBy(fn ($p) => $p->box?->code ?? 'Not in a box') : collect(),
        ])->layout('components.layouts.app', ['title' => 'Unassigned items · Radharani Jewellery ERP']);
    }
}
