<?php
namespace App\Livewire\Stock;

use App\Livewire\Concerns\WithDataTable;
use App\Models\Movement\KarigarRawBatch;
use App\Models\Movement\RateLog;
use App\Models\Movement\RawMetalEntry;
use App\Models\Purchase\Vendor;
use App\Services\InsufficientRawMaterial;
use App\Services\RawMaterialLedger;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\CSV\Writer;

/**
 * Raw Material: the metal the shop holds that is not yet a finished piece. A balance by holder (the
 * shop, each karigar, the refinery), metal, carat and kind, and the append-only ledger behind it.
 * Purchases, exchanges, the refinery and the karigar desk post here through RawMaterialLedger; the
 * actions on this page are the owner/manager's own: an adjustment, a reclassification, and metal
 * a karigar did not use coming back.
 */
class RawMaterial extends Component
{
    use WithDataTable;

    #[Url(except: 'shop')]
    public string $holder = 'shop'; // shop | karigar | refinery

    #[Url(except: '')]
    public string $metal = '';

    #[Url(except: '')]
    public string $kind = '';

    #[Url(except: '')]
    public string $source = '';

    public bool $showAdjust = false;
    public bool $showReclass = false;
    public bool $showReturn = false;

    // adjust
    public string $adjustDirection = 'add';
    public string $adjustPurpose = 'adjustment'; // adjustment | opening (opening balances only add)
    public string $adjustMetal = 'gold';
    public string $adjustCarat = '22K';
    public string $adjustKind = 'refined';
    public $adjustWeight = '';
    public string $adjustNote = '';

    // reclassify
    public string $fromKey = '';
    public $reclassWeight = '';
    public string $reclassCarat = '';
    public string $reclassNote = '';

    // karigar return
    public string $heldKey = '';
    public $returnWeight = '';
    public string $returnNote = '';

    protected function sortableColumns(): array
    {
        return ['date' => 'raw_metal_entries.id', 'weight' => 'raw_metal_entries.weight', 'metal' => 'raw_metal_entries.metal'];
    }

    protected function defaultSort(): array
    {
        return ['date', 'desc'];
    }

    protected function filterProperties(): array
    {
        return ['metal', 'kind', 'source'];
    }

    public function mount(): void
    {
        abort_unless(Auth::user()?->can('stock.manage'), 403);
        if (! array_key_exists($this->holder, RawMetalEntry::HOLDERS)) {
            $this->holder = 'shop';
        }
    }

    public function setHolder(string $holder): void
    {
        if (array_key_exists($holder, RawMetalEntry::HOLDERS)) {
            $this->holder = $holder;
            $this->resetPage();
        }
    }

    private function canManage(): bool
    {
        return (bool) Auth::user()?->can('rawmaterial.manage');
    }

    public function openForm(string $form): void
    {
        abort_unless($this->canManage(), 403);
        $this->resetValidation();
        $this->reset(['adjustWeight', 'adjustNote', 'fromKey', 'reclassWeight', 'reclassCarat', 'reclassNote', 'heldKey', 'returnWeight', 'returnNote']);
        $this->closeForm();
        $this->adjustPurpose = $form === 'opening' ? 'opening' : 'adjustment';
        $this->adjustDirection = 'add';
        match ($form) {
            'adjust', 'opening' => $this->showAdjust = true,
            'reclassify' => $this->showReclass = true,
            'karigar' => $this->showReturn = true,
            default => null,
        };
    }

    public function closeForm(): void
    {
        $this->showAdjust = $this->showReclass = $this->showReturn = false;
    }

    public function updatedAdjustMetal(): void
    {
        $this->adjustCarat = (RateLog::CARATS[$this->adjustMetal] ?? ['-'])[0];
    }

    // ---------------------------------------------------------------- actions

    public function adjust(): void
    {
        abort_unless($this->canManage(), 403);
        $this->validate([
            'adjustDirection' => $this->adjustPurpose === 'opening' ? 'required|in:add' : 'required|in:add,remove',
            'adjustMetal' => 'required|in:' . implode(',', array_keys(RateLog::rateCarats())),
            'adjustCarat' => 'required|string|max:10',
            'adjustKind' => 'required|in:refined,old_gold,scrap',
            'adjustWeight' => 'required|numeric|min:0.001|max:99999',
            'adjustNote' => 'required|string|max:255',
        ], ['adjustNote.required' => 'Say why the balance is being changed.'], ['adjustWeight' => 'weight', 'adjustCarat' => 'carat', 'adjustNote' => 'note']);

        $ledger = app(RawMaterialLedger::class);
        $leg = $ledger->shop($this->adjustMetal, $this->adjustCarat, $this->adjustKind);
        $source = $this->adjustPurpose === 'opening' ? 'opening' : 'adjustment';
        try {
            $this->adjustDirection === 'add'
                ? $ledger->receive($leg, (float) $this->adjustWeight, $source, null, $this->adjustNote)
                : $ledger->release($leg, (float) $this->adjustWeight, $source, null, $this->adjustNote);
        } catch (InsufficientRawMaterial $e) {
            $this->addError('adjustWeight', $e->getMessage());

            return;
        }

        $this->closeForm();
        $this->dispatch('toast', message: $this->adjustPurpose === 'opening' ? 'Opening balance recorded.' : 'Adjustment recorded.', type: 'success');
    }

