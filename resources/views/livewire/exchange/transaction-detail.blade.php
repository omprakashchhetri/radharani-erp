<div>
    @php
        $t = $transaction;
        $stageTone = ['received' => 'info', 'melted' => 'warning', 'tested' => 'gold', 'valued' => 'gold', 'settled' => 'success'][$t->stage] ?? 'neutral';
        $stageLabel = \App\Livewire\Exchange\StatusTracker::STAGES[$t->stage] ?? ucfirst($t->stage);
        $reachedMelted = in_array($t->stage, ['melted', 'tested', 'valued', 'settled'], true);
        $reachedTested = in_array($t->stage, ['tested', 'valued', 'settled'], true);
        $reachedDeduction = $t->deductable_weight !== null;
        $reachedSettled = $t->stage === 'settled';
    @endphp

    <x-ui.page-header title="Exchange #{{ $t->id }}" :subtitle="($t->description ?: 'No description') . ' · ' . ($t->customer->name ?? 'Unknown customer')"
        :crumbs="[['label' => 'Exchange & Refinery'], ['label' => 'Status Tracker', 'href' => route('exchange.tracker')], ['label' => '#' . $t->id]]">
        <x-slot:meta>
            <x-ui.badge :tone="$stageTone" size="lg" dot>{{ $stageLabel }}</x-ui.badge>
        </x-slot:meta>
        @if ($t->stage === 'tested')
            <x-slot:actions>
                <x-ui.button icon="coins" :href="route('exchange.valuation')">Go to Final Valuation</x-ui.button>
            </x-slot:actions>
        @endif
    </x-ui.page-header>

    @if ($t->stage === 'settled')
        <x-ui.card title="What next?" :subtitle="$credit > 0 ? '₹' . number_format($credit, 2) . ' of this exchange is still credit for ' . ($t->customer->name ?? 'the customer') : 'All of this exchange has been used'" icon="coins" class="mb-6">
            @if ($credit > 0)
                <div class="flex flex-wrap gap-3">
                    @can('sale.create')<x-ui.button icon="receipt" :href="route('sales.new', ['exchange' => $t->id])">Use in a sale</x-ui.button>@endcan
                    @can('orders.manage')<x-ui.button variant="secondary" icon="clipboard" :href="route('orders.new', ['exchange' => $t->id])">Use for a new order</x-ui.button>@endcan
                    @can('exchange.manage')<x-ui.button variant="secondary" icon="coins" wire:click="openCash">Paid out in cash</x-ui.button>@endcan
                </div>
                <p class="rj-help mt-3">A sale or an order takes what it needs and the rest stays as credit. The old metal from this exchange is already in Raw Material.</p>
            @endif
            @if ($applications->isNotEmpty())
                <div class="{{ $credit > 0 ? 'mt-5 pt-4 border-t border-line-light' : '' }}">
                    <div class="text-[12.5px] font-semibold text-ink_text-secondary mb-2">Where the value went</div>
                    @foreach ($applications as $a)
                        <div class="flex items-center justify-between gap-3 py-1.5 text-[13px]" wire:key="app-{{ $a->id }}">
                            <span>
                                @if ($a->kind === 'sale' && $a->applied_id)<a href="{{ route('sales.invoice', $a->applied_id) }}" class="font-semibold text-gold-dark hover:underline">Sale #{{ $a->applied_id }}</a>
                                @elseif ($a->kind === 'order' && $a->applied_id)<a href="{{ route('orders.show', $a->applied_id) }}" class="font-semibold text-gold-dark hover:underline">Order #{{ $a->applied_id }}</a>
                                @else<span class="font-semibold">{{ \App\Models\Exchange\ExchangeApplication::KINDS[$a->kind] }}</span>@endif
                                <span class="text-ink_text-muted"> · {{ $a->created_at->format('j M, g:i a') }} · {{ $a->user?->name }}{{ $a->amount < 0 ? ' · credit returned' : '' }}</span>
                            </span>
                            <span class="tabular font-semibold {{ $a->amount < 0 ? 'text-success' : '' }}">{{ $a->amount < 0 ? '+' : '-' }}₹{{ number_format(abs($a->amount), 2) }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-ui.card>

        @can('exchange.manage')
            <x-ui.modal wire:model="showCash" title="Paid out in cash" icon="coins" max-width="sm" submit="payCash" subtitle="Records that the customer took this part of the value in cash. It leaves the credit.">
                <x-ui.field label="Amount (₹)" for="ex-cash" error="cashAmount"><input id="ex-cash" type="number" step="0.01" min="0" wire:model="cashAmount" class="rj-input tabular @error('cashAmount') is-invalid @enderror" autofocus></x-ui.field>
                <x-ui.field label="Note" for="ex-cash-note" optional class="mt-4"><input id="ex-cash-note" type="text" wire:model="cashNote" maxlength="255" class="rj-input"></x-ui.field>
                <x-slot:footer>
                    <x-ui.button variant="secondary" x-on:click="show = false">Cancel</x-ui.button>
                    <x-ui.button type="submit" target="payCash" icon="check">Record</x-ui.button>
                </x-slot:footer>
            </x-ui.modal>
        @endcan
    @endif

    {{-- Key facts --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 bg-white border border-line-light rounded-card shadow-card mb-6 divide-y lg:divide-y-0 divide-line-light lg:divide-x overflow-hidden">
        <div class="p-5">
            <div class="text-[12px] font-semibold text-ink_text-muted">Gross weight</div>
            <div class="font-display text-[30px] leading-tight font-semibold tabular mt-1">{{ number_format($t->gross_weight, 3) }}<span class="text-[16px] text-ink_text-muted ml-1">g</span></div>
        </div>
        <div class="p-5 border-l border-line-light lg:border-l-0">
            <div class="text-[12px] font-semibold text-ink_text-muted">Net after melt</div>
            <div class="font-display text-[30px] leading-tight font-semibold tabular mt-1">{{ $t->net_weight !== null ? number_format($t->net_weight, 3) : '—' }}<span class="text-[16px] text-ink_text-muted ml-1">{{ $t->net_weight !== null ? 'g' : '' }}</span></div>
        </div>
        <div class="p-5">
            <div class="text-[12px] font-semibold text-ink_text-muted">Average purity</div>
            <div class="font-display text-[30px] leading-tight font-semibold tabular mt-1">{{ $t->purity_averaged !== null ? number_format($t->purity_averaged, 2) . '%' : '—' }}</div>
        </div>
        <div class="p-5 border-l border-line-light lg:border-l-0 bg-gradient-to-br from-gold-tint to-white">
            <div class="text-[12px] font-semibold text-gold-dark">{{ $t->stage === 'settled' ? 'Settled for' : 'Net payable weight' }}</div>
            <div class="font-display text-[30px] leading-tight font-semibold tabular mt-1 text-ink_text-primary">
                @if ($t->stage === 'settled' && $t->final_value !== null)
                    ₹{{ number_format($t->final_value, 2) }}
                @elseif ($t->deductable_weight !== null)
                    {{ number_format($t->deductable_weight, 3) }}<span class="text-[16px] text-ink_text-muted ml-1">g</span>
                @else
                    —
                @endif
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-[minmax(0,1fr)_360px] gap-6 items-start">
        <div class="space-y-4 min-w-0">
            <x-ui.card title="The process, step by step" icon="list" subtitle="Every figure entered exactly as measured — nothing here is calculated after the fact.">
                <div class="space-y-4">
                    {{-- Step 1 --}}
                    <div class="flex gap-4 p-4 rounded-xl bg-surface-sunken ring-1 ring-inset ring-line-light">
                        <span class="w-9 h-9 shrink-0 rounded-xl bg-success-bg text-success flex items-center justify-center"><x-ui.icon name="inbox" :size="16" /></span>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between gap-3">
                                <span class="text-[13.5px] font-bold text-ink_text-primary">1 · Received</span>
                                <span class="text-[12px] text-ink_text-muted tabular">{{ $t->created_at->format('d M Y, g:i a') }}</span>
                            </div>
                            <div class="text-[13px] text-ink_text-secondary mt-1">
                                Gross weight <span class="font-semibold text-ink_text-primary tabular">{{ number_format($t->gross_weight, 3) }} g</span>
                                @if ($t->description) · {{ $t->description }} @endif
                            </div>
                        </div>
                    </div>

                    {{-- Step 2 --}}
                    <div class="flex gap-4 p-4 rounded-xl {{ $reachedMelted ? 'bg-surface-sunken ring-1 ring-inset ring-line-light' : 'bg-surface-muted/60' }}">
                        <span class="w-9 h-9 shrink-0 rounded-xl flex items-center justify-center {{ $reachedMelted ? 'bg-success-bg text-success' : 'bg-white text-ink_text-muted ring-1 ring-inset ring-line-light' }}"><x-ui.icon name="flame" :size="16" /></span>
                        <div class="flex-1 min-w-0">
                            <span class="text-[13.5px] font-bold {{ $reachedMelted ? 'text-ink_text-primary' : 'text-ink_text-muted' }}">2 · Melted</span>
                            <div class="text-[13px] {{ $reachedMelted ? 'text-ink_text-secondary' : 'text-ink_text-muted' }} mt-1">
                                @if ($reachedMelted)
                                    Net weight after melting <span class="font-semibold text-ink_text-primary tabular">{{ number_format($t->net_weight, 3) }} g</span>
                                @else
                                    Not reached yet
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- Step 3 --}}
                    <div class="flex gap-4 p-4 rounded-xl {{ $reachedTested ? 'bg-surface-sunken ring-1 ring-inset ring-line-light' : 'bg-surface-muted/60' }}">
                        <span class="w-9 h-9 shrink-0 rounded-xl flex items-center justify-center {{ $reachedTested ? 'bg-success-bg text-success' : 'bg-white text-ink_text-muted ring-1 ring-inset ring-line-light' }}"><x-ui.icon name="shield-check" :size="16" /></span>
                        <div class="flex-1 min-w-0">
                            <span class="text-[13.5px] font-bold {{ $reachedTested ? 'text-ink_text-primary' : 'text-ink_text-muted' }}">3 · Tested</span>
                            <div class="text-[13px] {{ $reachedTested ? 'text-ink_text-secondary' : 'text-ink_text-muted' }} mt-1">
                                @if ($reachedTested)
                                    Test 1 <span class="font-semibold text-ink_text-primary tabular">{{ number_format($t->purity_test_1, 2) }}%</span>
                                    · Test 2 <span class="font-semibold text-ink_text-primary tabular">{{ number_format($t->purity_test_2, 2) }}%</span>
                                    · Average <span class="font-semibold text-ink_text-primary tabular">{{ number_format($t->purity_averaged, 2) }}%</span>
                                @else
                                    Not reached yet
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- Step 4 --}}
                    <div class="flex gap-4 p-4 rounded-xl {{ $reachedDeduction ? 'bg-surface-sunken ring-1 ring-inset ring-line-light' : 'bg-surface-muted/60' }}">
                        <span class="w-9 h-9 shrink-0 rounded-xl flex items-center justify-center {{ $reachedDeduction ? 'bg-success-bg text-success' : 'bg-white text-ink_text-muted ring-1 ring-inset ring-line-light' }}"><x-ui.icon name="percent" :size="16" /></span>
                        <div class="flex-1 min-w-0">
                            <span class="text-[13.5px] font-bold {{ $reachedDeduction ? 'text-ink_text-primary' : 'text-ink_text-muted' }}">4 · Deduction</span>
                            <div class="text-[13px] {{ $reachedDeduction ? 'text-ink_text-secondary' : 'text-ink_text-muted' }} mt-1">
                                @if ($reachedDeduction)
                                    Preset <span class="font-semibold text-ink_text-primary tabular">{{ number_format($t->preset_deduction_percent, 2) }}%</span>
                                    · Net payable <span class="font-semibold text-ink_text-primary tabular">{{ number_format($t->deductable_weight, 3) }} g</span>
                                @else
                                    Not reached yet
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- Step 5 --}}
                    <div class="flex gap-4 p-4 rounded-xl {{ $reachedSettled ? 'bg-gold-tint ring-1 ring-inset ring-gold-soft' : 'bg-surface-muted/60' }}">
                        <span class="w-9 h-9 shrink-0 rounded-xl flex items-center justify-center {{ $reachedSettled ? 'bg-white text-gold-dark ring-1 ring-inset ring-gold-soft' : 'bg-white text-ink_text-muted ring-1 ring-inset ring-line-light' }}"><x-ui.icon name="receipt" :size="16" /></span>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between gap-3">
                                <span class="text-[13.5px] font-bold {{ $reachedSettled ? 'text-ink_text-primary' : 'text-ink_text-muted' }}">5 · Settled</span>
                                @if ($t->settled_at)
                                    <span class="text-[12px] text-ink_text-muted tabular">{{ $t->settled_at->format('d M Y, g:i a') }}</span>
                                @endif
                            </div>
                            <div class="text-[13px] {{ $reachedSettled ? 'text-ink_text-secondary' : 'text-ink_text-muted' }} mt-1">
                                @if ($reachedSettled)
                                    Final value <span class="font-semibold text-ink_text-primary tabular">₹{{ number_format($t->final_value, 2) }}</span>
                                    @if ($t->settler) · recorded by {{ $t->settler->name }} @endif
                                @else
                                    Not settled yet
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </x-ui.card>
        </div>

        <aside class="space-y-6 xl:sticky xl:top-24">
            <x-ui.card title="Customer" icon="user">
                @if ($t->customer)
                    <dl class="rj-dl">
                        <div class="col-span-2"><dt>Name</dt><dd>{{ $t->customer->name }}</dd></div>
                        <div class="col-span-2"><dt>Phone</dt><dd>{{ $t->customer->phone }}</dd></div>
                    </dl>
                    @can('customer.manage')
                        @if (\Illuminate\Support\Facades\Route::has('admin.customers.detail'))
                            <x-ui.button variant="secondary" size="sm" iconRight="arrow-right" class="w-full mt-4" :href="route('admin.customers.detail', $t->customer)">View customer</x-ui.button>
                        @endif
                    @endcan
                @else
                    <p class="text-[13px] text-ink_text-muted">Customer record no longer available.</p>
                @endif
            </x-ui.card>

            <x-ui.card title="Details" icon="file-text">
                <dl class="rj-dl">
                    <div><dt>Created</dt><dd>{{ $t->created_at->format('d M Y') }}</dd></div>
                    <div><dt>By</dt><dd>{{ $t->creator->name ?? '—' }}</dd></div>
                    <div class="col-span-2"><dt>Last updated</dt><dd>{{ $t->updated_at->diffForHumans() }}</dd></div>
                </dl>
            </x-ui.card>
        </aside>
    </div>
</div>
