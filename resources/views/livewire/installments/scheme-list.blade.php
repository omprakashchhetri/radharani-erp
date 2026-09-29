<div>
    <x-ui.page-header title="Installment Schemes" subtitle="All enrolments, filterable by status.">
        <x-slot:actions>
            <x-ui.button variant="secondary" icon="calendar" :href="route('installments.monthly-status')">Monthly status</x-ui.button>
            <x-ui.button icon="plus" :href="route('installments.enrol')">Enrol customer</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
        <x-ui.stat-card icon="check-circle" label="Active" :value="number_format($stats['active'])" />
        <x-ui.stat-card icon="check" label="Completed" :value="number_format($stats['completed'])" />
        <x-ui.stat-card icon="alert-triangle" label="Defaulted" :value="number_format($stats['defaulted'])" />
    </div>

    <x-ui.datatable :paginator="$schemes">
        <x-slot:toolbar>
            <x-ui.search-input wire:model.live.debounce.300ms="search" placeholder="Search customer name or phone" class="w-full sm:w-[280px]" />
            <div class="rj-segment">
                @foreach (['' => 'All', 'active' => 'Active', 'completed' => 'Completed', 'defaulted' => 'Defaulted'] as $value => $name)
                    <button type="button" wire:click="$set('statusFilter', '{{ $value }}')" class="{{ $statusFilter === $value ? 'is-active' : '' }}">{{ $name }}</button>
                @endforeach
            </div>
            @if ($this->hasActiveFilters())
                <x-ui.button variant="ghost" size="sm" icon="x" wire:click="resetFilters">Clear</x-ui.button>
            @endif
        </x-slot:toolbar>

        <x-slot:head>
            <x-ui.th>Customer</x-ui.th>
            <x-ui.th field="amount" :sort-field="$this->currentSortField()" :sort-direction="$this->currentSortDirection()" align="right">Monthly amount</x-ui.th>
            <x-ui.th align="right">Months paid</x-ui.th>
            <x-ui.th field="started" :sort-field="$this->currentSortField()" :sort-direction="$this->currentSortDirection()">Started</x-ui.th>
            <x-ui.th field="status" :sort-field="$this->currentSortField()" :sort-direction="$this->currentSortDirection()">Status</x-ui.th>
            <x-ui.th align="right"><span class="sr-only">Actions</span></x-ui.th>
        </x-slot:head>

        @forelse ($schemes as $scheme)
            <tr wire:key="scheme-{{ $scheme->id }}">
                <td class="text-ink_text-primary font-semibold">{{ $scheme->customer->name ?? '—' }}</td>
                <td class="text-right tabular text-ink_text-primary">₹{{ number_format($scheme->monthly_amount, 2) }}</td>
                <td class="text-right tabular text-ink_text-secondary">{{ $scheme->months_paid }}</td>
                <td class="text-[12.5px] text-ink_text-secondary whitespace-nowrap">{{ \Illuminate\Support\Carbon::parse($scheme->start_date)->format('d M Y') }}</td>
                <td>
                    <x-ui.badge :tone="$scheme->status === 'active' ? 'success' : ($scheme->status === 'completed' ? 'info' : 'danger')" size="sm" dot>
                        {{ ucfirst($scheme->status) }}
                    </x-ui.badge>
                </td>
                <td>
                    <div class="flex items-center justify-end gap-1.5">
                        @if ($scheme->status === 'active')
                            <x-ui.button type="button" variant="secondary" size="sm"
                                x-on:click="$dispatch('rj-confirm', { title: 'Mark scheme completed?', message: 'The installment scheme for {{ $scheme->customer->name }} will be closed as completed. This cannot be undone from here.', confirm: 'Mark completed', action: () => $wire.markStatus({{ $scheme->id }}, 'completed') })">
                                Mark completed
                            </x-ui.button>
                            <x-ui.button type="button" variant="danger-soft" size="sm"
                                x-on:click="$dispatch('rj-confirm', { title: 'Mark scheme defaulted?', message: 'The installment scheme for {{ $scheme->customer->name }} will be closed as defaulted. This cannot be undone from here.', confirm: 'Mark defaulted', tone: 'danger', action: () => $wire.markStatus({{ $scheme->id }}, 'defaulted') })">
                                Mark defaulted
                            </x-ui.button>
                        @endif
                    </div>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="6">
                    @if ($this->hasActiveFilters())
                        <x-ui.empty-state icon="search" title="No schemes match these filters" message="Try a different search or status, or clear the filters.">
                            <x-ui.button variant="secondary" size="sm" wire:click="resetFilters">Clear filters</x-ui.button>
                        </x-ui.empty-state>
                    @else
                        <x-ui.empty-state icon="calendar" title="No schemes yet" message="Enrol the first customer into the installment scheme.">
                            <x-ui.button size="sm" icon="plus" :href="route('installments.enrol')">Enrol customer</x-ui.button>
                        </x-ui.empty-state>
                    @endif
                </td>
            </tr>
        @endforelse
    </x-ui.datatable>
</div>
