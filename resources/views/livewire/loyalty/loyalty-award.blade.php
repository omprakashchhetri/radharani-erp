<div>
    <x-ui.page-header title="Award Loyalty Points" subtitle="Manual award — e.g. a goodwill gesture or a referral bonus not tied to a sale.">
        <x-slot:actions>
            <x-ui.button variant="secondary" icon="history" :href="route('loyalty.ledger')">Ledger</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid grid-cols-1 xl:grid-cols-[minmax(0,1fr)_360px] gap-6 items-start">
        <x-ui.card>
            <div class="relative mb-4" x-data="{ open: true }" x-on:click.outside="open = false">
                <x-ui.field label="Customer" error="customerId">
                    @if ($this->customerObject)
                        <div class="rj-input w-full flex justify-between items-center">
                            <span class="text-ink_text-primary">{{ $this->customerObject->name }} ({{ $this->customerObject->phone }}) · {{ $this->customerObject->loyalty_points }} pts</span>
                            <button type="button" wire:click="$set('customerId', null)" class="text-gold font-semibold text-xs">Change</button>
                        </div>
                    @else
                        <div class="rj-input-icon">
                            <x-ui.icon name="search" :size="16" />
                            <input type="text" wire:model.live.debounce.300ms="customerSearch" x-on:focus="open = true" x-on:input="open = true"
                                autocomplete="off" placeholder="Search by name or phone..." class="rj-input">
                        </div>
                    @endif
                </x-ui.field>

                @if ($customerSearch && ! $this->customerObject)
                    <div x-show="open" class="absolute left-0 right-0 mt-2 bg-white border border-line-light rounded-xl shadow-pop p-1.5 z-dropdown">
                        @forelse ($customerResults as $c)
                            <button type="button" wire:click="pickCustomer({{ $c->id }})"
                                class="w-full flex items-center gap-3 px-2.5 py-2 rounded-lg hover:bg-surface-muted text-left">
                                <span class="w-8 h-8 rounded-full bg-surface-sunken ring-1 ring-inset ring-line-light flex items-center justify-center text-ink_text-muted shrink-0"><x-ui.icon name="user" :size="14" /></span>
                                <span class="flex-1 min-w-0">
                                    <span class="block text-[13px] font-semibold text-ink_text-primary truncate">{{ $c->name }}</span>
                                    <span class="block text-[12px] text-ink_text-muted">{{ $c->phone }} · {{ $c->loyalty_points }} pts</span>
                                </span>
                            </button>
                        @empty
                            <div class="px-2.5 py-2 text-[12.5px] text-ink_text-secondary">No matches.</div>
                        @endforelse
                    </div>
                @endif
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-[1fr_2fr] gap-4 mb-5">
                <x-ui.field label="Points" for="la-points" error="points">
                    <div class="rj-input-icon">
                        <x-ui.icon name="gift" :size="16" />
                        <input id="la-points" type="number" wire:model="points" class="rj-input tabular">
                    </div>
                </x-ui.field>
                <x-ui.field label="Reason" for="la-reason" error="reason">
                    <input id="la-reason" type="text" wire:model="reason" placeholder="e.g. goodwill, referral bonus" class="rj-input w-full">
                </x-ui.field>
            </div>

            <x-ui.button type="button" wire:click="award" target="award" variant="primary" icon="gift">Award points</x-ui.button>
        </x-ui.card>

        <aside class="space-y-6 xl:sticky xl:top-24">
            <x-ui.card title="Recent awards" icon="clock">
                @if ($recentAwards->isEmpty())
                    <p class="text-[12.5px] text-ink_text-secondary">Awards you record will show up here.</p>
                @else
                    <dl class="rj-dl">
                        @foreach ($recentAwards as $t)
                            <div>
                                <dt>{{ $t->customer->name ?? '—' }}</dt>
                                <dd class="tabular {{ $t->points >= 0 ? 'text-success' : 'text-danger' }}">{{ $t->points >= 0 ? '+' : '' }}{{ $t->points }}</dd>
                            </div>
                        @endforeach
                    </dl>
                @endif
            </x-ui.card>

            <div class="rounded-card bg-surface-sunken ring-1 ring-inset ring-line-light p-5">
                <div class="flex items-center gap-2 text-[12.5px] font-semibold text-ink_text-secondary mb-2">
                    <x-ui.icon name="info" :size="14" /> Fully manual
                </div>
                <p class="text-[12.5px] text-ink_text-secondary leading-relaxed">
                    Loyalty is a ledger, not an auto-calculated running total — every award or redemption is a deliberate entry here or at billing, so any dispute is answerable from history.
                </p>
            </div>
        </aside>
    </div>
</div>
