<div>
    <x-ui.page-header title="Customers" subtitle="Directory, plus portal password assignment — customers cannot self-register.">
        <x-slot:actions>
            <x-ui.button variant="secondary" icon="upload" :href="route('admin.customers.import')">Bulk import</x-ui.button>
            <x-ui.button icon="plus" wire:click="create">New customer</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.datatable :paginator="$customers">
        <x-slot:toolbar>
            <x-ui.search-input wire:model.live.debounce.300ms="search" placeholder="Search customers" class="w-full sm:w-[280px]" />
            @if ($this->hasActiveFilters())
                <x-ui.button variant="ghost" size="sm" icon="x" wire:click="resetFilters">Clear</x-ui.button>
            @endif
        </x-slot:toolbar>

        <x-slot:head>
            <x-ui.th field="name" :sort-field="$this->currentSortField()" :sort-direction="$this->currentSortDirection()">Name</x-ui.th>
            <x-ui.th>Phone</x-ui.th>
            <x-ui.th>Referral code</x-ui.th>
            <x-ui.th>Portal access</x-ui.th>
            <x-ui.th>Status</x-ui.th>
            <x-ui.th align="right"><span class="sr-only">Actions</span></x-ui.th>
        </x-slot:head>

        @foreach ($customers as $customer)
            <tr wire:key="customer-{{ $customer->id }}">
                <td class="text-ink_text-primary font-semibold">{{ $customer->name }}</td>
                <td class="text-ink_text-primary">{{ $customer->phone }}</td>
                <td class="rj-code text-[12px] text-ink_text-secondary">{{ $customer->referral_code }}</td>
                <td>
                    @if ($customer->password)
                        <x-ui.badge tone="success" size="sm" dot>Enabled</x-ui.badge>
                    @else
                        <x-ui.badge size="sm">Not set</x-ui.badge>
                    @endif
                </td>
                <td><x-ui.badge tone="success" size="sm">{{ strtoupper(str_replace('_', ' ', $customer->status)) }}</x-ui.badge></td>
                <td>
                    <div class="flex items-center justify-end gap-1">
                        <x-ui.button variant="ghost" size="icon-sm" icon="external-link" :href="route('admin.customers.detail', $customer)" title="View detail" aria-label="View {{ $customer->name }}" />
                        <x-ui.button variant="ghost" size="icon-sm" icon="edit" wire:click="edit({{ $customer->id }})" title="Edit" aria-label="Edit {{ $customer->name }}" />
                        <x-ui.button variant="ghost" size="icon-sm" icon="lock" wire:click="openPasswordForm({{ $customer->id }})" title="{{ $customer->password ? 'Reset' : 'Set' }} portal password" aria-label="{{ $customer->password ? 'Reset' : 'Set' }} password for {{ $customer->name }}" />
                    </div>
                </td>
            </tr>
        @endforeach
    </x-ui.datatable>

    <x-ui.modal wire:model="showForm" :title="$editingId ? 'Edit customer' : 'New customer'" icon="user" max-width="lg" submit="save">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <x-ui.field label="Name" for="cu-name" error="name">
                <input id="cu-name" type="text" class="rj-input w-full @error('name') is-invalid @enderror" wire:model="name">
            </x-ui.field>
            <x-ui.field label="Phone" for="cu-phone" error="phone">
                <input id="cu-phone" type="text" class="rj-input w-full @error('phone') is-invalid @enderror" wire:model="phone">
            </x-ui.field>
            <x-ui.field label="Email" for="cu-email" optional error="email">
                <input id="cu-email" type="email" class="rj-input w-full @error('email') is-invalid @enderror" wire:model="email">
            </x-ui.field>
            <x-ui.field label="Address" for="cu-address" optional error="address" class="sm:col-span-2">
                <input id="cu-address" type="text" class="rj-input w-full @error('address') is-invalid @enderror" wire:model="address">
            </x-ui.field>
            <x-ui.field label="GSTIN" for="cu-gstin" optional error="gstin">
                <input id="cu-gstin" type="text" class="rj-input w-full @error('gstin') is-invalid @enderror" wire:model="gstin">
            </x-ui.field>
            <x-ui.field label="Status" for="cu-status" class="sm:col-span-full">
                <select id="cu-status" class="rj-select w-full" wire:model="status">
                    <option value="past_customer">Past Customer</option>
                    <option value="order_given">Order Given</option>
                    <option value="order_pending">Order Pending</option>
                </select>
            </x-ui.field>
        </div>
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="show = false">Cancel</x-ui.button>
            <x-ui.button type="submit" target="save" icon="check">{{ $editingId ? 'Save changes' : 'Add customer' }}</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>

    <x-ui.modal wire:model="showPasswordFor" title="Set portal password" subtitle="Share this password with the customer directly — there is no reset-by-email flow yet." icon="lock" max-width="sm" submit="setPassword">
        <x-ui.field label="New password" for="cu-password" error="newPassword" hint="Minimum 8 characters.">
            <input id="cu-password" type="text" class="rj-input w-full @error('newPassword') is-invalid @enderror" wire:model="newPassword" placeholder="New password">
        </x-ui.field>
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="show = false">Cancel</x-ui.button>
            <x-ui.button type="submit" target="setPassword" icon="check">Set password</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
