<div>
    <x-ui.page-header title="Vendors" subtitle="Karigars, suppliers, and hallmarking centres."
        :crumbs="[['label' => 'Purchases & Vendors', 'href' => route('purchases.list')], ['label' => 'Vendors']]">
        <x-slot:actions>
            <x-ui.button icon="plus" wire:click="create">New vendor</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.datatable :paginator="$vendors">
        <x-slot:toolbar>
            <x-ui.search-input wire:model.live.debounce.300ms="search" placeholder="Search name or phone" class="w-full sm:w-[280px]" />
            <select wire:model.live="typeFilter" class="rj-select rj-input-sm">
                <option value="">All types</option>
                <option value="karigar">Karigar</option>
                <option value="supplier">Supplier</option>
                <option value="hallmark_center">Hallmarking Centre</option>
            </select>
            @if ($this->hasActiveFilters())
                <x-ui.button variant="ghost" size="sm" icon="x" wire:click="resetFilters">Clear</x-ui.button>
            @endif
        </x-slot:toolbar>

        <x-slot:head>
            <x-ui.th field="name" :sort-field="$this->currentSortField()" :sort-direction="$this->currentSortDirection()">Name</x-ui.th>
            <x-ui.th field="type" :sort-field="$this->currentSortField()" :sort-direction="$this->currentSortDirection()">Type</x-ui.th>
            <x-ui.th>Phone</x-ui.th>
            <x-ui.th field="balance" :sort-field="$this->currentSortField()" :sort-direction="$this->currentSortDirection()" align="right">Balance</x-ui.th>
            <x-ui.th align="right"><span class="sr-only">Actions</span></x-ui.th>
        </x-slot:head>

        @forelse ($vendors as $v)
            <tr wire:key="vendor-{{ $v->id }}">
                <td class="font-semibold text-ink_text-primary">{{ $v->name }}</td>
                <td><x-ui.badge tone="neutral">{{ str($v->type)->replace('_', ' ')->title() }}</x-ui.badge></td>
                <td class="text-ink_text-primary">{{ $v->phone ?: '—' }}</td>
                <td class="text-right tabular text-ink_text-primary">₹{{ number_format($v->balance, 2) }}</td>
                <td>
                    <div class="flex justify-end">
                        <x-ui.button variant="ghost" size="icon-sm" icon="edit" wire:click="edit({{ $v->id }})" title="Edit" aria-label="Edit {{ $v->name }}" />
                    </div>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="5">
                    @if ($this->hasActiveFilters())
                        <x-ui.empty-state icon="search" title="No vendors match these filters" message="Try a different name, phone or type.">
                            <x-ui.button variant="secondary" size="sm" wire:click="resetFilters">Clear filters</x-ui.button>
                        </x-ui.empty-state>
                    @else
                        <x-ui.empty-state icon="building" title="No vendors yet" message="Add the first karigar, supplier, or hallmarking centre.">
                            <x-ui.button size="sm" icon="plus" wire:click="create">New vendor</x-ui.button>
                        </x-ui.empty-state>
                    @endif
                </td>
            </tr>
        @endforelse
    </x-ui.datatable>

    <x-ui.modal wire:model="showForm" :title="$editingId ? 'Edit vendor' : 'New vendor'" icon="building" max-width="md" submit="save"
        :subtitle="$editingId ? 'Balance is a manually-maintained figure, not auto-calculated from purchases.' : 'Karigar, supplier, or hallmarking centre.'">
        <div class="space-y-4">
            <x-ui.field label="Name" for="v-name" error="name">
                <input id="v-name" type="text" class="rj-input w-full @error('name') is-invalid @enderror" wire:model="name" autofocus>
            </x-ui.field>
            <x-ui.field label="Type" for="v-type">
                <select id="v-type" class="rj-select w-full" wire:model="type">
                    <option value="karigar">Karigar</option>
                    <option value="supplier">Supplier</option>
                    <option value="hallmark_center">Hallmarking Centre</option>
                </select>
            </x-ui.field>
            <x-ui.field label="Phone" for="v-phone" optional>
                <input id="v-phone" type="text" class="rj-input w-full" wire:model="phone">
            </x-ui.field>
            <x-ui.field label="Address" for="v-address" optional>
                <input id="v-address" type="text" class="rj-input w-full" wire:model="address">
            </x-ui.field>
            <x-ui.field label="Opening / current balance (₹)" for="v-balance" error="balance"
                :hint="$editingId ? 'Total purchased (lifetime): ₹'.number_format($this->totalPurchased, 2).' — for reference only, not used to calculate balance.' : null">
                <input id="v-balance" type="number" step="0.01" class="rj-input w-full tabular @error('balance') is-invalid @enderror" wire:model="balance">
            </x-ui.field>
        </div>
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="show = false">Cancel</x-ui.button>
            <x-ui.button type="submit" target="save" icon="check">{{ $editingId ? 'Save changes' : 'Add vendor' }}</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
