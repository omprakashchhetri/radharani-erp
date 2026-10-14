<?php
namespace App\Livewire\Movement;

use App\Models\Location;
use App\Models\Movement\Movement;
use App\Models\Stock\Box;
use App\Models\Stock\Item;
use App\Models\Stock\Packet;
use App\Support\StockLookup;
use App\Support\Trackables;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * The morning / evening screen. Staff pick a direction, scan everything that
 * is moving (pieces, packets or whole boxes) into a tray, then confirm once.
 * Each tray line becomes its own vault_out / vault_in movement row.
 * In the evening the right-hand list shows what is still recorded on the
 * counter, so anything not scanned back is visible before confirming.
 */
class VaultCounterMove extends Component
{
    use \App\Livewire\Movement\Concerns\HasDoneBy;

    #[Url(except: 'to_counter')]
    public string $direction = 'to_counter'; // to_counter | to_vault

    // Where the tray is going (counters, displays, ...). Moving something already out to a different place is a place change.
    public ?int $locationId = null;

    public function mount(): void
    {
        $this->locationId = Location::defaultFloor()?->id;
    }

    // A different destination changes what counts as a place change, so start the tray again.
    public function updatedLocationId(): void
    {
        $this->reset(['tray', 'feedback']);
    }

    // Going back to the vault: optionally onto one of its trays (a tray is a place inside the vault).
    public ?int $vaultTrayId = null;

    public function updatedVaultTrayId(): void
    {
        $this->vaultTrayId = $this->vaultTrayId ?: null;
        $this->reset(['tray', 'feedback']);
    }

    // What is typed in the scan box, searched as well as scanned: pieces by code, HUID, category or description, and packets and boxes by code or label.
    public string $find = '';

    /** @var array<int, array{type: string, id: int, code: string, detail: string, weight: ?float, warning: ?string}> */
    public array $tray = [];

    /** @var array{tone: string, code: string, message: string}|null */
    public ?array $feedback = null;

    public function setDirection(string $direction): void
    {
        if (! in_array($direction, ['to_counter', 'to_vault'], true) || $direction === $this->direction) {
            return;
        }

        $this->direction = $direction;
        $this->reset(['tray', 'feedback']);
        $this->dispatch('scan-ready');
    }

    // The code arrives as an argument (the field is cleared in the browser on
    // Enter), so fast consecutive scans queue up instead of overwriting each other.
    public function scan(string $raw): void
    {
        $this->find = '';

        // Several at once: codes pasted or scanned together. Packet and box codes can hold spaces
        // ("GENTS RING"), so the whole input is tried as one code before it is ever split.
        $codes = StockLookup::splitCodes($raw, fn ($c) => $this->resolve(StockLookup::normalize($c))[1] ?? null);
        if (count($codes) > 1) {
            foreach ($codes as $one) {
                $this->scanOne($one);
            }
            $this->dispatch('scan-ready');

            return;
        }

        $this->scanOne($raw);
    }

    private function scanOne(string $raw): void
    {
        $code = StockLookup::normalize($raw);
        if ($code === '') {
            return;
        }

        $this->dispatch('scan-ready');

        [$type, $model] = $this->resolve($code);
        if (! $model) {
            $this->feedback('error', $code, 'Nothing found with this code.');
            return;
        }

        $this->consider($type, $model);
    }

    // Picked from the search results instead of scanned: it goes through exactly the same checks.
    public function addFromSearch(string $type, int $id): void
    {
        $model = match ($type) {
            'item' => Item::find($id),
            'packet' => Packet::find($id),
            'box' => Box::find($id),
            default => null,
        };
        $this->find = '';
        $this->dispatch('scan-ready');
        if (! $model) {
            return;
        }

        $this->consider($type, $model);
    }

