<?php
namespace App\Livewire\Exchange;

use App\Livewire\Concerns\WithDataTable;
use App\Models\Exchange\ExchangeDeductionPreset;
use App\Models\Exchange\RefineryBatch;
use App\Models\Movement\RawMetalEntry;
use App\Services\InsufficientRawMaterial;
use App\Services\RawMaterialLedger;
use Illuminate\Support\Facades\DB;
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
    public $refinedWeight = '';
    public $refinedPurity = '';
    public $extraWeight = '';   // extra metal that came back with it, added to the shop's stock
    public string $extraNote = '';

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

    // Weight, purity and the deduction give the resulting figure, the way the exchange works it out:
    // the pure metal in the refined weight, less the deduction set for that metal and carat.
    public function calculation(?RefineryBatch $batch = null): array
    {
        $batch ??= $this->batchId ? RefineryBatch::find($this->batchId) : null;
        if (! $batch || ! is_numeric($this->refinedWeight) || ! is_numeric($this->refinedPurity)) {
            return ['fine' => 0.0, 'percent' => 0.0, 'result' => 0.0];
        }
        $fine = (float) $this->refinedWeight * (float) $this->refinedPurity / 100;
        $percent = ExchangeDeductionPreset::percentFor($batch->metal, (float) $this->refinedPurity);

        return ['fine' => round($fine, 3), 'percent' => $percent, 'result' => round($fine * (1 - $percent / 100), 3)];
    }

    public function submit()
    {
        $this->validate([
            'batchId' => 'required|exists:refinery_batches,id',
            'refinedWeight' => 'required|numeric|min:0.001',
            'refinedPurity' => 'required|numeric|min:0|max:100',
            'extraWeight' => 'nullable|numeric|min:0.001|max:99999',
            'extraNote' => 'nullable|string|max:255',
        ]);

        $batch = RefineryBatch::where('status', 'sent')->findOrFail($this->batchId);

        $calc = $this->calculation($batch);

        $ledger = app(RawMaterialLedger::class);
        try {
            DB::transaction(function () use ($batch, $calc, $ledger) {
                $batch->update([
                    'refined_weight' => $this->refinedWeight,
                    'refined_purity' => $this->refinedPurity,
                    'deduction_percent' => $calc['percent'],
                    'result_weight' => $calc['result'],
                    'status' => 'returned',
                    'returned_at' => now(),
                    'returned_by' => auth()->id(),
                    'extra_weight' => is_numeric($this->extraWeight) && (float) $this->extraWeight > 0 ? $this->extraWeight : null,
                ]);

                // Out of the refinery (what was sent), into the shop as refined metal (what came back). The difference is the loss.
                if (! $ledger->hasPosted('refinery_return', $batch->id)) {
                    $ledger->transfer(
                        RawMaterialLedger::leg('refinery', $batch->id, $batch->metal, $batch->purity, $batch->kind),
                        $ledger->shop($batch->metal, RawMetalEntry::caratFor($batch->metal, $this->refinedPurity), 'refined'),
                        (float) $batch->weight, 'refinery_return', $batch->id, "Refinery batch #{$batch->id}", (float) $this->refinedWeight,
                    );
                    // Extra metal from the refinery goes into the shop's refined stock on its own row.
                    if (is_numeric($this->extraWeight) && (float) $this->extraWeight > 0) {
                        $ledger->receive(
                            $ledger->shop($batch->metal, RawMetalEntry::caratFor($batch->metal, $this->refinedPurity), 'refined'),
                            (float) $this->extraWeight, 'refinery_return', $batch->id,
                            "Extra metal with refinery batch #{$batch->id}" . ($this->extraNote ? ": {$this->extraNote}" : ''),
                        );
                    }
                }
            });
        } catch (InsufficientRawMaterial $e) {
            $this->addError('refinedWeight', $e->getMessage());

            return;
        }

        $this->dispatch('toast', message: "Return recorded for batch #{$batch->id}: {$calc['result']} g after the deduction.", type: 'success');
        $this->reset(['refinedWeight', 'refinedPurity', 'extraWeight', 'extraNote']);
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
            'calc' => $this->calculation(),
            'outstandingBatches' => RefineryBatch::where('status', 'sent')->oldest('sent_at')->get(),
            'returnedBatches' => $query->paginate($this->perPageValue()),
        ])->layout('components.layouts.app', ['title' => 'Refinery — Return — Radharani Jewellery']);
    }
}
