<div>
    <x-ui.page-header title="Sale Verification Queue" subtitle="Verifying here confirms the sale, assigns its real invoice number, flips its items from reserved to sold, and queues the customer notification."
        :crumbs="[['label' => 'Sales & Billing', 'href' => route('sales.history')], ['label' => 'Verification Queue']]" />

    <x-ui.datatable :paginator="$pending">
        <x-slot:toolbar>
            <x-ui.search-input wire:model.live.debounce.300ms="search" placeholder="Search by invoice or customer" class="w-full sm:w-[280px]" />
        </x-slot:toolbar>

        <x-slot:head>
            <x-ui.th field="invoice" :sort-field="$this->currentSortField()" :sort-direction="$this->currentSortDirection()">Reserved as</x-ui.th>
            <x-ui.th>Customer</x-ui.th>
            <x-ui.th align="right">Items</x-ui.th>
            <x-ui.th field="total" :sort-field="$this->currentSortField()" :sort-direction="$this->currentSortDirection()" align="right">Total</x-ui.th>
            <x-ui.th>By</x-ui.th>
            <x-ui.th field="created" :sort-field="$this->currentSortField()" :sort-direction="$this->currentSortDirection()">Reserved</x-ui.th>
            <x-ui.th align="right"><span class="sr-only">Actions</span></x-ui.th>
        </x-slot:head>

        @forelse ($pending as $sale)
            <tr wire:key="pending-sale-{{ $sale->id }}">
                <td class="rj-code text-ink_text-primary">{{ $sale->invoice_number }}</td>
                <td class="text-ink_text-primary">{{ $sale->customer->name ?? '—' }}</td>
                <td class="text-right tabular">{{ $sale->items->count() }}</td>
                <td class="text-right tabular text-ink_text-primary">₹{{ number_format($sale->total, 2) }}</td>
                <td class="text-ink_text-secondary">{{ $sale->creator->name ?? '—' }}</td>
                <td class="text-[12.5px] text-ink_text-secondary whitespace-nowrap">{{ $sale->created_at?->format('d M Y, g:i a') }}</td>
                <td>
                    <div class="flex items-center justify-end">
                        <x-ui.button variant="primary" size="sm" icon="check"
                            x-on:click="$dispatch('rj-confirm', { title: 'Verify this sale?', message: 'Its items will be marked sold, a real invoice number will be assigned, and the customer will be queued for notification. This cannot be undone.', confirm: 'Verify', action: () => $wire.verify({{ $sale->id }}) })">
                            Approve
                        </x-ui.button>
                    </div>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="7">
                    @if ($this->hasActiveFilters())
                        <x-ui.empty-state icon="search" title="No sales match this search" message="Try a different invoice or customer name.">
                            <x-ui.button variant="secondary" size="sm" wire:click="resetFilters">Clear search</x-ui.button>
                        </x-ui.empty-state>
                    @else
                        <x-ui.empty-state icon="check-circle" title="Nothing waiting on verification" message="Reserved sales from New Sale will show up here." />
                    @endif
                </td>
            </tr>
        @endforelse
    </x-ui.datatable>
</div>