    /** One scanned or picked piece, packet or box: checked, and put in the tray with a note when something is unusual. */
    private function consider(string $type, $model): void
    {
        $label = $type === 'item' ? $model->label : $model->code;

        if (collect($this->tray)->contains(fn ($t) => $t['type'] === $type && $t['id'] === $model->id)) {
            $this->feedback('info', $label, 'Already in the tray.');
            return;
        }

        if ($type === 'item' && ($blocked = $this->blockedReason($model))) {
            $this->feedback('error', $label, $blocked);
            return;
        }

        $last = $this->lastVaultMove($type, $model->id);
        $warning = null;

        $placeChange = false;
        if ($this->direction === 'to_counter' && $last?->movement_type === 'vault_out') {
            $at = $last->location_id ?? Location::defaultFloor()?->id;
            if ($at === $this->locationId) {
                $this->feedback('info', $label, 'Already at ' . (Location::find($at)?->name ?? 'the counter') . ' since ' . $last->created_at->format('g:i a') . '.');
                return;
            }
            $placeChange = true; // out of the vault already, now going to a different place
        } elseif ($this->direction === 'to_counter' && ($via = $this->outAsPartOf($type, $model))) {
            // The packet or box it sits in already went out: it is on the counter with that, not on its own.
            [$viaMove, $viaModel] = $via;
            $at = $viaMove->location_id ?? Location::defaultFloor()?->id;
            if ($at === $this->locationId) {
                $this->feedback('info', $label, "Already at " . (Location::find($at)?->name ?? 'the counter') . " as part of {$viaModel->code} since " . $viaMove->created_at->format('g:i a') . '.');
                return;
            }
            $placeChange = true;
        }
        if ($this->direction === 'to_vault') {
            $current = Location::latestVaultMove($type, $model->id);
            $currentTray = $current?->movement_type === 'vault_in' && $this->isTray($current->location_id) ? (int) $current->location_id : null;

            if ($this->vaultTrayId) {
                // Putting it on a tray: from the counter, from the vault or from another tray are all fine.
                if ($current?->movement_type !== 'vault_out' && $currentTray === $this->vaultTrayId) {
                    $this->feedback('info', $label, 'Already on ' . (Location::find($this->vaultTrayId)?->name ?? 'that tray') . '.');
                    return;
                }
            } elseif ($current?->movement_type !== 'vault_out') {
                $warning = 'Not recorded as going to the counter today';
            }
        }
        if ($this->direction === 'to_counter' && ($inside = $this->alreadyOutInside($type, $model))) {
            $warning = $inside;
        }

        $desc = Trackables::describe([$type => collect([$model->id => $model])] + ['item' => collect(), 'packet' => collect(), 'box' => collect()], $type, $model->id);

        array_unshift($this->tray, [
            'type' => $type,
            'id' => $model->id,
            'code' => $label,
            'detail' => $desc['detail'],
            'weight' => $type === 'item' ? (float) $model->weight : null,
            'warning' => $warning,
            'placeChange' => $placeChange,
        ]);

        $this->feedback($warning ? 'warning' : 'success', $label, $warning ? "Added. {$warning}." : ($placeChange ? 'Added as a place change.' : 'Added to the tray.'));
    }

    // One click from the "still on the counter" list: record the return straight away.
    public function returnNow(string $type, int $id): void
    {
        if (! in_array($type, ['item', 'packet', 'box'], true)) {
            return;
        }

        $last = $this->lastVaultMove($type, $id);
        if ($last?->movement_type !== 'vault_out') {
            $this->dispatch('toast', message: 'That is already recorded as back in the vault.', type: 'info');

            return;
        }

        $model = match ($type) {
            'item' => Item::find($id),
            'packet' => \App\Models\Stock\Packet::find($id),
            'box' => \App\Models\Stock\Box::find($id),
        };
        if (! $model) {
            return;
        }

        Movement::create([
                ...$this->doneByAttributes(),
            'trackable_type' => $type,
            'trackable_id' => $id,
            'movement_type' => 'vault_in',
            'location_id' => Location::vault()?->id,
            'purpose_label' => 'Closing stock',
            'user_id' => Auth::id(),
            'weight_at_dispatch' => $type === 'item' ? (float) $model->weight : null,
        ]);

        $this->dispatch('toast', message: ($type === 'item' ? $model->label : $model->code) . ' returned to the vault.', type: 'success');
    }

