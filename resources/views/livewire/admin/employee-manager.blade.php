<div>
    <x-ui.page-header title="Employees" subtitle="HR records — independent of system login.">
        <x-slot:actions>
            <x-ui.button icon="plus" wire:click="create">New employee</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.datatable :paginator="$employees">
        <x-slot:toolbar>
            <x-ui.search-input wire:model.live.debounce.300ms="search" placeholder="Search name or designation" class="w-full sm:w-[280px]" />
            @if ($this->hasActiveFilters())
                <x-ui.button variant="ghost" size="sm" icon="x" wire:click="resetFilters">Clear</x-ui.button>
            @endif
        </x-slot:toolbar>

        <x-slot:head>
            <x-ui.th field="name" :sort-field="$this->currentSortField()" :sort-direction="$this->currentSortDirection()">Name</x-ui.th>
            <x-ui.th field="designation" :sort-field="$this->currentSortField()" :sort-direction="$this->currentSortDirection()">Designation</x-ui.th>
            <x-ui.th>Phone</x-ui.th>
            <x-ui.th>Has login</x-ui.th>
            <x-ui.th>Status</x-ui.th>
            <x-ui.th align="right"><span class="sr-only">Actions</span></x-ui.th>
        </x-slot:head>

        @foreach ($employees as $employee)
            <tr wire:key="employee-{{ $employee->id }}">
                <td class="text-ink_text-primary font-semibold">{{ $employee->name }}</td>
                <td class="text-ink_text-primary">{{ $employee->designation }}</td>
                <td class="text-ink_text-secondary">{{ $employee->phone }}</td>
                <td>
                    @if ($employee->user_count > 0)
                        <x-ui.badge tone="success" size="sm" dot>Yes</x-ui.badge>
                    @else
                        <x-ui.badge size="sm">No</x-ui.badge>
                    @endif
                </td>
                <td>
                    <x-ui.badge :tone="$employee->status === 'active' ? 'success' : 'neutral'" size="sm">
                        {{ strtoupper($employee->status) }}
                    </x-ui.badge>
                </td>
                <td>
                    <div class="flex items-center justify-end gap-1">
                        <x-ui.button variant="ghost" size="icon-sm" icon="edit" wire:click="edit({{ $employee->id }})" title="Edit" aria-label="Edit {{ $employee->name }}" />
                        @if ($employee->status === 'active')
                            <x-ui.button variant="ghost" size="icon-sm" icon="x" title="Deactivate" aria-label="Deactivate {{ $employee->name }}"
                                x-on:click="$dispatch('rj-confirm', { title: 'Mark inactive?', message: 'The employee record for {{ $employee->name }} will be marked inactive. HR history is kept, not deleted.', confirm: 'Mark inactive', tone: 'danger', action: () => $wire.deactivate({{ $employee->id }}) })" />
                        @endif
                    </div>
                </td>
            </tr>
        @endforeach
    </x-ui.datatable>

    <x-ui.modal wire:model="showForm" :title="$editingId ? 'Edit employee' : 'New employee'" icon="user" max-width="lg" submit="save">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <x-ui.field label="Name" for="em-name" error="name">
                <input id="em-name" type="text" class="rj-input w-full @error('name') is-invalid @enderror" wire:model="name">
            </x-ui.field>
            <x-ui.field label="Phone" for="em-phone" optional error="phone">
                <input id="em-phone" type="text" class="rj-input w-full" wire:model="phone">
            </x-ui.field>
            <x-ui.field label="Designation" for="em-designation" optional error="designation">
                <input id="em-designation" type="text" class="rj-input w-full" wire:model="designation">
            </x-ui.field>
            <x-ui.field label="Address" for="em-address" optional error="address" class="sm:col-span-2">
                <input id="em-address" type="text" class="rj-input w-full" wire:model="address">
            </x-ui.field>
            <x-ui.field label="Status" for="em-status">
                <select id="em-status" class="rj-select w-full" wire:model="status">
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </x-ui.field>
            <x-ui.field label="Salary (₹)" for="em-salary" optional error="salary">
                <div class="rj-input-icon">
                    <x-ui.icon name="coins" :size="16" />
                    <input id="em-salary" type="number" step="0.01" class="rj-input tabular" wire:model="salary">
                </div>
            </x-ui.field>
            <x-ui.field label="Joining date" for="em-joined" optional error="joining_date">
                <input id="em-joined" type="date" class="rj-input w-full" wire:model="joining_date">
            </x-ui.field>
        </div>
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="show = false">Cancel</x-ui.button>
            <x-ui.button type="submit" target="save" icon="check">{{ $editingId ? 'Save changes' : 'Add employee' }}</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
