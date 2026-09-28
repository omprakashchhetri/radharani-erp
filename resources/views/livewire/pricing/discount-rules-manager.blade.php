<div>
    <x-ui.page-header title="Discount Rules" subtitle="Scope to an item, packet, box, category, or weight range."
        :crumbs="[['label' => 'Pricing & Rates', 'href' => route('pricing.rates')], ['label' => 'Discount Rules']]">
        <x-slot:actions>
            <x-ui.button icon="plus" wire:click="create">New rule</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
        <x-ui.stat-card icon="percent" label="Total rules" :value="number_format($stats['total'])" />
        <x-ui.stat-card icon="check-circle" label="Active" :value="number_format($stats['active'])" hint="applying to sales now" />
        <x-ui.stat-card icon="tag" label="Category rules" :value="number_format($stats['category'])" />
        <x-ui.stat-card icon="scale" label="Weight-tier rules" :value="number_format($stats['weightTier'])" />
    </div>

    <x-ui.datatable :paginator="$rules">
        <x-slot:toolbar>
            <x-ui.search-input wire:model.live.debounce.300ms="search" placeholder="Search by category" class="w-full sm:w-[260px]" />
            <select wire:model.live="scopeFilter" class="rj-select rj-input-sm">
                <option value="">All scopes</option>
                <option value="item">Item</option>
                <option value="packet">Packet</option>
                <option value="box">Box</option>
                <option value="category">Category</option>
                <option value="weight_tier">Weight range</option>
            </select>
            <div class="rj-segment">
                @foreach (['' => 'All', 'active' => 'Active', 'inactive' => 'Inactive'] as $value => $name)
                    <button type="button" wire:click="$set('activeFilter', '{{ $value }}')" class="{{ $activeFilter === $value ? 'is-active' : '' }}">{{ $name }}</button>
                @endforeach
            </div>
            @if ($this->hasActiveFilters())
                <x-ui.button variant="ghost" size="sm" icon="x" wire:click="resetFilters">Clear</x-ui.button>
            @endif
        </x-slot:toolbar>

        <x-slot:head>
            <x-ui.th field="scope" :sort-field="$this->currentSortField()" :sort-direction="$this->currentSortDirection()">Scope</x-ui.th>
            <x-ui.th field="value" :sort-field="$this->currentSortField()" :sort-direction="$this->currentSortDirection()">Discount</x-ui.th>
            <x-ui.th>Valid</x-ui.th>
            <x-ui.th>Status</x-ui.th>
            <x-ui.th field="created" :sort-field="$this->currentSortField()" :sort-direction="$this->currentSortDirection()">Created</x-ui.th>
            <x-ui.th align="right"><span class="sr-only">Actions</span></x-ui.th>
        </x-slot:head>

        @forelse ($rules as $r)
            <tr wire:key="rule-{{ $r->id }}">
                <td>
                    <span class="font-semibold text-ink_text-primary capitalize">{{ str_replace('_', ' ', $r->scope) }}</span>
                    <span class="block text-ink_text-secondary text-[11.5px]">
                        @if ($r->scope === 'category') {{ $r->category }}
                        @elseif ($r->scope === 'weight_tier') {{ $r->min_weight ?? '0' }}g–{{ $r->max_weight ?? '∞' }}g
                        @elseif ($r->scope_ref_id) #{{ $r->scope_ref_id }}
                        @endif
                    </span>
                </td>
                <td class="text-ink_text-primary tabular">{{ $r->discount_type === 'percentage' ? $r->value.'%' : '₹'.number_format($r->value, 2) }}</td>
                <td class="text-[11.5px] text-ink_text-primary whitespace-nowrap">{{ $r->valid_from?->format('d M Y') ?? 'any' }} – {{ $r->valid_to?->format('d M Y') ?? 'any' }}</td>
                <td><x-ui.badge :tone="$r->active ? 'success' : 'neutral'">{{ $r->active ? 'Active' : 'Inactive' }}</x-ui.badge></td>
                <td class="text-ink_text-secondary text-[12.5px] whitespace-nowrap">{{ $r->created_at?->format('d M Y') }}</td>
                <td>
                    <div class="flex items-center justify-end gap-1">
                        <x-ui.button variant="ghost" size="icon-sm" icon="edit" wire:click="edit({{ $r->id }})" title="Edit" aria-label="Edit rule" />
                        @if ($r->active)
                            <x-ui.button variant="ghost" size="icon-sm" icon="x" title="Deactivate" aria-label="Deactivate rule"
                                x-on:click="$dispatch('rj-confirm', { title: 'Deactivate this rule?', message: 'It will stop applying to new sales immediately. Create a new rule to re-enable it.', confirm: 'Deactivate', tone: 'danger', action: () => $wire.deactivate({{ $r->id }}) })" />
                        @endif
                    </div>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="6">
                    @if ($this->hasActiveFilters())
                        <x-ui.empty-state icon="search" title="No rules match these filters" message="Try a different scope or clear the filters.">
                            <x-ui.button variant="secondary" size="sm" wire:click="resetFilters">Clear filters</x-ui.button>
                        </x-ui.empty-state>
                    @else
                        <x-ui.empty-state icon="percent" title="No discount rules yet" message="Create the first rule to start discounting stock automatically.">
                            <x-ui.button size="sm" icon="plus" wire:click="create">New rule</x-ui.button>
                        </x-ui.empty-state>
                    @endif
                </td>
            </tr>
        @endforelse
    </x-ui.datatable>

    <x-ui.modal wire:model="showForm" :title="$editingId ? 'Edit rule' : 'New rule'" icon="percent" max-width="lg" submit="save"
        :subtitle="$editingId ? 'Changes apply the next time this rule is matched.' : 'Pick what the rule applies to, then set the discount.'">
        <div class="grid grid-cols-2 gap-4">
            <x-ui.field label="Scope" for="dr-scope" class="col-span-full">
                <select id="dr-scope" wire:model.live="scope" class="rj-select w-full">
                    <option value="item">Item</option>
                    <option value="packet">Packet</option>
                    <option value="box">Box</option>
                    <option value="category">Category</option>
                    <option value="weight_tier">Weight range</option>
                </select>
            </x-ui.field>

            @if ($scope === 'item')
                <x-ui.field label="Item" for="dr-ref" class="col-span-full">
                    <select id="dr-ref" wire:model="scopeRefId" class="rj-select w-full">
                        <option value="">— select —</option>
                        @foreach ($items as $i) <option value="{{ $i->id }}">{{ $i->huid_code ?: $i->internal_code }} — {{ $i->category }}</option> @endforeach
                    </select>
                </x-ui.field>
            @elseif ($scope === 'packet')
                <x-ui.field label="Packet" for="dr-ref" class="col-span-full">
                    <select id="dr-ref" wire:model="scopeRefId" class="rj-select w-full">
                        <option value="">— select —</option>
                        @foreach ($packets as $p) <option value="{{ $p->id }}">{{ $p->code }}</option> @endforeach
                    </select>
                </x-ui.field>
            @elseif ($scope === 'box')
                <x-ui.field label="Box" for="dr-ref" class="col-span-full">
                    <select id="dr-ref" wire:model="scopeRefId" class="rj-select w-full">
                        <option value="">— select —</option>
                        @foreach ($boxes as $b) <option value="{{ $b->id }}">{{ $b->code }}</option> @endforeach
                    </select>
                </x-ui.field>
            @elseif ($scope === 'category')
                <x-ui.field label="Category" for="dr-category" error="category" class="col-span-full">
                    <input id="dr-category" type="text" wire:model="category" class="rj-input w-full @error('category') is-invalid @enderror">
                </x-ui.field>
            @else
                <x-ui.field label="Min weight (g)" for="dr-min" error="minWeight">
                    <input id="dr-min" type="number" step="0.001" wire:model="minWeight" class="rj-input w-full @error('minWeight') is-invalid @enderror">
                </x-ui.field>
                <x-ui.field label="Max weight (g)" for="dr-max" error="maxWeight">
                    <input id="dr-max" type="number" step="0.001" wire:model="maxWeight" class="rj-input w-full @error('maxWeight') is-invalid @enderror">
                </x-ui.field>
            @endif

            <x-ui.field label="Discount type" for="dr-type">
                <select id="dr-type" wire:model="discountType" class="rj-select w-full">
                    <option value="percentage">Percentage</option>
                    <option value="flat">Flat (₹)</option>
                </select>
            </x-ui.field>
            <x-ui.field label="Value" for="dr-value" error="value">
                <input id="dr-value" type="number" step="0.01" wire:model="value" class="rj-input w-full @error('value') is-invalid @enderror">
            </x-ui.field>

            <x-ui.field label="Valid from" for="dr-from" optional error="validFrom">
                <input id="dr-from" type="date" wire:model="validFrom" class="rj-input w-full @error('validFrom') is-invalid @enderror">
            </x-ui.field>
            <x-ui.field label="Valid to" for="dr-to" optional error="validTo">
                <input id="dr-to" type="date" wire:model="validTo" class="rj-input w-full @error('validTo') is-invalid @enderror">
            </x-ui.field>

            <label class="col-span-full flex items-center gap-2 text-[12.5px] text-ink_text-primary">
                <input type="checkbox" wire:model="active" class="rj-checkbox">
                Active
            </label>
        </div>
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="show = false">Cancel</x-ui.button>
            <x-ui.button type="submit" target="save" icon="check">{{ $editingId ? 'Save changes' : 'Create rule' }}</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
