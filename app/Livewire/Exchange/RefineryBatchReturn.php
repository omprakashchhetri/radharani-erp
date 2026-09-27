<?php
namespace App\Livewire\Exchange;

use App\Livewire\Concerns\WithDataTable;
use App\Models\Exchange\RefineryBatch;
use Livewire\Component;

/**
 * Simple weight-out → refined-weight/purity-in round trip. Since more than
 * one batch can be outstanding (status = 'sent') at once, staff pick which
 * one this return is for instead of the component silently guessing —
 * defaults to the oldest outstanding batch (FIFO: whichever was sent
 * first is presumed back first), but any outstanding batch can be picked.
 */
class RefineryBatchReturn extends Component
{
    use WithDataTable;

    public ?int $batchId = null;
    public float $refinedWeight = 0;
    public float $refinedPurity = 0;

    protected function sortableColumns(): array
    {
        return [
            'id' => 'id',
            'weight' => 'refined_weight',
            'purity' => 'refined_purity',
            'returned' => 'returned_at',
        ];
    }

    protected function defaultSort(): array
    {
        return ['returned', 'desc'];
    }

    public function mount()
    {
        $this->batchId = RefineryBatch::where('status', 'sent')->oldest('sent_at')->value('id');
    }

    public function selectBatch(int $batchId): void
    {
        $this->batchId = $batchId;
    }

    public function submit()
    {
        $this->validate([
            'batchId' => 'required|exists:refinery_batches,id',
            'refinedWeight' => 'required|numeric|min:0.001',
            'refinedPurity' => 'required|numeric|min:0|max:100',
        ]);

        $batch = RefineryBatch::where('status', 'sent')->findOrFail($this->batchId);

        $batch->update([
            'refined_weight' => $this->refinedWeight,
            'refined_purity' => $this->refinedPurity,
            'status' => 'returned',
            'returned_at' => now(),
            'returned_by' => auth()->id(),
        ]);

        $this->dispatch('toast', message: "Return recorded for batch #{$batch->id} — {$this->refinedWeight}g at {$this->refinedPurity}%.", type: 'success');
        $this->reset(['refinedWeight', 'refinedPurity']);
        $this->batchId = RefineryBatch::where('status', 'sent')->oldest('sent_at')->value('id');
    }

    public function render()
    {
        $query = RefineryBatch::query()
            ->with('returner:id,name')
            ->where('status', 'returned')
            ->when($this->search, fn ($q) => $q->where('id', 'like', "%{$this->search}%"));

        $query = $this->applySorting($query)->orderByDesc('id');

        return view('livewire.exchange.refinery-batch-return', [
            'outstandingBatches' => RefineryBatch::where('status', 'sent')->oldest('sent_at')->get(),
            'returnedBatches' => $query->paginate($this->perPageValue()),
        ])->layout('components.layouts.app', ['title' => 'Refinery — Return — Radharani Jewellery']);
    }
}
