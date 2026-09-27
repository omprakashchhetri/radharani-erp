<div>
    <x-ui.page-header title="Final Valuation" subtitle="Exchanges that have finished testing, ready for a final rupee value and settlement."
        :crumbs="[['label' => 'Exchange & Refinery'], ['label' => 'Final Valuation']]" />

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <x-ui.stat-card icon="clock" label="Ready to settle" :value="number_format($readyCount)" hint="in the tested stage" />
    </div>

    <x-ui.datatable :paginator="$readyTransactions">
        <x-slot:toolbar>
            <x-ui.search-input wire:model.live.debounce.300ms="search" placeholder="Customer name or phone" class="w-full sm:w-[260px]" />
        </x-slot:toolbar>

        <x-slot:head>
            <x-ui.th field="id" :sort-field="$this->currentSortField()" :sort-direction="$this->currentSortDirection()">Exchange</x-ui.th>
            <th>Customer</th>
            <x-ui.th field="purity" :sort-field="$this->currentSortField()" :sort-direction="$this->currentSortDirection()" align="right">Avg. purity</x-ui.th>
            <x-ui.th field="weight" :sort-field="$this->currentSortField()" :sort-direction="$this->currentSortDirection()" align="right">Net payable</x-ui.th>
            <x-ui.th field="updated" :sort-field="$this->currentSortField()" :sort-direction="$this->currentSortDirection()">Tested</x-ui.th>
            <x-ui.th align="right"><span class="sr-only">Settle</span></x-ui.th>
        </x-slot:head>

        @forelse ($readyTransactions as $t)
            <tr wire:key="rt-{{ $t->id }}">
                <td><a href="{{ route('exchange.transactions.show', $t) }}" class="rj-code text-ink_text-primary hover:text-gold-dark">#{{ $t->id }}</a></td>
                <td>
                    <div class="font-semibold text-ink_text-primary">{{ $t->customer->name ?? '—' }}</div>
                    <div class="text-[12px] text-ink_text-muted">{{ $t->customer->phone ?? '' }}</div>
                </td>
                <td class="text-right tabular">{{ number_format($t->purity_averaged, 2) }}%</td>
                <td class="text-right tabular font-semibold">{{ number_format($t->deductable_weight, 3) }} <span class="text-ink_text-muted font-normal">g</span></td>
                <td class="text-[12.5px] text-ink_text-secondary whitespace-nowrap">{{ $t->updated_at->diffForHumans() }}</td>
                <td class="text-right">
                    <x-ui.button size="sm" icon="coins" wire:click="openSettle({{ $t->id }})">Settle</x-ui.button>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="6">
                    <x-ui.empty-state icon="check-circle" title="Nothing waiting for valuation" message="Exchanges appear here once purity testing is complete." />
                </td>
            </tr>
        @endforelse
    </x-ui.datatable>

    <x-ui.modal wire:model="showSettle" title="Settle exchange" icon="coins" max-width="sm" submit="settle"
        subtitle="Recorded against the transaction permanently — this cannot be changed afterwards.">
        @php $selected = $readyTransactions->firstWhere('id', $settleTransactionId); @endphp
        @if ($selected)
            <div class="rounded-xl bg-surface-sunken ring-1 ring-inset ring-line-light p-4 mb-4">
                <dl class="rj-dl">
                    <div><dt>Exchange</dt><dd class="rj-code">#{{ $selected->id }}</dd></div>
                    <div><dt>Customer</dt><dd>{{ $selected->customer->name ?? '—' }}</dd></div>
                    <div><dt>Avg. purity</dt><dd class="tabular">{{ number_format($selected->purity_averaged, 2) }}%</dd></div>
                    <div><dt>Net payable</dt><dd class="tabular">{{ number_format($selected->deductable_weight, 3) }} g</dd></div>
                </dl>
            </div>
        @endif
        <x-ui.field label="Final value" for="av-final" error="finalValue" hint="The rupee amount to be paid or credited to the customer">
            <div class="relative">
                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-ink_text-muted text-[14px] font-semibold pointer-events-none">₹</span>
                <input id="av-final" type="number" step="0.01" min="0" wire:model="finalValue" autofocus class="rj-input tabular pl-8">
            </div>
        </x-ui.field>
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="show = false">Cancel</x-ui.button>
            <x-ui.button type="submit" target="settle" icon="check">Settle exchange</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
