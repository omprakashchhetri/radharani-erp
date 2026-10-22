<div>
    <x-ui.page-header title="Raw Material" subtitle="Metal the shop holds that is not yet a finished piece: in the shop, with each karigar, and at the refinery."
        :crumbs="[['label' => 'Stock'], ['label' => 'Raw Material']]">
        <x-slot:actions>
            <x-ui.button variant="secondary" icon="download" wire:click="export" target="export">Export CSV</x-ui.button>
            @if ($canManage)
                <x-ui.button variant="secondary" icon="arrow-left" wire:click="openForm('karigar')">Metal back from a karigar</x-ui.button>
                <x-ui.button variant="secondary" icon="repeat" wire:click="openForm('reclassify')">Reclassify</x-ui.button>
                <x-ui.button variant="secondary" icon="plus" wire:click="openForm('opening')">Opening balance</x-ui.button>
                <x-ui.button icon="plus" wire:click="openForm('adjust')">Adjustment</x-ui.button>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    <div class="rj-segment mb-6" role="tablist">
        @foreach (\App\Models\Movement\RawMetalEntry::HOLDERS as $k => $label)
            <button type="button" role="tab" wire:click="setHolder('{{ $k }}')" class="{{ $holder === $k ? 'is-active' : '' }}">{{ $label }}</button>
        @endforeach
    </div>

    {{-- ===================================================== BALANCES --}}
    @if ($holder === 'shop')
        @php $byMetal = $balances->groupBy('metal'); @endphp
        @if ($byMetal->isEmpty())
            <x-ui.card class="mb-6"><x-ui.empty-state icon="scale" title="Nothing in the shop yet" message="Raw-material purchases and settled exchanges add to this. An owner can also add an opening balance with Adjustment." compact /></x-ui.card>
        @else
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4 mb-6">
                @foreach ($byMetal as $metalName => $rows)
                    <x-ui.card :title="ucfirst($metalName)" icon="scale">
                        @foreach ($rows as $r)
                            @php $fine = \App\Models\Movement\RawMetalEntry::fineWeight($r->metal, $r->purity, (float) $r->weight); @endphp
                            <div class="flex items-center justify-between gap-3 py-2 border-b border-line-light last:border-0 text-[13.5px]">
                                <div>
                                    <span class="font-semibold">{{ $r->purity }}</span>
                                    <x-ui.badge size="sm" :tone="['refined' => 'success', 'old_gold' => 'warning', 'scrap' => 'neutral'][$r->kind]">{{ \App\Models\Movement\RawMetalEntry::KINDS[$r->kind] }}</x-ui.badge>
                                    @if ($r->kind !== 'refined' && $fine !== null)<div class="text-[12px] text-ink_text-muted">about {{ number_format($fine, 3) }} g fine metal</div>@endif
                                </div>
                                <span class="tabular font-semibold {{ $r->weight < 0 ? 'text-danger' : '' }}">{{ number_format($r->weight, 3) }} g</span>
                            </div>
                        @endforeach
                    </x-ui.card>
                @endforeach
            </div>
        @endif
    @elseif ($holder === 'karigar')
        @if (empty($holdings))
            <x-ui.card class="mb-6"><x-ui.empty-state icon="truck" title="No karigar is holding metal" message="Metal advanced to a karigar shows here until it is used in pieces or comes back." compact /></x-ui.card>
        @else
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4 mb-6">
                @foreach (collect($holdings)->groupBy('karigar') as $name => $rows)
                    <x-ui.card :title="$name" icon="truck">
                        @foreach ($rows as $r)
                            <div class="flex items-center justify-between gap-3 py-2 border-b border-line-light last:border-0 text-[13.5px]">
                                <div><span class="font-semibold">{{ ucfirst($r['metal']) }} {{ $r['carat'] }}</span><div class="text-[12px] text-ink_text-muted">{{ $r['batch'] }}</div></div>
                                <span class="tabular font-semibold">{{ number_format($r['weight'], 3) }} g</span>
                            </div>
                        @endforeach
                    </x-ui.card>
                @endforeach
            </div>
        @endif
    @else
        @if ($balances->isEmpty())
            <x-ui.card class="mb-6"><x-ui.empty-state icon="flame" title="Nothing at the refinery" message="Old metal and scrap sent from Refinery, Send show here until the refined metal comes back." compact /></x-ui.card>
        @else
            <x-ui.card title="Out at the refinery" icon="flame" class="mb-6">
                @foreach ($balances as $r)
                    <div class="flex items-center justify-between gap-3 py-2 border-b border-line-light last:border-0 text-[13.5px]">
                        <div><span class="font-semibold">Batch #{{ $r->holder_id }}</span> · {{ ucfirst($r->metal) }} {{ $r->purity }}
                            <x-ui.badge size="sm" tone="neutral">{{ \App\Models\Movement\RawMetalEntry::KINDS[$r->kind] }}</x-ui.badge></div>
                        <span class="tabular font-semibold">{{ number_format($r->weight, 3) }} g</span>
                    </div>
                @endforeach
                <a href="{{ route('exchange.refinery.return') }}" class="inline-block mt-3 text-[12.5px] font-semibold text-gold-dark hover:underline">Record a return</a>
            </x-ui.card>
        @endif
    @endif

    {{-- ===================================================== LEDGER --}}
    <x-ui.datatable :paginator="$entries">
        <x-slot:toolbar>
            <x-ui.search-input wire:model.live.debounce.300ms="search" placeholder="Search a note or carat" class="w-full sm:w-[240px]" />
            <select wire:model.live="metal" class="rj-select w-auto min-w-[120px]" aria-label="Metal"><option value="">All metals</option>@foreach ($metals as $m)<option value="{{ $m }}">{{ ucfirst($m) }}</option>@endforeach</select>
            <select wire:model.live="kind" class="rj-select w-auto min-w-[150px]" aria-label="Kind"><option value="">All kinds</option>@foreach (\App\Models\Movement\RawMetalEntry::KINDS as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select>
            <select wire:model.live="source" class="rj-select w-auto min-w-[150px]" aria-label="What happened"><option value="">Everything</option>@foreach (\App\Models\Movement\RawMetalEntry::SOURCES as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select>
            @if ($this->hasActiveFilters())<x-ui.button variant="ghost" size="sm" icon="x" wire:click="resetFilters">Clear</x-ui.button>@endif
        </x-slot:toolbar>

        <x-slot:head>
            <x-ui.th field="date" :sort-field="$this->currentSortField()" :sort-direction="$this->currentSortDirection()">When</x-ui.th>
            <x-ui.th>What happened</x-ui.th>
            <x-ui.th>Holder</x-ui.th>
            <x-ui.th field="metal" :sort-field="$this->currentSortField()" :sort-direction="$this->currentSortDirection()">Metal</x-ui.th>
            <x-ui.th field="weight" :sort-field="$this->currentSortField()" :sort-direction="$this->currentSortDirection()" align="right">Weight</x-ui.th>
        </x-slot:head>

        @forelse ($entries as $e)
            <tr wire:key="rme-{{ $e->id }}">
                <td class="whitespace-nowrap text-[13px]">{{ $e->created_at->format('j M Y, g:i a') }}<div class="text-[12px] text-ink_text-muted">by {{ $e->user?->name ?? '-' }}</div></td>
                <td>
                    @php
                        $link = match ($e->source_type) {
                            'exchange' => $e->source_id ? route('exchange.transactions.show', $e->source_id) : null,
                            'karigar_advance', 'karigar_used', 'karigar_return' => $e->source_id ? route('movements.karigar.print', $e->source_id) : null,
                            'refinery_out', 'refinery_return' => route('exchange.refinery.return'),
                            'purchase' => route('purchases.list'),
                            default => null,
                        };
                    @endphp
                    <div class="font-semibold text-[13.5px]">@if ($link)<a href="{{ $link }}" class="hover:text-gold-dark">{{ \App\Models\Movement\RawMetalEntry::SOURCES[$e->source_type] ?? $e->source_type }}</a>@else{{ \App\Models\Movement\RawMetalEntry::SOURCES[$e->source_type] ?? $e->source_type }}@endif</div>
                    @if ($e->note)<div class="text-[12.5px] text-ink_text-muted max-w-[320px] truncate" title="{{ $e->note }}">{{ $e->note }}</div>@endif
                </td>
                <td class="text-[13px]">{{ $names[$e->holder_type . ':' . $e->holder_id] ?? 'Shop' }}</td>
                <td class="text-[13px]">{{ ucfirst($e->metal) }} {{ $e->purity }}<div class="text-[12px] text-ink_text-muted">{{ \App\Models\Movement\RawMetalEntry::KINDS[$e->kind] }}</div></td>
                <td class="text-right tabular font-semibold whitespace-nowrap {{ $e->weight < 0 ? 'text-danger' : 'text-success' }}">{{ $e->weight > 0 ? '+' : '' }}{{ number_format($e->weight, 3) }} g</td>
            </tr>
        @empty
            <tr><td colspan="5"><x-ui.empty-state icon="book" :title="$this->hasActiveFilters() ? 'Nothing matches' : 'No entries yet'" message="Every change to this metal is listed here and never edited." compact /></td></tr>
        @endforelse
    </x-ui.datatable>

    @if ($canManage)
        {{-- ===================================================== ADJUSTMENT --}}
        <x-ui.modal wire:model="showAdjust" :title="$adjustPurpose === 'opening' ? 'Opening balance' : 'Adjustment'" icon="plus" max-width="md" submit="adjust"
            subtitle="An opening balance or a correction. It is added to the shop's stock as a new entry; nothing is edited.">
            <div class="space-y-4">
                <div class="rj-segment" @if ($adjustPurpose === 'opening') hidden @endif><button type="button" wire:click="$set('adjustDirection', 'add')" class="{{ $adjustDirection === 'add' ? 'is-active' : '' }}">Add metal</button><button type="button" wire:click="$set('adjustDirection', 'remove')" class="{{ $adjustDirection === 'remove' ? 'is-active' : '' }}">Take metal out</button></div>
                <div class="grid grid-cols-2 gap-4">
                    <x-ui.field label="Metal" for="ra-metal"><select id="ra-metal" wire:model.live="adjustMetal" class="rj-select">@foreach ($metals as $m)<option value="{{ $m }}">{{ ucfirst($m) }}</option>@endforeach</select></x-ui.field>
                    <x-ui.field label="Carat" for="ra-carat" error="adjustCarat"><select id="ra-carat" wire:model="adjustCarat" class="rj-select">@foreach ($carats[$adjustMetal] ?? [] as $c)<option value="{{ $c }}">{{ $c }}</option>@endforeach</select></x-ui.field>
                    <x-ui.field label="Kind" for="ra-kind"><select id="ra-kind" wire:model="adjustKind" class="rj-select">@foreach (\App\Models\Movement\RawMetalEntry::KINDS as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></x-ui.field>
                    <x-ui.field label="Weight (g)" for="ra-weight" error="adjustWeight"><input id="ra-weight" type="number" step="0.001" min="0" wire:model="adjustWeight" class="rj-input tabular @error('adjustWeight') is-invalid @enderror" autofocus></x-ui.field>
                </div>
                <x-ui.field label="Why" for="ra-note" error="adjustNote"><input id="ra-note" type="text" wire:model="adjustNote" maxlength="255" class="rj-input @error('adjustNote') is-invalid @enderror" placeholder="e.g. opening balance, scrap swept from the workshop"></x-ui.field>
            </div>
            <x-slot:footer>
                <x-ui.button variant="secondary" x-on:click="show = false">Cancel</x-ui.button>
                <x-ui.button type="submit" target="adjust" icon="check">Record</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>

        {{-- ===================================================== RECLASSIFY --}}
        <x-ui.modal wire:model="showReclass" title="Reclassify as refined" icon="repeat" max-width="md" submit="reclassify"
            subtitle="Old metal or scrap that has been melted and tested can be treated as refined, so it can go to a karigar.">
            <div class="space-y-4">
                <x-ui.field label="Which stock" for="rc-from" error="fromKey">
                    <select id="rc-from" wire:model.live="fromKey" class="rj-select">
                        <option value="">Choose...</option>
                        @foreach ($unrefined as $u)<option value="{{ $u['key'] }}">{{ ucfirst($u['metal']) }} {{ $u['carat'] }} · {{ \App\Models\Movement\RawMetalEntry::KINDS[$u['kind']] }} · {{ number_format($u['weight'], 3) }} g</option>@endforeach
                    </select>
                    @if (empty($unrefined))<p class="rj-help">There is no old metal or scrap in the shop.</p>@endif
                </x-ui.field>
                @php $pickedMetal = collect($unrefined)->firstWhere('key', $fromKey)['metal'] ?? 'gold'; @endphp
                <div class="grid grid-cols-2 gap-4">
                    <x-ui.field label="Weight (g)" for="rc-weight" error="reclassWeight"><input id="rc-weight" type="number" step="0.001" min="0" wire:model="reclassWeight" class="rj-input tabular @error('reclassWeight') is-invalid @enderror"></x-ui.field>
                    <x-ui.field label="Carat it is now" for="rc-carat" error="reclassCarat"><select id="rc-carat" wire:model="reclassCarat" class="rj-select"><option value="">Choose...</option>@foreach ($carats[$pickedMetal] ?? [] as $c)<option value="{{ $c }}">{{ $c }}</option>@endforeach</select></x-ui.field>
                </div>
                <x-ui.field label="How it was tested" for="rc-note" error="reclassNote"><input id="rc-note" type="text" wire:model="reclassNote" maxlength="255" class="rj-input @error('reclassNote') is-invalid @enderror" placeholder="e.g. melted and assayed at 91.6"></x-ui.field>
            </div>
            <x-slot:footer>
                <x-ui.button variant="secondary" x-on:click="show = false">Cancel</x-ui.button>
                <x-ui.button type="submit" target="reclassify" icon="check">Reclassify</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>

        {{-- ===================================================== METAL BACK FROM A KARIGAR --}}
        <x-ui.modal wire:model="showReturn" title="Metal back from a karigar" icon="arrow-left" max-width="md" submit="returnFromKarigar"
            subtitle="Metal a karigar was advanced but did not use. It goes back into the shop as refined metal.">
            <div class="space-y-4">
                <x-ui.field label="Karigar and batch" for="kb-held" error="heldKey">
                    <select id="kb-held" wire:model="heldKey" class="rj-select">
                        <option value="">Choose...</option>
                        @foreach ($held as $h)<option value="{{ $h['key'] }}">{{ $h['karigar'] }} · {{ $h['batch'] }} · {{ ucfirst($h['metal']) }} {{ $h['carat'] }} · holds {{ number_format($h['weight'], 3) }} g</option>@endforeach
                    </select>
                    @if (empty($held))<p class="rj-help">No karigar is holding metal.</p>@endif
                </x-ui.field>
                <x-ui.field label="Weight (g)" for="kb-weight" error="returnWeight"><input id="kb-weight" type="number" step="0.001" min="0" wire:model="returnWeight" class="rj-input tabular @error('returnWeight') is-invalid @enderror"></x-ui.field>
                <x-ui.field label="Note" for="kb-note" optional><input id="kb-note" type="text" wire:model="returnNote" maxlength="255" class="rj-input"></x-ui.field>
            </div>
            <x-slot:footer>
                <x-ui.button variant="secondary" x-on:click="show = false">Cancel</x-ui.button>
                <x-ui.button type="submit" target="returnFromKarigar" icon="check">Record</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
    @endif
</div>
