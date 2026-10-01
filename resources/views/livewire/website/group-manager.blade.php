@php
    $noun = $isCategory ? 'category' : 'collection';
    $param = $isCategory ? 'category' : 'collection';
@endphp
<div>
    <x-ui.page-header :title="$isCategory ? 'Website categories' : 'Collections'"
        :subtitle="$isCategory
            ? 'How the public website groups your pieces. Each one gathers one or more stock categories.'
            : 'Named stories on the website, like Temple or Daily Gold. Pieces join one from their Website tab.'"
        :crumbs="[['label' => 'Website'], ['label' => $isCategory ? 'Categories' : 'Collections']]">
        <x-slot:actions>
            <x-ui.button variant="secondary" icon="external-link" :href="route('storefront.catalog')" target="_blank">View on website</x-ui.button>
            <x-ui.button icon="plus" wire:click="create">New {{ $noun }}</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    @if ($isCategory && $unmapped->isNotEmpty())
        <div class="flex items-start gap-3 mb-5 px-4 py-3 rounded-card bg-warning-bg ring-1 ring-inset ring-warning/20 text-[13px] text-ink_text-primary">
            <x-ui.icon name="info" :size="16" class="shrink-0 mt-0.5 text-warning" />
            <div>
                <span class="font-semibold">Not on the website yet:</span>
                {{ $unmapped->join(', ') }}.
                <span class="text-ink_text-secondary">Pieces in these stock categories can't be listed until a website category includes them.</span>
            </div>
        </div>
    @endif

    <x-ui.datatable :paginator="$groups">
        <x-slot:toolbar>
            <x-ui.search-input wire:model.live.debounce.300ms="search" placeholder="Search {{ Str::plural($noun) }}" class="w-full sm:w-[280px]" />
            @if ($this->hasActiveFilters())
                <x-ui.button variant="ghost" size="sm" icon="x" wire:click="resetFilters">Clear</x-ui.button>
            @endif
        </x-slot:toolbar>

        <x-slot:head>
            <x-ui.th field="order" :sort-field="$this->currentSortField()" :sort-direction="$this->currentSortDirection()">#</x-ui.th>
            <x-ui.th field="name" :sort-field="$this->currentSortField()" :sort-direction="$this->currentSortDirection()">{{ ucfirst($noun) }}</x-ui.th>
            @if ($isCategory)
                <x-ui.th>Includes stock categories</x-ui.th>
            @endif
            <x-ui.th align="right">On the site</x-ui.th>
            <x-ui.th>Status</x-ui.th>
            <x-ui.th align="right"><span class="sr-only">Actions</span></x-ui.th>
        </x-slot:head>

        @forelse ($groups as $g)
            <tr wire:key="group-{{ $g->id }}" @class(['opacity-60' => ! $g->is_active])>
                <td class="text-ink_text-muted tabular w-12">{{ $g->sort_order }}</td>
                <td>
                    <div class="flex items-center gap-3 min-w-0">
                        @if ($g->image_url)
                            <img src="{{ \App\Support\StorefrontImage::sized($g->image_url, 96, 96) }}" alt="" class="w-11 h-11 rounded-full object-cover ring-1 ring-line shrink-0">
                        @else
                            <span class="w-11 h-11 rounded-full bg-surface-muted ring-1 ring-line flex items-center justify-center text-ink_text-muted shrink-0"><x-ui.icon name="image" :size="16" /></span>
                        @endif
                        <div class="min-w-0">
                            <div class="font-semibold text-ink_text-primary flex items-center gap-2">
                                {{ $g->name }}
                                @if ($isCategory && $g->in_menu)
                                    <x-ui.badge tone="gold" size="sm">Menu bar</x-ui.badge>
                                @endif
                            </div>
                            <div class="rj-code text-[12px] text-ink_text-muted truncate">/shop?{{ $param }}={{ $g->slug }}</div>
                        </div>
                    </div>
                </td>
                @if ($isCategory)
                    <td>
                        <div class="flex flex-wrap gap-1.5">
                            @foreach (collect($g->stock_categories ?? [])->push($g->name)->unique(fn ($c) => mb_strtolower($c)) as $sc)
                                <x-ui.badge size="sm">{{ $sc }}</x-ui.badge>
                            @endforeach
                        </div>
                    </td>
                @endif
                <td class="text-right tabular">
                    <span class="font-semibold text-ink_text-primary">{{ $live[$g->slug] ?? 0 }}</span>
                    <span class="text-ink_text-muted text-[12px]">{{ Str::plural('piece', $live[$g->slug] ?? 0) }}</span>
                </td>
                <td>
                    <x-ui.badge :tone="$g->is_active ? 'success' : 'neutral'" size="sm" dot>{{ $g->is_active ? 'Showing' : 'Hidden' }}</x-ui.badge>
                </td>
                <td>
                    <div class="flex items-center justify-end gap-1">
                        @if ($g->is_active && ($live[$g->slug] ?? 0))
                            <x-ui.button variant="ghost" size="icon-sm" icon="external-link" :href="route('storefront.catalog', [$param => $g->slug])" target="_blank" title="Open on the website" aria-label="Open {{ $g->name }} on the website" />
                        @endif
                        <x-ui.button variant="ghost" size="icon-sm" icon="edit" wire:click="edit({{ $g->id }})" title="Edit" aria-label="Edit {{ $g->name }}" />
                        <x-ui.button variant="ghost" size="icon-sm" :icon="$g->is_active ? 'eye-off' : 'eye'"
                            :title="$g->is_active ? 'Hide from website' : 'Show on website'" aria-label="{{ $g->is_active ? 'Hide' : 'Show' }} {{ $g->name }}"
                            x-on:click="$dispatch('rj-confirm', {
                                title: '{{ $g->is_active ? 'Hide '.e($g->name).' from the website?' : 'Show '.e($g->name).' on the website?' }}',
                                message: '{{ $g->is_active ? 'Its pieces stay in stock; they just stop appearing under this '.$noun.' online.' : 'It will appear on the website again with its pieces.' }}',
                                confirm: '{{ $g->is_active ? 'Hide' : 'Show' }}', tone: '{{ $g->is_active ? 'danger' : 'default' }}',
                                action: () => $wire.toggleActive({{ $g->id }}) })" />
                    </div>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="{{ $isCategory ? 6 : 5 }}">
                    <x-ui.empty-state icon="globe" title="No {{ Str::plural($noun) }} yet" message="Add the first one to start building the website." compact>
                        <x-ui.button size="sm" icon="plus" wire:click="create">New {{ $noun }}</x-ui.button>
                    </x-ui.empty-state>
                </td>
            </tr>
        @endforelse
    </x-ui.datatable>

    <x-ui.modal wire:model="showForm" :title="$editingId ? 'Edit '.$noun : 'New '.$noun" icon="globe" max-width="xl" submit="save"
        subtitle="Shown on the public website.">
        <div class="grid grid-cols-1 sm:grid-cols-[180px_minmax(0,1fr)] gap-6">
            {{-- Cover photo --}}
            <div>
                <label class="rj-label">Cover photo</label>
                <label class="relative block aspect-square rounded-card overflow-hidden ring-1 ring-line bg-surface-sunken cursor-pointer group">
                    @if ($photo)
                        <img src="{{ $photo->temporaryUrl() }}" alt="" class="absolute inset-0 w-full h-full object-cover">
                    @elseif ($currentImage)
                        <img src="{{ \App\Support\StorefrontImage::sized($currentImage, 400, 400) }}" alt="" class="absolute inset-0 w-full h-full object-cover">
                    @endif
                    <span @class([
                        'absolute inset-0 flex flex-col items-center justify-center gap-1.5 text-center px-3 text-[12.5px] font-semibold transition-opacity',
                        'bg-ink/55 text-white opacity-0 group-hover:opacity-100' => $photo || $currentImage,
                        'text-ink_text-secondary' => ! $photo && ! $currentImage,
                    ])>
                        <x-ui.icon name="camera" :size="20" wire:loading.remove wire:target="photo" />
                        <x-ui.icon name="loader" :size="20" class="animate-spin" wire:loading wire:target="photo" />
                        {{ $photo || $currentImage ? 'Change photo' : 'Add a photo' }}
                    </span>
                    <input type="file" wire:model="photo" accept="image/*" class="absolute inset-0 opacity-0 cursor-pointer">
                </label>
                @error('photo') <p class="rj-error"><x-ui.icon name="alert-triangle" :size="12" />{{ $message }}</p> @enderror
                <p class="rj-help">Square works best. Saved as a compressed WebP.</p>
            </div>

            <div class="space-y-4 min-w-0">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <x-ui.field label="Name" for="g-name" error="name">
                        <input id="g-name" type="text" wire:model.live.debounce.400ms="name" class="rj-input @error('name') is-invalid @enderror" autofocus autocomplete="off">
                    </x-ui.field>
                    <x-ui.field label="Web address" for="g-slug" error="slug" :hint="$editingId ? 'Changing it breaks links people already shared.' : null">
                        <input id="g-slug" type="text" wire:model="slug" class="rj-input rj-code @error('slug') is-invalid @enderror" autocomplete="off">
                    </x-ui.field>
                </div>

                <x-ui.field label="Short description" for="g-blurb" error="blurb" optional hint="One line, shown under the name on the website.">
                    <textarea id="g-blurb" rows="2" wire:model="blurb" class="rj-textarea"></textarea>
                </x-ui.field>

                @if ($isCategory)
                    <x-ui.field label="Also includes these stock categories" for="g-stock" error="stockCategories" optional
                        hint="Comma separated, as typed on pieces in Stock. The category's own name always counts.">
                        <input id="g-stock" type="text" list="stock-category-options" wire:model="stockCategories" class="rj-input" autocomplete="off" placeholder="Bangle, Chudi, Kada">
                        <datalist id="stock-category-options">
                            @foreach ($stockCategoryOptions as $c)<option value="{{ $c }}"></option>@endforeach
                        </datalist>
                    </x-ui.field>
                @endif

                <div class="grid grid-cols-1 sm:grid-cols-[120px_minmax(0,1fr)] gap-4 items-end">
                    <x-ui.field label="Order" for="g-order" error="sort_order">
                        <input id="g-order" type="number" min="0" wire:model="sort_order" class="rj-input tabular">
                    </x-ui.field>
                    <div class="flex flex-wrap gap-x-6 gap-y-3 pb-2.5">
                        <label class="inline-flex items-center gap-2.5 text-[13.5px] font-semibold text-ink_text-primary cursor-pointer">
                            <input type="checkbox" wire:model="is_active" class="rj-checkbox"> Show on the website
                        </label>
                        @if ($isCategory)
                            <label class="inline-flex items-center gap-2.5 text-[13.5px] font-semibold text-ink_text-primary cursor-pointer">
                                <input type="checkbox" wire:model="in_menu" class="rj-checkbox"> In the menu bar
                            </label>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="show = false">Cancel</x-ui.button>
            <x-ui.button type="submit" target="save" icon="check">{{ $editingId ? 'Save changes' : 'Add '.$noun }}</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
