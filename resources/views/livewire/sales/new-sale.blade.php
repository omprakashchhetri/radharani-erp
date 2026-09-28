<div>
    <x-ui.page-header title="New Sale / Billing" subtitle="Submitting reserves this sale — it is not final until admin verifies it."
        :crumbs="[['label' => 'Sales & Billing', 'href' => route('sales.history')], ['label' => 'New Sale']]">
        <x-slot:actions>
            <x-ui.button variant="secondary" icon="clock" :href="route('sales.verification')">Verification queue</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid grid-cols-1 xl:grid-cols-[minmax(0,1fr)_360px] gap-6 items-start">
        <x-ui.card>
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
                        <span class="text-[13px] font-semibold text-ink_text-primary">{{ $this->customerObject?->name }} selected — {{ $this->customerObject?->loyalty_points ?? 0 }} loyalty pts</span>
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
                                    <span class="block text-[12px] text-ink_text-muted">{{ $c->phone }} · {{ $c->loyalty_points }} pts</span>
                                </span>
                                @if ($customerId === $c->id) <x-ui.icon name="check" :size="14" class="text-gold-dark shrink-0" /> @endif
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="relative mt-5" x-data="{ open: true }" x-on:click.outside="open = false">
                <x-ui.field label="Add item — scan or search" error="cart">
                    <div class="flex gap-2">
                        <div class="rj-input-icon flex-1 min-w-0">
                            <x-ui.icon name="scan" :size="16" />
                            <input id="sale-item-search" type="text" wire:model.live.debounce.300ms="itemSearch" x-on:focus="open = true" x-on:input="open = true"
                                x-on:keydown.enter.prevent="if ($event.target.value.trim()) $wire.addByCode($event.target.value)"
                                autocomplete="off" placeholder="HUID / code / category..." class="rj-input">
                        </div>
                        <x-ui.scan-button target="#sale-item-search" submit="enter" continuous title="Scan pieces onto the bill" variant="button" label="Scan" />
                    </div>
                </x-ui.field>

                @if ($itemSearch && $itemResults->isNotEmpty())
                    <div x-show="open" class="absolute left-0 right-0 mt-2 bg-white border border-line-light rounded-xl shadow-pop p-1.5 z-dropdown">
                        @foreach ($itemResults as $r)
                            <button type="button" wire:click="addItem({{ $r->id }})"
                                class="w-full flex items-center gap-3 px-2.5 py-2 rounded-lg hover:bg-surface-muted text-left">
                                <span class="w-8 h-8 rounded-full bg-surface-sunken ring-1 ring-inset ring-line-light flex items-center justify-center text-ink_text-muted shrink-0"><x-ui.icon name="gem" :size="14" /></span>
                                <span class="flex-1 min-w-0">
                                    <span class="block rj-code text-[12.5px] text-ink_text-primary truncate">{{ $r->huid_code ?: $r->internal_code }}</span>
                                    <span class="block text-[12px] text-ink_text-muted">{{ $r->category }} · {{ $r->weight }}g</span>
                                </span>
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="rounded-xl border border-line-light overflow-hidden mt-5">
                <x-ui.table :headers="['Item', 'Live price', 'GST', '']">
                    @forelse ($cart as $itemId => $line)
                    <tr wire:key="cart-{{ $itemId }}" class="h-[56px] border-b border-line-light">
                        <td class="px-4 font-semibold text-ink_text-primary">{{ $line['label'] }} <span class="text-ink_text-secondary font-normal">({{ $line['category'] }})</span></td>
                        <td class="px-4 tabular text-ink_text-primary">₹{{ number_format($line['price'], 2) }}</td>
                        <td class="px-4 tabular text-ink_text-secondary">{{ $line['gst_rate'] }}%</td>
                        <td class="px-4">
                            <div class="flex justify-end">
                                <x-ui.button variant="ghost" size="icon-sm" icon="x" wire:click="removeItem({{ $itemId }})" title="Remove" aria-label="Remove {{ $line['label'] }}" />
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4">
                            <x-ui.empty-state icon="gem" title="No items added yet" message="Prices are always computed live, never typed." compact />
                        </td>
                    </tr>
                    @endforelse
                </x-ui.table>
            </div>
        </x-ui.card>

        <aside class="space-y-6 xl:sticky xl:top-24">
            <x-ui.card title="Totals" icon="receipt">
                <dl class="rj-dl">
                    <div><dt>Subtotal</dt><dd class="tabular">₹{{ number_format($this->subtotal, 2) }}</dd></div>
                    <div><dt>GST (CGST+SGST)</dt><dd class="tabular">₹{{ number_format($this->gstTotal, 2) }}</dd></div>
                    <div><dt class="text-success">Loyalty discount</dt><dd class="tabular text-success">-₹{{ number_format($this->loyaltyDiscount, 2) }}</dd></div>
                    <div class="col-span-2 pt-2 mt-1 border-t border-line-light">
                        <dt class="font-bold text-ink_text-primary">Grand total</dt>
                        <dd class="font-display text-[22px] font-semibold tabular text-ink_text-primary">₹{{ number_format($this->grandTotal, 2) }}</dd>
                    </div>
                </dl>
            </x-ui.card>

            <x-ui.card title="Loyalty" icon="gift">
                <x-ui.field label="Apply points" for="ns-loyalty" :hint="'Customer balance: '.($this->customerObject?->loyalty_points ?? 0).' pts'">
                    <input id="ns-loyalty" type="number" wire:model.live="loyaltyPointsUsed" class="rj-input tabular">
                </x-ui.field>
            </x-ui.card>

            <x-ui.card title="Payment" subtitle="Modes can be combined." icon="credit-card">
                <div class="space-y-2 mb-3.5">
                    @foreach ($paymentModes as $i => $pm)
                    <div class="flex gap-2">
                        <select wire:model="paymentModes.{{ $i }}.mode" class="rj-select flex-1">
                            <option value="cash">Cash</option>
                            <option value="bank">Bank</option>
                            <option value="upi">UPI</option>
                            <option value="card">Card</option>
                        </select>
                        <input type="number" step="0.01" wire:model="paymentModes.{{ $i }}.amount" placeholder="₹" class="rj-input w-[110px] tabular">
                    </div>
                    @endforeach
                </div>
                <x-ui.button type="button" wire:click="addPaymentMode" variant="secondary" icon="plus" class="w-full mb-4">Add payment mode</x-ui.button>

                <x-ui.button wire:click="submit" target="submit" variant="primary" icon="check" class="w-full">Reserve Sale</x-ui.button>
            </x-ui.card>
        </aside>
    </div>
</div>
