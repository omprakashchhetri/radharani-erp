<div>
    <x-ui.page-header title="Audit Log" subtitle="Every movement, sale, purchase, stock and order change, tamper-evident — read-only, sourced from spatie/laravel-activitylog." />

    <div class="grid grid-cols-2 xl:grid-cols-3 gap-4 mb-6">
        <x-ui.stat-card icon="history" label="Total entries" :value="number_format($stats['total'])" />
        <x-ui.stat-card icon="calendar" label="Today" :value="number_format($stats['today'])" />
        <x-ui.stat-card icon="clock" label="This week" :value="number_format($stats['thisWeek'])" />
    </div>

    <x-ui.datatable :paginator="$activities">
        <x-slot:toolbar>
            <x-ui.search-input wire:model.live.debounce.300ms="search" placeholder="Search description" class="w-full sm:w-[280px]" />
            <select wire:model.live="logNameFilter" class="rj-select rj-input-sm">
                <option value="">All types</option>
                <option value="movement">Movements</option>
                <option value="sale">Sales</option>
                <option value="purchase">Purchases</option>
                <option value="stock">Stock</option>
                <option value="order">Orders</option>
            </select>
            @if ($this->hasActiveFilters())
                <x-ui.button variant="ghost" size="sm" icon="x" wire:click="resetFilters">Clear</x-ui.button>
            @endif
        </x-slot:toolbar>

        <x-slot:head>
            <x-ui.th field="created" :sort-field="$this->currentSortField()" :sort-direction="$this->currentSortDirection()">Date</x-ui.th>
            <x-ui.th field="type" :sort-field="$this->currentSortField()" :sort-direction="$this->currentSortDirection()">Type</x-ui.th>
            <x-ui.th>Description</x-ui.th>
            <x-ui.th>By</x-ui.th>
        </x-slot:head>

        @forelse ($activities as $a)
            <tr wire:key="activity-{{ $a->id }}">
                <td class="text-[12.5px] text-ink_text-secondary whitespace-nowrap">{{ $a->created_at->format('d M Y, g:i a') }}</td>
                <td><x-ui.badge tone="neutral" size="sm">{{ ucfirst($a->log_name ?? 'other') }}</x-ui.badge></td>
                <td class="text-ink_text-primary">{{ $a->description }}</td>
                <td class="text-ink_text-secondary">{{ $a->causer->name ?? 'System' }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="4">
                    @if ($this->hasActiveFilters())
                        <x-ui.empty-state icon="search" title="No entries match these filters" message="Try a different search or type, or clear the filters.">
                            <x-ui.button variant="secondary" size="sm" wire:click="resetFilters">Clear filters</x-ui.button>
                        </x-ui.empty-state>
                    @else
                        <x-ui.empty-state icon="history" title="No audit entries yet" message="Movements, sales, purchases, stock and order changes will show up here." />
                    @endif
                </td>
            </tr>
        @endforelse
    </x-ui.datatable>
</div>
