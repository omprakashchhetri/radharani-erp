<div>
    <x-ui.page-header title="Roles & Permissions" subtitle="Define what each role is allowed to do. New roles need no code changes.">
        <x-slot:actions>
            <x-ui.button icon="plus" wire:click="create">New role</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card :padding="false" class="overflow-hidden">
        <x-ui.table :headers="['Role', 'Permissions', 'Users', '']">
            @foreach ($roles as $role)
                <tr wire:key="role-{{ $role->id }}" class="h-[56px] border-b border-line-light">
                    <td class="px-4 font-semibold text-ink_text-primary">
                        {{ ucwords(str_replace('_', ' ', $role->name)) }}
                        @if ($role->name === 'owner')
                            <x-ui.badge tone="gold" size="sm" class="ml-1.5">Protected</x-ui.badge>
                        @endif
                    </td>
                    <td class="px-4 tabular text-ink_text-secondary">{{ $role->permissions_count }}</td>
                    <td class="px-4 tabular text-ink_text-secondary">{{ $role->users_count }}</td>
                    <td class="px-4">
                        <div class="flex items-center justify-end gap-1">
                            @if ($role->name !== 'owner')
                                <x-ui.button variant="ghost" size="icon-sm" icon="edit" wire:click="edit({{ $role->id }})" title="Edit" aria-label="Edit {{ $role->name }}" />
                                <x-ui.button variant="ghost" size="icon-sm" icon="x" title="Delete" aria-label="Delete {{ $role->name }}"
                                    x-on:click="$dispatch('rj-confirm', { title: 'Delete this role?', message: 'Roles with users assigned cannot be deleted — reassign them first. This cannot be undone.', confirm: 'Delete', tone: 'danger', action: () => $wire.delete({{ $role->id }}) })" />
                            @endif
                        </div>
                    </td>
                </tr>
            @endforeach
        </x-ui.table>
    </x-ui.card>

    <x-ui.modal wire:model="showForm" :title="$editingId ? 'Edit role' : 'New role'" icon="shield-check" max-width="xl" submit="save">
        <div class="space-y-4">
            <x-ui.field label="Role name" for="rl-name" error="name" hint="Lowercase with underscores, e.g. counter_staff.">
                <input id="rl-name" type="text" class="rj-input w-full max-w-[280px] @error('name') is-invalid @enderror" wire:model="name" placeholder="e.g. counter_staff">
            </x-ui.field>

            <div>
                <label class="rj-label">Permissions</label>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 mt-1.5">
                    @foreach ($allPermissions as $permission)
                        <label class="flex items-center gap-1.5 text-[12.5px] text-ink_text-primary cursor-pointer">
                            <input type="checkbox" wire:model="selectedPermissions" value="{{ $permission }}" class="rj-checkbox">
                            {{ $permission }}
                        </label>
                    @endforeach
                </div>
            </div>
        </div>
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="show = false">Cancel</x-ui.button>
            <x-ui.button type="submit" target="save" icon="check">{{ $editingId ? 'Save changes' : 'Create role' }}</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
