<?php
namespace App\Livewire\Exchange;

use App\Livewire\Concerns\WithDataTable;
use App\Models\Exchange\RefineryBatch;
use App\Models\Movement\RateLog;
use App\Models\Movement\RawMetalEntry;
use App\Services\InsufficientRawMaterial;
use App\Services\RawMaterialLedger;
use Illuminate\Support\Facades\DB;
use App\Services\PhotoCompressionService;
use Livewire\Component;
use Livewire\WithFileUploads;

class RefineryBatchSend extends Component
{
    use WithFileUploads, WithDataTable;

    public string $origin = 'stock';   // stock (from the shop's raw material) | outside (brought in, not in the stock)
    public string $source = '';   // "metal|carat|kind" of the stock being sent
    public string $outsideMetal = 'gold';
    public string $outsideCarat = '';
    public string $outsideKind = 'old_gold';
    public string $outsideNote = '';
    public $weight = '';
    public $photo = null;

    protected function sortableColumns(): array
    {
        return [
            'id' => 'id',
            'weight' => 'weight',
            'status' => 'status',
            'sent' => 'sent_at',
        ];
    }

    protected function defaultSort(): array
    {
        return ['sent', 'desc'];
    }

    /** Old metal and scrap in the shop that can go to the refinery: [['key', 'metal', 'carat', 'kind', 'available']]. */
    public function sendable(): array
    {
        return RawMetalEntry::selectRaw('metal, purity, kind, SUM(weight) as weight')
            ->where('holder_type', 'shop')->whereIn('kind', ['old_gold', 'scrap'])
            ->groupBy('metal', 'purity', 'kind')->havingRaw('SUM(weight) > 0.0005')->orderBy('metal')->orderBy('purity')->get()
            ->map(fn ($r) => ['key' => "{$r->metal}|{$r->purity}|{$r->kind}", 'metal' => $r->metal, 'carat' => $r->purity, 'kind' => $r->kind, 'available' => (float) $r->weight])
            ->all();
    }

    public function sendAll(): void
    {
        $row = collect($this->sendable())->firstWhere('key', $this->source);
        $this->weight = $row ? (string) $row['available'] : '';
    }

    public function submit()
    {
        $outside = $this->origin === 'outside';
        $rules = [
            'weight' => 'required|numeric|min:0.001',
            'photo' => 'required|image|max:5120',
        ];
        $rules += $outside
            ? [
                'outsideMetal' => 'required|in:' . implode(',', array_keys(RateLog::rateCarats())),
                'outsideCarat' => 'required|string|max:10',
                'outsideKind' => 'required|in:old_gold,scrap',
                'outsideNote' => 'required|string|max:255',
            ]
            : ['source' => 'required|string'];
        $this->validate($rules, [
            'source.required' => 'Choose which stock is being sent.',
            'outsideNote.required' => 'Say whose metal this is or where it came from.',
        ], ['outsideCarat' => 'carat', 'outsideNote' => 'note']);

        if ($outside) {
            $row = ['metal' => $this->outsideMetal, 'carat' => RawMetalEntry::normalizeCarat($this->outsideCarat), 'kind' => $this->outsideKind];
        } else {
            $row = collect($this->sendable())->firstWhere('key', $this->source);
            if (! $row) {
                $this->addError('source', 'That stock is not in the shop any more.');

                return;
            }
            if ((float) $this->weight > $row['available'] + RawMaterialLedger::EPSILON) {
                $this->addError('weight', 'Only ' . number_format($row['available'], 3) . ' g is available.');

                return;
            }
        }

        $path = app(PhotoCompressionService::class)->store($this->photo, 'refinery');
        $ledger = app(RawMaterialLedger::class);

        try {
            $batch = DB::transaction(function () use ($row, $path, $ledger, $outside) {
                $batch = RefineryBatch::create([
                    'metal' => $row['metal'],
                    'purity' => $row['carat'],
                    'kind' => $row['kind'],
                    'origin' => $outside ? 'outside' : 'stock',
                    'outside_note' => $outside ? $this->outsideNote : null,
                    'weight' => $this->weight,
                    'photo_path' => $path,
                    'status' => 'sent',
                    'sent_at' => now(),
                    'created_by' => auth()->id(),
                ]);
                $to = RawMaterialLedger::leg('refinery', $batch->id, $row['metal'], $row['carat'], $row['kind']);
                if ($outside) {
                    // Not from the shop's stock: it enters the refinery's holding directly, with the reason on the row.
                    $ledger->receive($to, (float) $this->weight, 'refinery_out', $batch->id, "Refinery batch #{$batch->id}, from outside: {$this->outsideNote}");
                } else {
                    $ledger->transfer($ledger->shop($row['metal'], $row['carat'], $row['kind']), $to, (float) $this->weight, 'refinery_out', $batch->id, "Refinery batch #{$batch->id}");
                }

                return $batch;
            });
        } catch (InsufficientRawMaterial $e) {
            $this->addError('weight', $e->getMessage());

            return;
        }

        $this->reset(['source', 'weight', 'photo', 'outsideCarat', 'outsideNote']);
        $this->dispatch('toast', message: "Batch #{$batch->id} of {$batch->weight}g recorded for send.", type: 'success');
    }

    public function render()
    {
        $query = RefineryBatch::query()
            ->when($this->search, fn ($q) => $q->where('id', 'like', "%{$this->search}%"));

        $query = $this->applySorting($query)->orderByDesc('id');

        return view('livewire.exchange.refinery-batch-send', [
            'batches' => $query->paginate($this->perPageValue()),
            'sendable' => $this->sendable(),
            'stats' => [
                'outstanding' => RefineryBatch::where('status', 'sent')->count(),
                'outstandingWeight' => (float) RefineryBatch::where('status', 'sent')->sum('weight'),
                'returned' => RefineryBatch::where('status', 'returned')->count(),
            ],
        ])->layout('components.layouts.app', ['title' => 'Refinery — Send — Radharani Jewellery']);
    }
}
