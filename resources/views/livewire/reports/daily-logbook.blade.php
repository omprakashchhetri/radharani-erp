<div>
    <x-ui.page-header title="Daily Logbook" subtitle="Movements and sales for the day, combined into one timeline.">
        <x-slot:actions>
            <x-ui.button variant="secondary" icon="download" wire:click="export" target="export">Export this day (CSV)</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
        <x-ui.stat-card icon="repeat" label="Movements" :value="number_format($stats['movements'])" />
        <x-ui.stat-card icon="receipt" label="Sales" :value="number_format($stats['sales'])" />
        <x-ui.stat-card icon="coins" label="Sales value" :value="'₹'.number_format($stats['salesValue'], 2)" />
    </div>

    <div class="flex flex-wrap items-center gap-2.5 mb-4">
        <input type="date" wire:model.live="date" class="rj-input w-[170px]">
        <x-ui.search-input wire:model.live.debounce.300ms="search" placeholder="Search event or staff" class="w-full sm:w-[260px]" />
    </div>

    <details class="mb-4 group">
        <summary class="cursor-pointer text-[13px] font-semibold text-gold-dark hover:underline list-none">Export several days</summary>
        <div class="mt-3 flex flex-wrap items-end gap-3">
            <x-ui.field label="From" for="lb-from" error="exportFrom"><input id="lb-from" type="date" wire:model="exportFrom" class="rj-input w-[170px]"></x-ui.field>
            <x-ui.field label="To" for="lb-to" error="exportTo"><input id="lb-to" type="date" wire:model="exportTo" class="rj-input w-[170px]"></x-ui.field>
            <x-ui.button variant="secondary" icon="download" wire:click="exportRange" target="exportRange">Download CSV</x-ui.button>
        </div>
    </details>

    <x-ui.card :padding="false" class="overflow-hidden">
        <x-ui.table :headers="['Time', 'Type', 'Event', 'By / Customer', 'Note']">
            @forelse ($timeline as $entry)
                <tr class="h-[56px] border-b border-line-light">
                    <td class="px-4 tabular text-ink_text-primary">{{ $entry['time']?->format('H:i') }}</td>
                    <td class="px-4">
                        @if ($entry['kind'] === 'sale')
                            <x-ui.badge tone="success" size="sm">Sale</x-ui.badge>
                        @else
                            <x-ui.badge tone="neutral" size="sm">Movement</x-ui.badge>
                        @endif
                    </td>
                    <td class="px-4 text-ink_text-primary">{{ $entry['label'] }}</td>
                    <td class="px-4 text-ink_text-primary">{{ $entry['by'] }}</td>
                    <td class="px-4 text-ink_text-secondary text-[12.5px]">{{ $entry['note'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5">
                        <x-ui.empty-state icon="calendar" title="Nothing recorded on this date" message="Try a different date or clear the search." compact />
                    </td>
                </tr>
            @endforelse
        </x-ui.table>
    </x-ui.card>
</div>
