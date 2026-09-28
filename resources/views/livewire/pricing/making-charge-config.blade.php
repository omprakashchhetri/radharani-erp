<div>
    <x-ui.page-header title="Making-Charge Configuration" subtitle="Category-level defaults for the making charge staff see pre-filled in Stock → Add/Edit Item."
        :crumbs="[['label' => 'Pricing & Rates', 'href' => route('pricing.rates')], ['label' => 'Making Charges']]" />

    <div class="grid grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
        <x-ui.stat-card icon="percent" label="Presets configured" :value="number_format($presets->count())" />
        <x-ui.stat-card icon="tag" label="Percentage type" :value="number_format($presets->where('type', 'percentage')->count())" />
        <x-ui.stat-card icon="gem" label="Flat per piece" :value="number_format($presets->where('type', 'flat_per_piece')->count())" />
        <x-ui.stat-card icon="scale" label="Flat per gram" :value="number_format($presets->where('type', 'flat_per_gram')->count())" />
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-[minmax(0,1fr)_360px] gap-6 items-start">
        <x-ui.card :title="$editingId ? 'Edit preset' : 'New preset'" :subtitle="$editingId ? 'Changes apply the next time a matching item is added.' : 'Set a default so staff do not have to type it every time.'" icon="percent">
            <form wire:submit="save">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <x-ui.field label="Category" for="mc-category" error="category" hint="Matches the category field on Stock items.">
                        <input id="mc-category" type="text" wire:model="category" class="rj-input @error('category') is-invalid @enderror">
                    </x-ui.field>

                    <x-ui.field :label="$selectedType === 'percentage' ? 'Percentage (%)' : ($selectedType === 'flat_per_piece' ? 'Amount per piece (₹)' : 'Amount per gram (₹)')"
                        for="mc-value" error="value">
                        <div class="rj-input-icon">
                            <x-ui.icon :name="$selectedType === 'percentage' ? 'percent' : 'coins'" :size="16" />
                            <input id="mc-value" type="number" step="0.01" wire:model="value" class="rj-input tabular @error('value') is-invalid @enderror">
                        </div>
                    </x-ui.field>
                </div>

                <div class="mt-4">
                    <label class="rj-label">Charge type</label>
                    <div class="rj-segment w-full">
                        <button type="button" wire:click="$set('selectedType','percentage')" class="{{ $selectedType === 'percentage' ? 'is-active' : '' }}">% of metal value</button>
                        <button type="button" wire:click="$set('selectedType','flat_per_piece')" class="{{ $selectedType === 'flat_per_piece' ? 'is-active' : '' }}">Flat / piece</button>
                        <button type="button" wire:click="$set('selectedType','flat_per_gram')" class="{{ $selectedType === 'flat_per_gram' ? 'is-active' : '' }}">Flat / gram</button>
                    </div>
                </div>

                <div class="flex gap-2.5 mt-6">
                    <x-ui.button type="submit" variant="primary" target="save" icon="check">{{ $editingId ? 'Save changes' : 'Add preset' }}</x-ui.button>
                    @if ($editingId)<x-ui.button type="button" variant="secondary" wire:click="cancel">Cancel</x-ui.button>@endif
                </div>
            </form>
        </x-ui.card>

        <aside class="xl:sticky xl:top-24">
            <x-ui.card title="Presets" icon="list" :padding="false">
                <x-ui.table :headers="['Category', 'Charge', '']">
                    @forelse ($presets as $p)
                    <tr wire:key="preset-{{ $p->id }}" class="h-[52px] border-b border-line-light">
                        <td class="px-4 text-ink_text-primary font-semibold truncate max-w-[110px]">{{ $p->category }}</td>
                        <td class="px-4 text-ink_text-secondary whitespace-nowrap">
                            @if ($p->type === 'percentage') {{ rtrim(rtrim(number_format($p->value, 2), '0'), '.') }}%
                            @elseif ($p->type === 'flat_per_piece') ₹{{ number_format($p->value, 2) }}/pc
                            @else ₹{{ number_format($p->value, 2) }}/g
                            @endif
                        </td>
                        <td class="px-2">
                            <div class="flex items-center justify-end gap-1">
                                <x-ui.button variant="ghost" size="icon-sm" icon="edit" wire:click="edit({{ $p->id }})" title="Edit" aria-label="Edit {{ $p->category }}" />
                                <x-ui.button variant="ghost" size="icon-sm" icon="x" title="Remove" aria-label="Remove {{ $p->category }}"
                                    x-on:click="$dispatch('rj-confirm', { title: 'Remove preset?', message: 'Staff will no longer see a pre-filled making charge for &quot;{{ $p->category }}&quot;.', confirm: 'Remove', tone: 'danger', action: () => $wire.delete({{ $p->id }}) })" />
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="3">
                            <x-ui.empty-state icon="percent" title="No presets yet" message="Add a category preset on the left." compact />
                        </td>
                    </tr>
                    @endforelse
                </x-ui.table>
            </x-ui.card>
        </aside>
    </div>
</div>
