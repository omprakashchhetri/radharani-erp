<div>
    <x-ui.page-header title="Boxes" subtitle="The top level of the stock room. Each box holds packets, each packet holds pieces."
        :crumbs="[['label' => 'Stock', 'href' => route('stock.items')], ['label' => 'Boxes']]">
        <x-slot:actions>
            <x-ui.button variant="secondary" icon="layers" :href="route('stock.configurator')">Configurator</x-ui.button>
            <x-ui.button icon="plus" wire:click="create">New box</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
        <x-ui.stat-card icon="archive" label="Boxes" :value="number_format($stats['boxes'])" hint="in the stock room" />
        <x-ui.stat-card icon="package" label="Packets inside boxes" :value="number_format($stats['packets'])" />
        <x-ui.stat-card icon="gem" label="Pieces inside boxes" :value="number_format($stats['items'])" />
        <x-ui.stat-card icon="unlink" label="Packets without a box" :value="number_format($stats['loosePackets'])"
            :hint="$stats['loosePackets'] ? 'assign them in the configurator' : 'all packets are boxed'" :href="$stats['loosePackets'] ? route('stock.configurator') : null" />
    </div>

    @php $pageIds = $boxes->pluck('id')->map(fn ($id) => (string) $id)->all(); @endphp
    <x-ui.datatable :paginator="$boxes">
        <x-slot:toolbar>
            <x-ui.search-input scan wire:model.live.debounce.300ms="search" placeholder="Search by code or label" class="w-full sm:w-[300px]" />
            <div class="rj-segment">
                @foreach (['' => 'All', 'filled' => 'With packets', 'empty' => 'Empty'] as $value => $name)
                    <button type="button" wire:click="$set('contents', '{{ $value }}')" class="{{ $contents === $value ? 'is-active' : '' }}">{{ $name }}</button>
                @endforeach
            </div>
            @if ($this->hasActiveFilters())
                <x-ui.button variant="ghost" size="sm" icon="x" wire:click="resetFilters">Clear</x-ui.button>
            @endif
        </x-slot:toolbar>

        @if (count($selected))
            <x-slot:bulk>
                <div class="flex flex-wrap items-center gap-3 px-4 py-2.5 bg-ink text-white animate-fade-in">
                    <span class="text-[13px] font-semibold"><span class="text-gold-light tabular">{{ count($selected) }}</span> selected</span>
                    <div class="w-px h-5 bg-white/10"></div>
                    <button type="button" wire:click="printSelectedQr" class="inline-flex items-center gap-1.5 h-8 px-3 rounded-lg text-[12.5px] font-semibold text-ink-fg hover:text-white hover:bg-white/10">
                        <x-ui.icon name="printer" :size="14" /> Print QR labels
                    </button>
                    <button type="button" wire:click="clearSelection" class="ml-auto text-[12.5px] font-semibold text-ink-dim hover:text-white">Clear selection</button>
                </div>
            </x-slot:bulk>
        @endif

        <x-slot:head>
            <th class="w-10 !pr-0">
                <input type="checkbox" class="rj-checkbox" aria-label="Select all on this page"
                    @checked(count($pageIds) && ! array_diff($pageIds, $selected))
                    x-on:change="$wire.set('selected', $event.target.checked ? @js($pageIds) : [])">
            </th>
            <x-ui.th field="code" :sort-field="$this->currentSortField()" :sort-direction="$this->currentSortDirection()">Box</x-ui.th>
            <x-ui.th field="packets" :sort-field="$this->currentSortField()" :sort-direction="$this->currentSortDirection()" align="right">Packets</x-ui.th>
            <x-ui.th field="items" :sort-field="$this->currentSortField()" :sort-direction="$this->currentSortDirection()" align="right">Pieces</x-ui.th>
            <x-ui.th field="weight" :sort-field="$this->currentSortField()" :sort-direction="$this->currentSortDirection()" align="right">Weight</x-ui.th>
            <x-ui.th>QR label</x-ui.th>
            <x-ui.th field="created" :sort-field="$this->currentSortField()" :sort-direction="$this->currentSortDirection()">Created</x-ui.th>
            <x-ui.th align="right"><span class="sr-only">Actions</span></x-ui.th>
        </x-slot:head>

        @forelse ($boxes as $box)
            <tr wire:key="box-{{ $box->id }}" @class(['is-selected' => in_array((string) $box->id, $selected, true)])>
                <td class="!pr-0"><input type="checkbox" class="rj-checkbox" value="{{ $box->id }}" wire:model.live="selected" aria-label="Select {{ $box->code }}"></td>
                <td>
                    <a href="{{ route('stock.boxes.show', $box) }}" class="group flex items-center gap-3 min-w-0">
                        <span class="w-10 h-10 shrink-0 rounded-xl bg-surface-muted ring-1 ring-inset ring-line-light text-ink_text-secondary group-hover:bg-gold-tint group-hover:text-gold-dark group-hover:ring-gold-soft flex items-center justify-center transition-colors">
                            <x-ui.icon name="archive" :size="17" />
                        </span>
                        <span class="min-w-0">
                            <span class="block rj-code text-ink_text-primary group-hover:text-gold-dark">{{ $box->code }}</span>
                            <span class="block text-[12.5px] text-ink_text-secondary truncate">{{ $box->label ?: 'No label' }}</span>
                        </span>
                    </a>
                </td>
                <td class="text-right tabular font-semibold">{{ $box->packets_count }}</td>
                <td class="text-right tabular">{{ $box->items_count }}</td>
                <td class="text-right tabular text-ink_text-secondary">{{ $box->items_sum_weight ? number_format($box->items_sum_weight, 3) . ' g' : '-' }}</td>
                <td>
                    @if ($box->qr_codes_exists)
                        <x-ui.badge tone="success" size="sm">Issued</x-ui.badge>
                    @else
                        <x-ui.badge size="sm">Not issued</x-ui.badge>
                    @endif
                </td>
                <td class="text-ink_text-secondary text-[12.5px] whitespace-nowrap">{{ $box->created_at?->format('d M Y') }}</td>
                <td>
                    <div class="flex items-center justify-end gap-1">
                        <x-ui.button variant="ghost" size="icon-sm" icon="qr-code" wire:click="printQr({{ $box->id }})" title="Print QR label" aria-label="Print QR label for {{ $box->code }}" />
                        <x-ui.button variant="ghost" size="icon-sm" icon="edit" wire:click="edit({{ $box->id }})" title="Edit" aria-label="Edit {{ $box->code }}" />
                        <x-ui.button variant="secondary" size="sm" iconRight="arrow-right" :href="route('stock.boxes.show', $box)">Open</x-ui.button>
                    </div>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="8">
                    @if ($this->hasActiveFilters())
                        <x-ui.empty-state icon="search" title="No boxes match these filters" message="Try a different code or clear the filters.">
                            <x-ui.button variant="secondary" size="sm" wire:click="resetFilters">Clear filters</x-ui.button>
                        </x-ui.empty-state>
                    @else
                        <x-ui.empty-state icon="archive" title="No boxes yet" message="Create the first box, then add packets to it.">
                            <x-ui.button size="sm" icon="plus" wire:click="create">New box</x-ui.button>
                        </x-ui.empty-state>
                    @endif
                </td>
            </tr>
        @endforelse
    </x-ui.datatable>

    <x-ui.modal wire:model="showForm" :title="$editingId ? 'Edit box' : 'New box'" icon="archive" max-width="md" submit="save"
        :subtitle="$editingId ? 'Renaming keeps all of the box\'s history.' : 'Give the box a code that matches its physical label.'">
        <div class="space-y-4">
            <x-ui.field label="Box code" for="box-code" error="code" hint="Printed on the box and its QR label. Must be unique.">
                <input id="box-code" type="text" wire:model="code" class="rj-input rj-code uppercase @error('code') is-invalid @enderror" autocomplete="off" autofocus>
            </x-ui.field>
            <x-ui.field label="Label" for="box-label" error="label" optional hint="Where it lives, e.g. Vault shelf 2.">
                <input id="box-label" type="text" wire:model="label" maxlength="100" class="rj-input @error('label') is-invalid @enderror">
            </x-ui.field>
        </div>
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="show = false">Cancel</x-ui.button>
            <x-ui.button type="submit" target="save" icon="check">{{ $editingId ? 'Save changes' : 'Create box' }}</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