    public function reclassify(): void
    {
        abort_unless($this->canManage(), 403);
        $this->validate([
            'fromKey' => 'required|string',
            'reclassWeight' => 'required|numeric|min:0.001|max:99999',
            'reclassCarat' => 'required|string|max:10',
            'reclassNote' => 'required|string|max:255',
        ], ['fromKey.required' => 'Choose the old metal or scrap.', 'reclassCarat.required' => 'Choose the carat it is now.', 'reclassNote.required' => 'Say how it was tested, so there is a record.'],
            ['reclassWeight' => 'weight', 'reclassCarat' => 'carat', 'reclassNote' => 'note']);

        $from = collect($this->unrefinedStock())->firstWhere('key', $this->fromKey);
        if (! $from) {
            $this->addError('fromKey', 'That stock is not in the shop any more.');

            return;
        }

        $ledger = app(RawMaterialLedger::class);
        try {
            $ledger->transfer(
                $ledger->shop($from['metal'], $from['carat'], $from['kind']),
                $ledger->shop($from['metal'], $this->reclassCarat, 'refined'),
                (float) $this->reclassWeight, 'reclassify', null,
                "{$from['carat']} {$from['kind']} to {$this->reclassCarat} refined: {$this->reclassNote}",
            );
        } catch (InsufficientRawMaterial $e) {
            $this->addError('reclassWeight', $e->getMessage());

            return;
        }

        $this->closeForm();
        $this->dispatch('toast', message: 'Reclassified as refined. It can now be given to a karigar.', type: 'success');
    }

    public function returnFromKarigar(): void
    {
        abort_unless($this->canManage(), 403);
        $this->validate([
            'heldKey' => 'required|string',
            'returnWeight' => 'required|numeric|min:0.001|max:99999',
            'returnNote' => 'nullable|string|max:255',
        ], ['heldKey.required' => 'Choose the karigar and batch.'], ['returnWeight' => 'weight']);

        $held = collect($this->karigarHoldings())->firstWhere('key', $this->heldKey);
        if (! $held) {
            $this->addError('heldKey', 'That karigar no longer holds this metal.');

            return;
        }

        $ledger = app(RawMaterialLedger::class);
        try {
            $ledger->transfer(
                RawMaterialLedger::leg('karigar', $held['vendor_id'], $held['metal'], $held['carat'], 'refined'),
                $ledger->shop($held['metal'], $held['carat'], 'refined'),
                (float) $this->returnWeight, 'karigar_return', $held['batch_id'],
                "Back from {$held['karigar']}" . ($this->returnNote ? ": {$this->returnNote}" : ''),
            );
        } catch (InsufficientRawMaterial $e) {
            $this->addError('returnWeight', $e->getMessage());

            return;
        }

        $this->closeForm();
        $this->dispatch('toast', message: number_format((float) $this->returnWeight, 3) . ' g back in the shop.', type: 'success');
    }

    public function export()
    {
        $rows = $this->ledgerQuery()->orderByDesc('raw_metal_entries.id')->get();
        $names = $this->holderNames($rows);

        return response()->streamDownload(function () use ($rows, $names) {
            $w = new Writer();
            $w->openToFile('php://output');
            $w->addRow(Row::fromValues(['Date', 'Holder', 'Metal', 'Carat', 'Kind', 'Weight (g)', 'What', 'Note', 'By']));
            foreach ($rows as $r) {
                $w->addRow(Row::fromValues([
                    $r->created_at->format('Y-m-d H:i'), $names[$r->holder_type . ':' . $r->holder_id] ?? RawMetalEntry::HOLDERS[$r->holder_type],
                    $r->metal, $r->purity, RawMetalEntry::KINDS[$r->kind], (float) $r->weight,
                    RawMetalEntry::SOURCES[$r->source_type] ?? $r->source_type, (string) $r->note, $r->user?->name ?? '',
                ]));
            }
            $w->close();
        }, 'raw-material-ledger-' . now()->format('Y-m-d') . '.csv', ['Content-Type' => 'text/csv']);
    }

    // ---------------------------------------------------------------- data

