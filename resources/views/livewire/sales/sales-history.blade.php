<div>
    <x-ui.page-header title="Sales History" subtitle="Searchable record of past sales."
        :crumbs="[['label' => 'Sales & Billing', 'href' => route('sales.history')], ['label' => 'History']]">
        <x-slot:actions>
            <x-ui.button variant="secondary" icon="clock" :href="route('sales.verification')">Verification queue</x-ui.button>
            <x-ui.button icon="plus" :href="route('sales.new')">New sale</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
        <x-ui.stat-card icon="receipt" label="Total sales" :value="number_format($stats['total'])" />
        <x-ui.stat-card icon="check-circle" label="Verified" :value="number_format($stats['verified'])" />
        <x-ui.stat-card icon="clock" label="Awaiting verification" :value="number_format($stats['reserved'])" :hint="$stats['reserved'] ? 'in the queue' : null" :href="$stats['reserved'] ? route('sales.verification') : null" />
        <x-ui.stat-card icon="coins" label="Today's total" :value="'₹'.number_format($stats['todayTotal'], 2)" />
    </div>

    <x-ui.datatable :paginator="$sales">
        <x-slot:toolbar>
            <x-ui.search-input wire:model.live.debounce.300ms="search" placeholder="Search invoice # or customer" class="w-full sm:w-[280px]" />
            <div class="rj-segment">
                @foreach (['' => 'All', 'verified' => 'Verified', 'reserved' => 'Reserved'] as $value => $name)
                    <button type="button" wire:click="$set('status', '{{ $value }}')" class="{{ $status === $value ? 'is-active' : '' }}">{{ $name }}</button>
                @endforeach
            </div>
            @if ($this->hasActiveFilters())
                <x-ui.button variant="ghost" size="sm" icon="x" wire:click="resetFilters">Clear</x-ui.button>
            @endif
        </x-slot:toolbar>

        <x-slot:head>
            <x-ui.th field="invoice" :sort-field="$this->currentSortField()" :sort-direction="$this->currentSortDirection()">Invoice</x-ui.th>
            <x-ui.th>Customer</x-ui.th>
            <x-ui.th field="created" :sort-field="$this->currentSortField()" :sort-direction="$this->currentSortDirection()">Date</x-ui.th>
            <x-ui.th field="total" :sort-field="$this->currentSortField()" :sort-direction="$this->currentSortDirection()" align="right">Total</x-ui.th>
            <x-ui.th>Status</x-ui.th>
            <x-ui.th align="right"><span class="sr-only">Actions</span></x-ui.th>
        </x-slot:head>

        @forelse ($sales as $sale)
            <tr wire:key="sale-{{ $sale->id }}">
                <td class="rj-code text-ink_text-primary">{{ $sale->invoice_number }}</td>
                <td class="text-ink_text-primary">{{ $sale->customer->name ?? '—' }}</td>
                <td class="text-[12.5px] text-ink_text-secondary whitespace-nowrap">{{ $sale->created_at?->format('d M Y') }}</td>
                <td class="text-right tabular text-ink_text-primary">₹{{ number_format($sale->total, 2) }}</td>
                <td>
                    <x-ui.badge :tone="$sale->confirmed_by_accountant ? 'success' : 'warning'" size="sm" dot>
                        {{ $sale->confirmed_by_accountant ? 'Verified' : 'Reserved' }}
                    </x-ui.badge>
                </td>
                <td>
                    <div class="flex justify-end">
                        <x-ui.button variant="secondary" size="sm" iconRight="arrow-right" :href="route('sales.invoice', $sale)">View</x-ui.button>
                    </div>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="6">
                    @if ($this->hasActiveFilters())
                        <x-ui.empty-state icon="search" title="No sales match these filters" message="Try a different search or clear the filters.">
                            <x-ui.button variant="secondary" size="sm" wire:click="resetFilters">Clear filters</x-ui.button>
                        </x-ui.empty-state>
                    @else
                        <x-ui.empty-state icon="receipt" title="No sales yet" message="New sales entered at billing will show up here.">
                            <x-ui.button size="sm" icon="plus" :href="route('sales.new')">New sale</x-ui.button>
                        </x-ui.empty-state>
                    @endif
                </td>
            </tr>
        @endforelse
    </x-ui.datatable>
</div>