    public function removeFromTray(int $index): void
    {
        unset($this->tray[$index]);
        $this->tray = array_values($this->tray);
        $this->dispatch('scan-ready');
    }

    public function clearTray(): void
    {
        $this->reset(['tray', 'feedback']);
        $this->dispatch('scan-ready');
    }

    public function confirmMove(): void
    {
        if (! $this->tray) {
            return;
        }

        $type = $this->direction === 'to_counter' ? 'vault_out' : 'vault_in';
        $skipped = [];

        DB::transaction(function () use ($type, &$skipped) {
            foreach ($this->tray as $t) {
                // Re-check at confirm time: a piece may have been sold or dispatched meanwhile.
                if ($t['type'] === 'item' && ($item = Item::find($t['id'])) && $this->blockedReason($item)) {
                    $skipped[] = $t['code'];
                    continue;
                }

                // Re-check the record too: a second click or a second tab must not write the same move twice.
                $last = $this->lastVaultMove($t['type'], $t['id']);
                $vaultPlace = $this->vaultTrayId ?: Location::vault()?->id;
                $already = $type === 'vault_out'
                    ? $last?->movement_type === 'vault_out' && ($last->location_id ?? Location::defaultFloor()?->id) === $this->locationId
                    : $last?->movement_type === 'vault_in' && ($last->location_id ?? Location::vault()?->id) === $vaultPlace;
                if ($already) {
                    $skipped[] = $t['code'];
                    continue;
                }

                Movement::create([
                ...$this->doneByAttributes(),
                    'trackable_type' => $t['type'],
                    'trackable_id' => $t['id'],
                    'movement_type' => $type,
                    'location_id' => $type === 'vault_out' ? $this->locationId : $vaultPlace,
                    'purpose_label' => $type === 'vault_out' ? (! empty($t['placeChange']) ? 'Place change' : 'Counter display') : ($this->vaultTrayId ? 'Placed on a tray' : 'Closing stock'),
                    'user_id' => Auth::id(),
                    'weight_at_dispatch' => $t['weight'],
                ]);
            }
        });

        $moved = count($this->tray) - count($skipped);
        $where = $this->direction === 'to_counter'
            ? 'sent to ' . (Location::find($this->locationId)?->name ?? 'the counter')
            : ($this->vaultTrayId ? 'put on ' . (Location::find($this->vaultTrayId)?->name ?? 'a tray') : 'returned to the vault');
        $this->dispatch('toast', message: "{$moved} " . \Illuminate\Support\Str::plural('entry', $moved) . " {$where}.", type: 'success');
        if ($skipped) {
            $this->dispatch('toast', message: 'Skipped ' . implode(', ', $skipped) . ': no longer available, or already recorded.', type: 'warning');
        }

        $this->reset(['tray', 'feedback']);
        $this->dispatch('scan-ready');
    }

    private function isTray($locationId): bool
    {
        return $locationId && Location::whereKey($locationId)->where('type', 'tray')->exists();
    }

    /** @return list<array{type: string, id: int, label: string, detail: string}> */
    private function searchResults(): array
    {
        $q = trim($this->find);
        if (mb_strlen($q) < 2) {
            return [];
        }
        $like = '%' . $q . '%';

        $items = Item::query()->where('status', '!=', 'sold')->where(fn ($w) => $w->where('huid_code', 'like', $like)->orWhere('internal_code', 'like', $like)
            ->orWhere('category', 'like', $like)->orWhere('description', 'like', $like))->orderBy('category')->limit(6)->get()
            ->map(fn ($i) => ['type' => 'item', 'id' => $i->id, 'label' => $i->label, 'detail' => trim($i->category . ' · ' . number_format((float) $i->weight, 3) . ' g' . ($i->status !== 'in_stock' ? ' · ' . str_replace('_', ' ', $i->status) : ''))]);
        $packets = Packet::query()->where(fn ($w) => $w->where('code', 'like', $like)->orWhere('label', 'like', $like))->orderBy('code')->limit(4)->get()
            ->map(fn ($p) => ['type' => 'packet', 'id' => $p->id, 'label' => $p->code, 'detail' => 'Packet' . ($p->label ? ' · ' . $p->label : '')]);
        $boxes = Box::query()->where(fn ($w) => $w->where('code', 'like', $like)->orWhere('label', 'like', $like))->orderBy('code')->limit(4)->get()
            ->map(fn ($b) => ['type' => 'box', 'id' => $b->id, 'label' => $b->code, 'detail' => 'Box' . ($b->label ? ' · ' . $b->label : '')]);

        return $boxes->concat($packets)->concat($items)->all();
    }

