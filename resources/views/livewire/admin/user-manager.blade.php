<div>
    <x-ui.page-header title="Users & Logins" subtitle="System access — every action is attributed to one of these accounts.">
        <x-slot:actions>
            <x-ui.button icon="plus" wire:click="create">New user</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.datatable :paginator="$users">
        <x-slot:toolbar>
            <x-ui.search-input wire:model.live.debounce.300ms="search" placeholder="Search name or email" class="w-full sm:w-[280px]" />
            @if ($this->hasActiveFilters())
                <x-ui.button variant="ghost" size="sm" icon="x" wire:click="resetFilters">Clear</x-ui.button>
            @endif
        </x-slot:toolbar>

        <x-slot:head>
            <x-ui.th field="name" :sort-field="$this->currentSortField()" :sort-direction="$this->currentSortDirection()">Name</x-ui.th>
            <x-ui.th field="email" :sort-field="$this->currentSortField()" :sort-direction="$this->currentSortDirection()">Email</x-ui.th>
            <x-ui.th>Roles</x-ui.th>
            <x-ui.th>Employee</x-ui.th>
            <x-ui.th>Status</x-ui.th>
            <x-ui.th align="right"><span class="sr-only">Actions</span></x-ui.th>
        </x-slot:head>

        @foreach ($users as $user)
            <tr wire:key="user-{{ $user->id }}">
                <td class="text-ink_text-primary font-semibold">
                    {{ $user->name }}
                    @if ($user->id === auth()->id())
                        <span class="text-ink_text-muted font-normal">(you)</span>
                    @endif
                </td>
                <td class="text-ink_text-secondary">{{ $user->email }}</td>
                <td class="text-ink_text-primary">{{ $user->roles->pluck('name')->map(fn($r) => ucwords(str_replace('_', ' ', $r)))->join(', ') }}</td>
                <td class="text-ink_text-secondary">{{ $user->employee?->name ?? '—' }}</td>
                <td>
                    <x-ui.badge :tone="$user->is_active ? 'success' : 'neutral'" size="sm" dot>
                        {{ $user->is_active ? 'Active' : 'Disabled' }}
                    </x-ui.badge>
                </td>
                <td>
                    <div class="flex items-center justify-end gap-1">
                        <x-ui.button variant="ghost" size="icon-sm" icon="edit" wire:click="edit({{ $user->id }})" title="Edit" aria-label="Edit {{ $user->name }}" />
                        @if ($user->id === auth()->id() && $user->is_active)
                            <x-ui.button variant="ghost" size="icon-sm" icon="lock" disabled title="You cannot disable your own account" aria-label="Cannot disable your own account" />
                        @else
                            <x-ui.button variant="ghost" size="icon-sm" :icon="$user->is_active ? 'lock' : 'log-in'" :title="$user->is_active ? 'Disable login' : 'Enable login'" :aria-label="($user->is_active ? 'Disable' : 'Enable').' '.$user->name"
                                x-on:click="$dispatch('rj-confirm', { title: '{{ $user->is_active ? 'Disable' : 'Enable' }} this login?', message: '{{ $user->is_active ? 'They will no longer be able to sign in until re-enabled.' : 'They will be able to sign in again.' }}', confirm: '{{ $user->is_active ? 'Disable' : 'Enable' }}', tone: '{{ $user->is_active ? 'danger' : '' }}', action: () => $wire.toggleActive({{ $user->id }}) })" />
                        @endif
                    </div>
                </td>
            </tr>
        @endforeach
    </x-ui.datatable>

    <x-ui.modal wire:model="showForm" :title="$editingId ? 'Edit user' : 'New user'" icon="user" max-width="lg" submit="save">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <x-ui.field label="Name" for="us-name" error="name">
                <input id="us-name" type="text" class="rj-input w-full @error('name') is-invalid @enderror" wire:model="name">
            </x-ui.field>
            <x-ui.field label="Email" for="us-email" error="email">
                <input id="us-email" type="email" class="rj-input w-full @error('email') is-invalid @enderror" wire:model="email">
            </x-ui.field>
            <x-ui.field :label="$editingId ? 'Password (leave blank to keep)' : 'Password'" for="us-password" error="password">
                <input id="us-password" type="password" class="rj-input w-full @error('password') is-invalid @enderror" wire:model="password">
            </x-ui.field>
            <x-ui.field label="Link to employee" for="us-employee" optional>
                <select id="us-employee" class="rj-select w-full" wire:model="employee_id">
                    <option value="">— none —</option>
                    @foreach ($employees as $employee)
                        <option value="{{ $employee->id }}">{{ $employee->name }}</option>
                    @endforeach
                </select>
            </x-ui.field>
            <x-ui.field label="Roles" error="selectedRoles" class="sm:col-span-full">
                <div class="flex flex-wrap gap-2">
                    @foreach ($roles as $roleName)
                        <label class="flex items-center gap-1.5 text-[12.5px] bg-surface-muted border border-line rounded-full px-3 py-1.5 cursor-pointer">
                            <input type="checkbox" wire:model="selectedRoles" value="{{ $roleName }}" class="rj-checkbox">
                            {{ ucwords(str_replace('_', ' ', $roleName)) }}
                        </label>
                    @endforeach
                </div>
            </x-ui.field>
        </div>
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="show = false">Cancel</x-ui.button>
            <x-ui.button type="submit" target="save" icon="check">{{ $editingId ? 'Save changes' : 'Add user' }}</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
