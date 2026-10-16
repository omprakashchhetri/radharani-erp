<div>
    <x-ui.page-header title="Unassigned items" subtitle="Pieces not in a packet are on hold: they are not counted as stock, shown on the website or sold until you put them in a packet."
        :crumbs="[['label' => 'Stock', 'href' => route('stock.items')], ['label' => 'Unassigned items']]">
        <x-slot:actions>
            <x-ui.button variant="secondary" icon="download" wire:click="exportAll" target="exportAll">Export all as CSV</x-ui.button>
            <x-ui.button variant="secondary" icon="package" :href="route('stock.assign')">Change item location</x-ui.button>
            <x-ui.button icon="upload" :href="route('stock.import')">Bulk import</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="flex flex-wrap items-center gap-3 mb-5">
        <x-ui.search-input scan wire:model.live.debounce.300ms="search" placeholder="HUID, code, category or description" class="w-full sm:w-[320px]" />
        <label class="flex items-center gap-2 text-[13px]"><input type="checkbox" class="rj-checkbox" wire:model.live="incompleteOnly"> Incomplete only</label>
        <span class="text-[13px] text-ink_text-secondary">{{ $total }} {{ \Illuminate\Support\Str::plural('piece', $total) }} in {{ $batches->count() }} {{ \Illuminate\Support\Str::plural('batch', $batches->count()) }}</span>
        @if (count($selected))
            <div class="ml-auto flex items-center gap-2 px-3 py-1.5 rounded-control bg-ink text-white">
                <span class="text-[13px] font-semibold"><span class="text-gold-light tabular">{{ count($selected) }}</span> selected</span>
                <x-ui.button size="sm" variant="secondary" icon="package" wire:click="openMove">Move to packet</x-ui.button>
                <x-ui.button size="sm" variant="secondary" icon="edit" wire:click="openEdit">Edit selected</x-ui.button>
                <x-ui.button size="sm" variant="danger-soft" icon="trash"
                    x-on:click="$dispatch('rj-confirm', { title: 'Delete {{ count($selected) }} piece(s)?', message: 'They are removed from the lists. A piece that was sold or is out of the store is kept.', confirm: 'Delete', tone: 'danger', action: () => $wire.deleteSelected() })">Delete</x-ui.button>
            </div>
        @endif
    </div>

    @forelse ($batches as $b)
        @php $allOn = ! array_diff($b['ids'], $selected); @endphp
        <x-ui.card :padding="false" class="mb-5" wire:key="batch-{{ $b['key'] }}">
            <div class="flex flex-wrap items-center gap-3 px-5 py-3.5 border-b border-line-light">
                <input type="checkbox" class="rj-checkbox" aria-label="Select this batch" @checked($allOn)
                    x-on:change="$wire.toggleBatch('{{ $b['key'] }}', $event.target.checked)">
                <div class="flex-1">
                    <div class="font-display text-[20px] leading-tight font-semibold text-ink_text-primary">{{ $b['label'] }}</div>
                    <div class="text-[12.5px] text-ink_text-muted">{{ $b['kind'] }} · {{ count($b['rows']) }} {{ \Illuminate\Support\Str::plural('piece', count($b['rows'])) }} · {{ number_format($b['weight'], 3) }} g</div>
                </div>
                <x-ui.button variant="secondary" size="sm" icon="download" wire:click="exportBatch('{{ $b['key'] }}')" target="exportBatch">Export CSV</x-ui.button>
            </div>
            <x-ui.table :headers="['', 'Piece', 'Category', 'Metal', 'Weight', 'Status', '']">
                @foreach ($b['rows'] as $item)
                    <tr wire:key="un-{{ $item->id }}" @class(['is-selected' => in_array((string) $item->id, $selected, true)])>
                        <td class="!pr-0 w-10"><input type="checkbox" class="rj-checkbox" value="{{ $item->id }}" wire:model.live="selected" aria-label="Select {{ $item->label }}"></td>
                        <td><a href="{{ route('stock.items.show', $item) }}" class="rj-code text-ink_text-primary hover:text-gold-dark">{{ $item->label }}</a></td>
                        <td>{{ $item->category }}</td>
                        <td>{{ $item->metal ? ucfirst($item->metal) : '-' }} <span class="text-ink_text-muted">{{ $item->purity }}</span></td>
                        <td class="tabular">{{ number_format($item->weight, 3) }} g</td>
                        <td><x-ui.status :status="$item->status" />
                            @if ($item->missingDetails())<x-ui.badge tone="warning" size="sm" title="Missing: {{ implode(', ', $item->missingDetails()) }}">Incomplete: {{ implode(', ', $item->missingDetails()) }}</x-ui.badge>@endif</td>
                        <td class="text-right">
                            <x-ui.button variant="ghost" size="icon-sm" icon="edit" x-on:click="Livewire.dispatch('open-item-form', { id: {{ $item->id }} })" aria-label="Edit {{ $item->label }}" />
                        </td>
                    </tr>
                @endforeach
            </x-ui.table>
        </x-ui.card>
    @empty
        <x-ui.card>
            <x-ui.empty-state icon="package" title="Nothing unassigned" message="{{ $incompleteOnly ? 'No unassigned piece is missing details.' : 'Every piece is in a packet. Pieces imported or added without a packet will show up here.' }}" />
        </x-ui.card>
    @endforelse

    <x-ui.modal wire:model="showEdit" title="Edit selected pieces" icon="edit" max-width="md" submit="saveEdit"
        subtitle="{{ count($selected) }} piece(s). Only what you fill in is changed; the rest of each piece stays as it is.">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <x-ui.field label="Metal" for="ue-metal" error="editMetal" optional>
                <select id="ue-metal" wire:model.live="editMetal" class="rj-select"><option value="">Leave as it is</option>@foreach (\App\Livewire\Stock\ItemForm::METALS as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select>
            </x-ui.field>
            <x-ui.field label="Purity" for="ue-purity" error="editPurity" optional>
                <select id="ue-purity" wire:model="editPurity" class="rj-select"><option value="">Leave as it is</option>@foreach ($editPurities as $p)<option value="{{ $p }}">{{ $p }}</option>@endforeach</select>
            </x-ui.field>
            <x-ui.field label="Category" for="ue-cat" error="editCategoryId" optional hint="Pick a metal first to see its categories.">
                <select id="ue-cat" wire:model="editCategoryId" class="rj-select"><option value="">Leave as it is</option>@foreach ($editCategories as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select>
            </x-ui.field>
            <x-ui.field label="Making charge type" for="ue-mt" error="editMakingType" optional>
                <select id="ue-mt" wire:model="editMakingType" class="rj-select"><option value="">Leave as it is</option><option value="percentage">Percentage</option><option value="flat_per_piece">Flat per piece</option><option value="flat_per_gram">Flat per gram</option></select>
            </x-ui.field>
            <x-ui.field label="Making charge value" for="ue-mv" error="editMakingValue" optional>
                <input id="ue-mv" type="number" step="0.01" min="0" wire:model="editMakingValue" class="rj-input tabular">
            </x-ui.field>
        </div>
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="show = false">Cancel</x-ui.button>
            <x-ui.button type="submit" target="saveEdit" icon="check">Save changes</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>

    <x-ui.modal wire:model="showMove" title="Move to packet" icon="package" max-width="sm" submit="moveSelected"
        subtitle="{{ count($selected) }} piece(s). Each move is recorded in the piece's history.">
        <x-ui.field label="Packet" for="un-packet" error="moveToPacketId">
            <select id="un-packet" wire:model="moveToPacketId" autofocus class="rj-select">
                <option value="">Choose a packet...</option>
                @foreach ($packets as $box => $list)
                    <optgroup label="{{ $box }}">
                        @foreach ($list as $p)<option value="{{ $p->id }}">{{ $p->code }}{{ $p->label ? ' · ' . $p->label : '' }}</option>@endforeach
                    </optgroup>
                @endforeach
            </select>
        </x-ui.field>
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="show = false">Cancel</x-ui.button>
            <x-ui.button type="submit" target="moveSelected" icon="package">Move</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>

    <livewire:stock.item-form />
</div>