    /** Old metal and scrap in the shop, as choices: [['key', 'metal', 'carat', 'kind', 'weight']]. */
    public function unrefinedStock(): array
    {
        return RawMetalEntry::selectRaw('metal, purity, kind, SUM(weight) as weight')->where('holder_type', 'shop')->whereIn('kind', ['old_gold', 'scrap'])
            ->groupBy('metal', 'purity', 'kind')->havingRaw('SUM(weight) > 0.0005')->orderBy('metal')->orderBy('purity')->get()
            ->map(fn ($r) => ['key' => "{$r->metal}|{$r->purity}|{$r->kind}", 'metal' => $r->metal, 'carat' => $r->purity, 'kind' => $r->kind, 'weight' => (float) $r->weight])->all();
    }

    /** What each karigar still holds, per batch: [['key', 'vendor_id', 'karigar', 'batch_id', 'batch', 'metal', 'carat', 'weight']]. */
    public function karigarHoldings(): array
    {
        $rows = RawMetalEntry::selectRaw('holder_id, source_id, metal, purity, SUM(weight) as weight')
            ->where('holder_type', 'karigar')->where('kind', 'refined')->whereIn('source_type', ['karigar_advance', 'karigar_used', 'karigar_return'])
            ->groupBy('holder_id', 'source_id', 'metal', 'purity')->havingRaw('SUM(weight) > 0.0005')->get();

        $vendors = Vendor::whereIn('id', $rows->pluck('holder_id'))->pluck('name', 'id');
        $batches = KarigarRawBatch::whereIn('id', $rows->pluck('source_id'))->get()->keyBy('id');

        return $rows->map(fn ($r) => [
            'key' => "{$r->holder_id}|{$r->source_id}|{$r->metal}|{$r->purity}",
            'vendor_id' => (int) $r->holder_id, 'karigar' => $vendors[$r->holder_id] ?? "Karigar #{$r->holder_id}",
            'batch_id' => (int) $r->source_id, 'batch' => $batches[$r->source_id]?->label ?? "Batch #{$r->source_id}",
            'metal' => $r->metal, 'carat' => $r->purity, 'weight' => (float) $r->weight,
        ])->sortBy('karigar')->values()->all();
    }

    private function holderNames($rows): array
    {
        $vendors = Vendor::whereIn('id', $rows->where('holder_type', 'karigar')->pluck('holder_id')->unique())->pluck('name', 'id');
        $out = [];
        foreach ($rows as $r) {
            $out[$r->holder_type . ':' . $r->holder_id] = match ($r->holder_type) {
                'karigar' => $vendors[$r->holder_id] ?? 'A karigar',
                'refinery' => 'Refinery batch #' . $r->holder_id,
                default => 'Shop',
            };
        }

        return $out;
    }

    private function ledgerQuery()
    {
        return RawMetalEntry::query()->with('user:id,name')->where('holder_type', $this->holder)
            ->when($this->metal !== '', fn ($q) => $q->where('metal', $this->metal))
            ->when($this->kind !== '', fn ($q) => $q->where('kind', $this->kind))
            ->when($this->source !== '', fn ($q) => $q->where('source_type', $this->source))
            ->when($this->search !== '', fn ($q) => $q->where(fn ($w) => $w->where('note', 'like', "%{$this->search}%")->orWhere('purity', 'like', "%{$this->search}%")));
    }

    public function render()
    {
        $entries = $this->applySorting($this->ledgerQuery())->paginate($this->perPageValue());

        $totals = RawMetalEntry::selectRaw('holder_type, SUM(weight) as weight')->groupBy('holder_type')->pluck('weight', 'holder_type');

        $balances = RawMetalEntry::selectRaw('holder_type, holder_id, metal, purity, kind, SUM(weight) as weight')
            ->where('holder_type', $this->holder)->groupBy('holder_type', 'holder_id', 'metal', 'purity', 'kind')
            ->havingRaw('ABS(SUM(weight)) > 0.0005')->orderBy('metal')->orderBy('purity')->get();

        return view('livewire.stock.raw-material', [
            'entries' => $entries,
            'names' => $this->holderNames($entries->getCollection()->concat($balances)),
            'balances' => $balances,
            'holdings' => $this->holder === 'karigar' ? $this->karigarHoldings() : [],
            'totals' => $totals,
            'unrefined' => $this->showReclass ? $this->unrefinedStock() : [],
            'held' => $this->showReturn ? $this->karigarHoldings() : [],
            'carats' => RateLog::rateCarats(),
            'metals' => array_keys(RateLog::rateCarats()),
            'canManage' => $this->canManage(),
        ])->layout('components.layouts.app', ['title' => 'Raw Material · Radharani Jewellery']);
    }
}
