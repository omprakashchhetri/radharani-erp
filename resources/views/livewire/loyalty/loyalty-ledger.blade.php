<div>
    <x-ui.page-header title="Loyalty Points Ledger" subtitle="Full history of points earned and redeemed, across all customers.">
        <x-slot:actions>
            <x-ui.button icon="gift" :href="route('loyalty.award')">Award points</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid grid-cols-2 xl:grid-cols-3 gap-4 mb-6">
        <x-ui.stat-card icon="receipt" label="Ledger entries" :value="number_format($stats['entries'])" />
        <x-ui.stat-card icon="gift" label="Points earned" :value="'+'.number_format($stats['earned'])" />
        <x-ui.stat-card icon="arrow-down" label="Points redeemed" :value="'-'.number_format($stats['redeemed'])" />
    </div>

    <x-ui.datatable :paginator="$transactions">
        <x-slot:toolbar>
            <x-ui.search-input wire:model.live.debounce.300ms="search" placeholder="Search customer name or phone" class="w-full sm:w-[280px]" />
            @if ($this->hasActiveFilters())
                <x-ui.button variant="ghost" size="sm" icon="x" wire:click="resetFilters">Clear</x-ui.button>
            @endif
        </x-slot:toolbar>

        <x-slot:head>
            <x-ui.th field="created" :sort-field="$this->currentSortField()" :sort-direction="$this->currentSortDirection()">Date</x-ui.th>
            <x-ui.th>Customer</x-ui.th>
            <x-ui.th>Reason</x-ui.th>
            <x-ui.th>Sale</x-ui.th>
            <x-ui.th field="points" :sort-field="$this->currentSortField()" :sort-direction="$this->currentSortDirection()" align="right">Points</x-ui.th>
        </x-slot:head>

        @forelse ($transactions as $t)
            <tr wire:key="loyalty-{{ $t->id }}">
                <td class="text-[12.5px] text-ink_text-secondary whitespace-nowrap">{{ $t->created_at->format('d M Y') }}</td>
                <td class="text-ink_text-primary font-semibold">{{ $t->customer->name ?? '—' }}</td>
                <td class="text-ink_text-primary">{{ $t->reason ?: '—' }}</td>
                <td class="text-ink_text-secondary">{{ $t->related_sale_id ? '#'.$t->related_sale_id : '—' }}</td>
                <td class="text-right tabular font-semibold {{ $t->points >= 0 ? 'text-success' : 'text-danger' }}">
                    {{ $t->points >= 0 ? '+' : '' }}{{ $t->points }}
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="5">
                    @if ($this->hasActiveFilters())
                        <x-ui.empty-state icon="search" title="No activity matches this search" message="Try a different customer name or phone.">
                            <x-ui.button variant="secondary" size="sm" wire:click="resetFilters">Clear filters</x-ui.button>
                        </x-ui.empty-state>
                    @else
                        <x-ui.empty-state icon="gift" title="No loyalty activity yet" message="Points awarded or redeemed will show up here.">
                            <x-ui.button size="sm" icon="gift" :href="route('loyalty.award')">Award points</x-ui.button>
                        </x-ui.empty-state>
                    @endif
                </td>
            </tr>
        @endforelse
    </x-ui.datatable>
</div>
