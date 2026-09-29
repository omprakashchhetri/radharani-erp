<div>
    <x-ui.page-header title="Accounts" subtitle="Basic chart of accounts — asset, liability, income, expense."
        :crumbs="[['label' => 'Accounting', 'href' => route('accounting.ledger')], ['label' => 'Accounts']]">
        <x-slot:actions>
            <x-ui.button variant="secondary" icon="receipt" :href="route('accounting.ledger')">Ledger</x-ui.button>
            <x-ui.button icon="plus" wire:click="create">New account</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card :padding="false" class="overflow-hidden">
        <x-ui.table :headers="['Name', 'Type', 'Transactions', '']">
            @forelse ($accounts as $a)
                <tr wire:key="account-{{ $a->id }}" class="h-[56px] border-b border-line-light">
                    <td class="px-4 font-semibold text-ink_text-primary">{{ $a->name }}</td>
                    <td class="px-4"><x-ui.badge tone="neutral">{{ ucfirst($a->type) }}</x-ui.badge></td>
                    <td class="px-4 tabular text-ink_text-secondary">{{ $a->transactions_count }}</td>
                    <td class="px-4">
                        <div class="flex justify-end">
                            <x-ui.button variant="ghost" size="icon-sm" icon="edit" wire:click="edit({{ $a->id }})" title="Edit" aria-label="Edit {{ $a->name }}" />
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="4">
                        <x-ui.empty-state icon="book" title="No accounts yet" message="Add the first account to start building the chart of accounts.">
                            <x-ui.button size="sm" icon="plus" wire:click="create">New account</x-ui.button>
                        </x-ui.empty-state>
                    </td>
                </tr>
            @endforelse
        </x-ui.table>
    </x-ui.card>

    <x-ui.modal wire:model="showForm" :title="$editingId ? 'Edit account' : 'New account'" icon="book" max-width="sm" submit="save">
        <div class="space-y-4">
            <x-ui.field label="Name" for="acc-name" error="name">
                <input id="acc-name" type="text" class="rj-input w-full @error('name') is-invalid @enderror" wire:model="name" autofocus>
            </x-ui.field>
            <x-ui.field label="Type" for="acc-type">
                <select id="acc-type" class="rj-select w-full" wire:model="type">
                    <option value="asset">Asset</option>
                    <option value="liability">Liability</option>
                    <option value="income">Income</option>
                    <option value="expense">Expense</option>
                </select>
            </x-ui.field>
        </div>
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="show = false">Cancel</x-ui.button>
            <x-ui.button type="submit" target="save" icon="check">{{ $editingId ? 'Save changes' : 'Add account' }}</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