    private function resolve(string $code): array
    {
        if ($item = StockLookup::item($code)) {
            return ['item', $item];
        }
        if ($found = StockLookup::container($code)) {
            return [$found['type'], $found['model']];
        }

        return [null, null];
    }

    private function blockedReason(Item $item): ?string
    {
        return match ($item->status) {
            'sold' => 'This piece is sold.',
            'dispatched' => 'This piece is out with a karigar, hallmarking or photo trip.',
            'pending_review' => 'This piece is waiting for admin review.',
            default => null,
        };
    }

    /**
     * The packet or box this piece / packet sits in, when that container's own latest vault move is a
     * vault_out newer than the piece's own: [that movement, the container]. Null otherwise.
     *
     * @return array{0: Movement, 1: \Illuminate\Database\Eloquent\Model}|null
     */
    private function outAsPartOf(string $type, $model): ?array
    {
        $containers = [];
        if ($type === 'item' && $model->packet_id && ($packet = Packet::find($model->packet_id))) {
            $containers[] = $packet;
            $box = $packet->box_id ? Box::find($packet->box_id) : null;
        } elseif ($type === 'packet') {
            $box = $model->box_id ? Box::find($model->box_id) : null;
        } else {
            $box = null;
        }
        if ($box) {
            $containers[] = $box;
        }

        $own = $this->lastVaultMove($type, $model->id);
        foreach ($containers as $container) {
            $cType = $container instanceof Packet ? 'packet' : 'box';
            $move = $this->lastVaultMove($cType, $container->id);
            if ($move?->movement_type === 'vault_out' && (! $own || $move->id > $own->id)) {
                return [$move, $container];
            }
        }

        return null;
    }

    /** A warning when part of a packet or box is already out on its own, so nothing is sent twice by mistake. */
    private function alreadyOutInside(string $type, $model): ?string
    {
        if ($type === 'box') {
            $packetIds = Packet::where('box_id', $model->id)->pluck('id');
            $n = $this->countOut('packet', $packetIds)
                + $this->countOut('item', Item::whereIn('packet_id', $packetIds)->pluck('id'));
            $what = 'packet or piece';
        } elseif ($type === 'packet') {
            $n = $this->countOut('item', Item::where('packet_id', $model->id)->pluck('id'));
            $what = 'piece';
        } else {
            return null;
        }

        return $n ? "{$n} {$what}" . ($n === 1 ? ' in it is' : 's in it are') . ' already on the counter on their own' : null;
    }

    private function countOut(string $type, $ids): int
    {
        if ($ids->isEmpty()) {
            return 0;
        }
        $latest = Movement::selectRaw('MAX(id)')->where('trackable_type', $type)->whereIn('trackable_id', $ids)
            ->whereIn('movement_type', Movement::PAIRS['vault'])->groupBy('trackable_id');

        return Movement::whereIn('id', $latest)->where('movement_type', 'vault_out')->count();
    }

    private function lastVaultMove(string $type, int $id): ?Movement
    {
        return Movement::where('trackable_type', $type)->where('trackable_id', $id)
            ->whereIn('movement_type', Movement::PAIRS['vault'])
            ->latest('id')->first();
    }

    private function feedback(string $tone, string $code, string $message): void
    {
        $this->feedback = compact('tone', 'code', 'message');
    }

