<div>
    <x-ui.page-header title="Ledger / Transactions" subtitle="Auto-generated from sales, purchases, and installment payments."
        :crumbs="[['label' => 'Accounting', 'href' => route('accounting.ledger')], ['label' => 'Ledger']]">
        <x-slot:actions>
            <x-ui.button variant="secondary" icon="book" :href="route('accounting.accounts')">Accounts</x-ui.button>
            @can('ledger.manage')
                <x-ui.button icon="plus" wire:click="create">Manual entry</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
        <x-ui.stat-card icon="receipt" label="Transactions" :value="number_format($stats['count'])" />
        <x-ui.stat-card icon="arrow-up" label="Total debits" :value="'₹'.number_format($stats['debits'], 2)" />
        <x-ui.stat-card icon="arrow-down" label="Total credits" :value="'₹'.number_format($stats['credits'], 2)" />
        <x-ui.stat-card icon="edit" label="Manual entries" :value="number_format($stats['manual'])" />
    </div>

    <x-ui.datatable :paginator="$transactions">
        <x-slot:toolbar>
            <select wire:model.live="accountFilter" class="rj-select rj-input-sm">
                <option value="">All accounts</option>
                @foreach ($accounts as $a)
                    <option value="{{ $a->id }}">{{ $a->name }} ({{ ucfirst($a->type) }})</option>
                @endforeach
            </select>
            <select wire:model.live="typeFilter" class="rj-select rj-input-sm">
                <option value="">All reference types</option>
                <option value="sale">Sale</option>
                <option value="purchase">Purchase</option>
                <option value="installment">Installment</option>
                <option value="manual">Manual</option>
            </select>
            @if ($this->hasActiveFilters())
                <x-ui.button variant="ghost" size="sm" icon="x" wire:click="resetFilters">Clear</x-ui.button>
            @endif
        </x-slot:toolbar>

        <x-slot:head>
            <x-ui.th field="created" :sort-field="$this->currentSortField()" :sort-direction="$this->currentSortDirection()">Date</x-ui.th>
            <x-ui.th>Account</x-ui.th>
            <x-ui.th>Reference</x-ui.th>
            <x-ui.th field="debit" :sort-field="$this->currentSortField()" :sort-direction="$this->currentSortDirection()" align="right">Debit</x-ui.th>
            <x-ui.th field="credit" :sort-field="$this->currentSortField()" :sort-direction="$this->currentSortDirection()" align="right">Credit</x-ui.th>
            <x-ui.th>By</x-ui.th>
        </x-slot:head>

        @forelse ($transactions as $t)
            <tr wire:key="txn-{{ $t->id }}">
                <td class="text-[12.5px] text-ink_text-secondary whitespace-nowrap">{{ $t->created_at->format('d M Y, g:i a') }}</td>
                <td class="text-ink_text-primary font-semibold">{{ $t->account->name ?? '—' }}</td>
                <td>
                    <x-ui.badge tone="neutral" size="sm">{{ ucfirst($t->reference_type) }}</x-ui.badge>
                    @if ($t->reference_id)
                        <span class="text-ink_text-muted text-[11.5px]">#{{ $t->reference_id }}</span>
                    @elseif ($t->note)
                        <span class="text-ink_text-muted text-[11.5px]">{{ $t->note }}</span>
                    @endif
                </td>
                <td class="text-right tabular text-ink_text-primary">{{ $t->debit > 0 ? '₹'.number_format($t->debit, 2) : '—' }}</td>
                <td class="text-right tabular text-ink_text-primary">{{ $t->credit > 0 ? '₹'.number_format($t->credit, 2) : '—' }}</td>
                <td class="text-ink_text-secondary">{{ $t->creator->name ?? '—' }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="6">
                    @if ($this->hasActiveFilters())
                        <x-ui.empty-state icon="search" title="No transactions match these filters" message="Try a different account, type, or clear the filters.">
                            <x-ui.button variant="secondary" size="sm" wire:click="resetFilters">Clear filters</x-ui.button>
                        </x-ui.empty-state>
                    @else
                        <x-ui.empty-state icon="receipt" title="No transactions recorded yet" message="Sales, purchases and installment payments will post here automatically once wired; a manual entry can also be added." />
                    @endif
                </td>
            </tr>
        @endforelse
    </x-ui.datatable>

    @can('ledger.manage')
        <x-ui.modal wire:model="showForm" title="Manual entry" subtitle="Creates a single debit or credit row against an account." icon="edit" max-width="sm" submit="save">
            <div class="space-y-4">
                <x-ui.field label="Account" for="le-account" error="accountId">
                    <select id="le-account" class="rj-select w-full @error('accountId') is-invalid @enderror" wire:model="accountId">
                        <option value="">Select account…</option>
                        @foreach ($accounts as $a)
                            <option value="{{ $a->id }}">{{ $a->name }} ({{ ucfirst($a->type) }})</option>
                        @endforeach
                    </select>
                </x-ui.field>
                <div>
                    <label class="rj-label">Direction</label>
                    <div class="rj-segment w-full">
                        <button type="button" wire:click="$set('direction', 'debit')" class="{{ $direction === 'debit' ? 'is-active' : '' }}">Debit</button>
                        <button type="button" wire:click="$set('direction', 'credit')" class="{{ $direction === 'credit' ? 'is-active' : '' }}">Credit</button>
                    </div>
                </div>
                <x-ui.field label="Amount (₹)" for="le-amount" error="amount">
                    <div class="rj-input-icon">
                        <x-ui.icon name="coins" :size="16" />
                        <input id="le-amount" type="number" step="0.01" class="rj-input tabular @error('amount') is-invalid @enderror" wire:model="amount">
                    </div>
                </x-ui.field>
                <x-ui.field label="Note" for="le-note" optional error="note">
                    <input id="le-note" type="text" class="rj-input w-full" wire:model="note" placeholder="What this entry is for">
                </x-ui.field>
            </div>
            <x-slot:footer>
                <x-ui.button variant="secondary" x-on:click="show = false">Cancel</x-ui.button>
                <x-ui.button type="submit" target="save" icon="check">Record entry</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
    @endcan
</div>
