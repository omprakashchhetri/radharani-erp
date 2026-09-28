<div>
    <x-ui.page-header title="New Custom Order" subtitle="Note which of the two rate rules applies before the customer leaves."
        :crumbs="[['label' => 'Custom Orders'], ['label' => 'New Order']]" />

    <div class="grid grid-cols-1 xl:grid-cols-[minmax(0,1fr)_360px] gap-6 items-start">
        <x-ui.card>
            <form wire:submit="submit" class="space-y-4">
                <div class="relative" x-data="{ open: true }" x-on:click.outside="open = false">
                    <x-ui.field label="Customer" error="customerId">
                        <div class="rj-input-icon">
                            <x-ui.icon name="search" :size="16" />
                            <input type="text" wire:model.live.debounce.300ms="customerSearch" x-on:focus="open = true" x-on:input="open = true"
                                autocomplete="off" placeholder="Search by name or phone..." class="rj-input">
                        </div>
                    </x-ui.field>

                    @if ($customerId && ! $customerSearch)
                        <div class="mt-2 flex items-center gap-2.5 px-3.5 py-2.5 rounded-xl bg-gold-tint ring-1 ring-gold-soft">
                            <x-ui.icon name="user-check" :size="15" class="text-gold-dark" />
                            <span class="text-[13px] font-semibold text-ink_text-primary">Customer selected</span>
                        </div>
                    @endif

                    @if ($customerSearch && $customerResults->isNotEmpty())
                        <div x-show="open" class="absolute left-0 right-0 mt-2 bg-white border border-line-light rounded-xl shadow-pop p-1.5 z-dropdown">
                            @foreach ($customerResults as $c)
                                <button type="button" wire:click="$set('customerId', {{ $c->id }})"
                                    class="w-full flex items-center gap-3 px-2.5 py-2 rounded-lg hover:bg-surface-muted text-left {{ $customerId === $c->id ? 'bg-gold-tint' : '' }}">
                                    <span class="w-8 h-8 rounded-full bg-surface-sunken ring-1 ring-inset ring-line-light flex items-center justify-center text-ink_text-muted shrink-0"><x-ui.icon name="user" :size="14" /></span>
                                    <span class="flex-1 min-w-0">
                                        <span class="block text-[13px] font-semibold text-ink_text-primary truncate">{{ $c->name }}</span>
                                        <span class="block text-[12px] text-ink_text-muted">{{ $c->phone }}</span>
                                    </span>
                                    @if ($customerId === $c->id) <x-ui.icon name="check" :size="14" class="text-gold-dark shrink-0" /> @endif
                                </button>
                            @endforeach
                        </div>
                    @endif
                </div>

                <x-ui.field label="What's being ordered" for="no-desc" error="productDescription">
                    <input id="no-desc" type="text" wire:model="productDescription" placeholder="e.g. 22K bridal necklace set, custom design" class="rj-input">
                </x-ui.field>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <x-ui.field label="Category" for="no-cat" optional>
                        <input id="no-cat" type="text" wire:model="category" placeholder="e.g. Necklace" class="rj-input">
                    </x-ui.field>
                    <x-ui.field label="Metal" for="no-metal" error="metal" :optional="! $fullPaymentNow">
                        <select id="no-metal" wire:model="metal" class="rj-select">
                            <option value="">Select metal</option>
                            <option value="gold">Gold</option>
                            <option value="silver">Silver</option>
                            <option value="titanium">Titanium</option>
                            <option value="platinum">Platinum</option>
                        </select>
                    </x-ui.field>
                    <x-ui.field label="Estimated weight" for="no-weight" error="estimatedWeight" optional>
                        <div class="rj-input-icon">
                            <x-ui.icon name="scale" :size="16" />
                            <input id="no-weight" type="number" step="0.001" min="0" wire:model="estimatedWeight" class="rj-input tabular">
                        </div>
                    </x-ui.field>
                </div>

                <div>
                    <label class="inline-flex items-center gap-2.5 text-[13px] font-semibold text-ink_text-primary cursor-pointer select-none">
                        <input type="checkbox" class="rj-checkbox" wire:model.live="inStock">
                        Product is currently in stock
                    </label>

                    @if ($inStock)
                        <div class="relative mt-3" x-data="{ open: true }" x-on:click.outside="open = false">
                            <div class="rj-input-icon">
                                <x-ui.icon name="search" :size="16" />
                                <x-ui.scan-button target="#order-item-search" title="Scan the piece" class="absolute right-1.5 top-1/2 -translate-y-1/2 !w-8 !h-8" />
                                <input id="order-item-search" type="text" wire:model.live.debounce.300ms="existingItemSearch" x-on:focus="open = true" x-on:input="open = true"
                                    autocomplete="off" placeholder="Search existing item by HUID or code..." class="rj-input pr-12">
                            </div>
                            @if ($existingItemId && ! $existingItemSearch)
                                <div class="mt-2 flex items-center gap-2.5 px-3.5 py-2.5 rounded-xl bg-gold-tint ring-1 ring-gold-soft">
                                    <x-ui.icon name="gem" :size="15" class="text-gold-dark" />
                                    <span class="text-[13px] font-semibold text-ink_text-primary">Item linked</span>
                                </div>
                            @endif
                            @if ($existingItemSearch && $itemResults->isNotEmpty())
                                <div x-show="open" class="absolute left-0 right-0 mt-2 bg-white border border-line-light rounded-xl shadow-pop p-1.5 z-dropdown">
                                    @foreach ($itemResults as $it)
                                        <button type="button" wire:click="$set('existingItemId', {{ $it->id }})"
                                            class="w-full flex items-center gap-3 px-2.5 py-2 rounded-lg hover:bg-surface-muted text-left {{ $existingItemId === $it->id ? 'bg-gold-tint' : '' }}">
                                            <span class="rj-code text-[12px]">{{ $it->huid_code ?? $it->internal_code }}</span>
                                            <span class="flex-1 text-[12.5px] text-ink_text-muted truncate">{{ $it->category }} · {{ number_format($it->weight, 3) }}g</span>
                                            @if ($existingItemId === $it->id) <x-ui.icon name="check" :size="14" class="text-gold-dark shrink-0" /> @endif
                                        </button>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @else
                        <p class="text-[12.5px] text-ink_text-secondary mt-2">Needs to be made — this becomes a Karigar Dispatch (Raw Material Issue) once confirmed.</p>
                    @endif
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <x-ui.field label="Estimated value" for="no-value" error="estimatedValue">
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-ink_text-muted text-[14px] font-semibold pointer-events-none">₹</span>
                            <input id="no-value" type="number" step="0.01" min="0" wire:model="estimatedValue" class="rj-input tabular pl-8">
                        </div>
                    </x-ui.field>
                    <x-ui.field label="Expected ready by" for="no-ready" error="expectedReadyDate" optional hint="Shown on the Status Board and used to flag overdue orders">
                        <input id="no-ready" type="date" wire:model="expectedReadyDate" class="rj-input">
                    </x-ui.field>
                </div>

                <div class="rounded-xl p-4 {{ $fullPaymentNow ? 'bg-success-bg ring-1 ring-inset ring-success/20' : 'bg-surface-sunken ring-1 ring-inset ring-line-light' }}">
                    <label class="flex items-center gap-2.5 text-[13px] font-bold text-ink_text-primary cursor-pointer select-none mb-1">
                        <input type="checkbox" class="rj-checkbox" wire:model.live="fullPaymentNow">
                        Customer is paying the full value now
                    </label>
                    @if ($fullPaymentNow)
                        <div class="flex items-center gap-1.5 text-[12.5px] font-bold text-success mt-2">
                            <x-ui.icon name="lock" :size="13" /> Rate will be LOCKED to today's rate for this order.
                        </div>
                    @else
                        <div class="mt-3">
                            <x-ui.field label="Advance / deposit amount" for="no-deposit" error="depositAmount">
                                <div class="relative">
                                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-ink_text-muted text-[14px] font-semibold pointer-events-none">₹</span>
                                    <input id="no-deposit" type="number" step="0.01" min="0" wire:model="depositAmount" class="rj-input tabular pl-8">
                                </div>
                            </x-ui.field>
                        </div>
                        <div class="flex items-center gap-1.5 text-[12.5px] font-bold text-warning mt-3">
                            <x-ui.icon name="clock" :size="13" /> Rate applies AT DELIVERY, not today.
                        </div>
                    @endif
                </div>

                <x-ui.button type="submit" target="submit" icon="plus" class="w-full">Create Order</x-ui.button>
            </form>
        </x-ui.card>

        <aside class="rounded-card bg-surface-sunken ring-1 ring-inset ring-line-light p-5">
            <div class="flex items-center gap-2 text-[12.5px] font-semibold text-ink_text-secondary mb-2">
                <x-ui.icon name="info" :size="14" /> The rate-lock rule
            </div>
            <p class="text-[12.5px] text-ink_text-secondary leading-relaxed">
                If the customer pays the full value today, the metal rate is frozen to today's rate for this order. If they only leave a deposit, the rate that applies is whatever it is on the day the order is actually delivered — not today's rate.
            </p>
        </aside>
    </div>
</div>