    public function render()
    {
        $latest = Movement::query()->selectRaw('MAX(id)')
            ->whereIn('movement_type', Movement::PAIRS['vault'])
            ->groupBy('trackable_type', 'trackable_id');

        $onCounter = Movement::whereIn('id', $latest)->where('movement_type', 'vault_out')
            ->with('user:id,name')->orderByDesc('id')->get();

        $today = Movement::whereIn('movement_type', Movement::PAIRS['vault'])
            ->whereDate('created_at', today())
            ->with('user:id,name')->orderByDesc('id')->limit(60)->get();

        $loaded = Trackables::load($onCounter->concat($today));

        $inTray = collect($this->tray)->map(fn ($t) => $t['type'] . ':' . $t['id'])->flip();

        $places = Location::pluck('name', 'id');
        $defaultFloor = Location::defaultFloor()?->id;

        $counterRows = $onCounter->map(function ($m) use ($loaded, $inTray, $places, $defaultFloor) {
            $d = Trackables::describe($loaded, $m->trackable_type, $m->trackable_id);
            $status = $m->trackable_type === 'item' ? $d['model']?->status : null;

            return $d + [
                'type' => $m->trackable_type,
                'place' => $places[$m->location_id ?? $defaultFloor] ?? null,
                'since' => $m->created_at,
                'by' => $m->user?->name,
                'sold' => in_array($status, ['sold', 'reserved'], true),
                'scanned' => $inTray->has($m->trackable_type . ':' . $m->trackable_id),
            ];
        });

        // A box or packet with sold pieces inside shows it on the row.
        $counterRows = $counterRows->map(function ($r) {
            // Where it is kept, so a packet or piece reads as its own entry and not as its box.
            $r['inside'] = match ($r['type']) {
                'packet' => $r['model']?->box?->code,
                'item' => $r['model']?->packet?->code,
                default => null,
            };
            $r['soldInside'] = match ($r['type']) {
                'packet' => Item::where('packet_id', $r['model']?->id)->whereIn('status', ['sold', 'reserved'])->count(),
                'box' => Item::whereIn('packet_id', \App\Models\Stock\Packet::where('box_id', $r['model']?->id)->select('id'))
                    ->whereIn('status', ['sold', 'reserved'])->count(),
                default => 0,
            };

            return $r;
        });

        $expected = $counterRows->where('sold', false);

        // Today's out/in times side by side, one row per box / packet / piece.
        $pairs = $today->groupBy(fn ($m) => $m->trackable_type . ':' . $m->trackable_id)->map(function ($rows) use ($loaded) {
            $first = $rows->first();

            return Trackables::describe($loaded, $first->trackable_type, $first->trackable_id) + [
                'out' => $rows->where('movement_type', 'vault_out')->sortBy('id')->first(),
                'in' => $rows->where('movement_type', 'vault_in')->sortByDesc('id')->first(),
                'key' => $first->trackable_type . ':' . $first->trackable_id,
            ];
        })->values();

        return view('livewire.movement.vault-counter-move', [
            'counterRows' => $counterRows,
            'expectedCount' => $expected->count(),
            'missingCount' => $expected->where('scanned', false)->count(),
            'pairs' => $pairs,
            'movesToday' => $today->count(),
            'byUser' => $counterRows->where('sold', false)->groupBy('by')->map->count()->sortDesc(),
            'boxesOut' => $counterRows->where('type', 'box')->where('sold', false)->count(),
            'stats' => [
                'sent' => $today->where('movement_type', 'vault_out')->count(),
                'returned' => $today->where('movement_type', 'vault_in')->count(),
            ],
            'locations' => Location::active()->floor()->orderBy('sort_order')->orderBy('id')->get(['id', 'name']),
            'vaultTrays' => Location::active()->where('type', 'tray')->orderBy('sort_order')->orderBy('id')->get(['id', 'name']),
            'found' => $this->searchResults(),
            'trayWeight' => collect($this->tray)->sum('weight'),
        ])->layout('components.layouts.app', ['title' => 'Vault ↔ Counter · Radharani Jewellery']);
    }
}
