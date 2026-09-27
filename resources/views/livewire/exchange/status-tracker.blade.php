<div>
    <x-ui.page-header title="Status Tracker" subtitle="Every old gold/silver exchange, from received to settled."
        :crumbs="[['label' => 'Exchange & Refinery'], ['label' => 'Status Tracker']]">
        <x-slot:actions>
            <x-ui.button icon="plus" :href="route('exchange.new')">New exchange</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
        @foreach (\App\Livewire\Exchange\StatusTracker::STAGES as $key => $label)
            <button type="button" wire:click="$set('stageFilter', '{{ $stageFilter === $key ? '' : $key }}')"
                class="text-left bg-white border rounded-card p-4 shadow-card transition-[border-color,box-shadow] {{ $stageFilter === $key ? 'border-gold ring-4 ring-gold/10' : 'border-line-light hover:border-gold-soft' }}">
                <div class="text-[12px] font-semibold text-ink_text-secondary">{{ $label }}</div>
                <div class="font-display text-[28px] leading-none font-semibold tabular mt-2">{{ $stats[$key] ?? 0 }}</div>
            </button>
        @endforeach
    </div>

    @php $stageKeys = array_keys(\App\Livewire\Exchange\StatusTracker::STAGES); @endphp
    <x-ui.datatable :paginator="$transactions">
        <x-slot:toolbar>
            <x-ui.search-input wire:model.live.debounce.300ms="search" placeholder="Customer name or phone" class="w-full sm:w-[260px]" />
            @if ($this->hasActiveFilters())
                <x-ui.button variant="ghost" size="sm" icon="x" wire:click="resetFilters">Clear all</x-ui.button>
            @endif
        </x-slot:toolbar>

        <x-slot:head>
            <x-ui.th field="id" :sort-field="$this->currentSortField()" :sort-direction="$this->currentSortDirection()">Exchange</x-ui.th>
            <th>Customer</th>
            <x-ui.th field="weight" :sort-field="$this->currentSortField()" :sort-direction="$this->currentSortDirection()" align="right">Gross weight</x-ui.th>
            <x-ui.th field="stage" :sort-field="$this->currentSortField()" :sort-direction="$this->currentSortDirection()">Stage</x-ui.th>
            <x-ui.th field="updated" :sort-field="$this->currentSortField()" :sort-direction="$this->currentSortDirection()">Last updated</x-ui.th>
            <x-ui.th align="right"><span class="sr-only">Open</span></x-ui.th>
        </x-slot:head>

        @forelse ($transactions as $t)
            <tr wire:key="ex-{{ $t->id }}">
                <td>
                    <a href="{{ route('exchange.transactions.show', $t) }}" class="rj-code text-ink_text-primary hover:text-gold-dark">#{{ $t->id }}</a>
                </td>
                <td>
                    <div class="font-semibold text-ink_text-primary">{{ $t->customer->name ?? '—' }}</div>
                    <div class="text-[12px] text-ink_text-muted">{{ $t->customer->phone ?? '' }}</div>
                </td>
                <td class="text-right tabular">{{ number_format($t->gross_weight, 3) }} <span class="text-ink_text-muted font-normal">g</span></td>
                <td>
                    <div class="flex gap-1 mb-1">
                        @foreach ($stageKeys as $key)
                            <span class="w-[26px] h-1.5 rounded-full {{ array_search($t->stage, $stageKeys) >= array_search($key, $stageKeys) ? 'bg-gold' : 'bg-surface-muted' }}"></span>
                        @endforeach
                    </div>
                    <x-ui.badge :tone="['received' => 'info', 'melted' => 'warning', 'tested' => 'gold', 'valued' => 'gold', 'settled' => 'success'][$t->stage] ?? 'neutral'" size="sm">
                        {{ \App\Livewire\Exchange\StatusTracker::STAGES[$t->stage] ?? ucfirst($t->stage) }}
                    </x-ui.badge>
                </td>
                <td class="text-[12.5px] text-ink_text-secondary whitespace-nowrap">{{ $t->updated_at->diffForHumans() }}</td>
                <td class="text-right">
                    <x-ui.button variant="secondary" size="icon-sm" icon="arrow-right" :href="route('exchange.transactions.show', $t)" title="Open" aria-label="Open exchange #{{ $t->id }}" />
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="6">
                    @if ($this->hasActiveFilters())
                        <x-ui.empty-state icon="search" title="No exchanges match" message="Nothing fits these filters. Loosen one of them or clear them all.">
                            <x-ui.button variant="secondary" size="sm" wire:click="resetFilters">Clear filters</x-ui.button>
                        </x-ui.empty-state>
                    @else
                        <x-ui.empty-state icon="flame" title="No exchanges recorded yet" message="Start one from New Entry.">
                            <x-ui.button size="sm" icon="plus" :href="route('exchange.new')">New exchange</x-ui.button>
                        </x-ui.empty-state>
                    @endif
                </td>
            </tr>
        @endforelse
    </x-ui.datatable>
</div>
