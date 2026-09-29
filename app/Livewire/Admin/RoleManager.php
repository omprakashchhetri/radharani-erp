<?php
namespace App\Livewire\Admin;

use Livewire\Component;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RoleManager extends Component
{
    public bool $showForm = false;
    public ?int $editingId = null;
    public string $name = '';
    public array $selectedPermissions = [];

    protected function rules(): array
    {
        return [
            'name' => 'required|string|max:50|unique:roles,name'.($this->editingId ? ','.$this->editingId : ''),
            'selectedPermissions' => 'array',
        ];
    }

    public function create(): void
    {
        $this->resetValidation();
        $this->reset(['editingId', 'name', 'selectedPermissions']);
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $this->resetValidation();
        $role = Role::with('permissions')->findOrFail($id);
        $this->editingId = $role->id;
        $this->name = $role->name;
        $this->selectedPermissions = $role->permissions->pluck('name')->toArray();
        $this->showForm = true;
    }

    public function save(): void
    {
        // The owner role's permission set is not editable from this screen
        // — it is the top role every other permission check is measured
        // against, and letting it be edited here risks an accidental (or
        // malicious) de-privileging of the account that manages everyone
        // else's access, with no built-in way back.
        if ($this->editingId) {
            $existing = Role::findOrFail($this->editingId);
            if ($existing->name === 'owner') {
                $this->addError('name', 'The owner role cannot be edited from this screen.');
                return;
            }
        }

        $this->validate();

        $role = Role::updateOrCreate(['id' => $this->editingId], ['name' => $this->name]);
        $role->syncPermissions($this->selectedPermissions);

        $message = $this->editingId ? 'Role updated.' : 'Role created.';
        $this->showForm = false;
        $this->reset(['editingId', 'name', 'selectedPermissions']);
        $this->dispatch('toast', message: $message, type: 'success');
    }

    public function cancel(): void
    {
        $this->showForm = false;
        $this->reset(['editingId', 'name', 'selectedPermissions']);
    }

    // Roles with users attached should not be deletable from here without
    // reassignment first — enforce that check before allowing delete.
    public function delete(int $id): void
    {
        $role = Role::findOrFail($id);

        if ($role->name === 'owner') {
            $this->dispatch('toast', message: 'The owner role cannot be deleted.', type: 'error');
            return;
        }

        if ($role->users()->count() > 0) {
            $this->dispatch('toast', message: 'Cannot delete a role with users assigned. Reassign them first.', type: 'error');
            return;
        }

        $role->delete();
        $this->dispatch('toast', message: 'Role deleted.', type: 'success');
    }

    public function render()
    {
        return view('livewire.admin.role-manager', [
            'roles' => Role::withCount('users', 'permissions')->orderBy('name')->get(),
            'allPermissions' => Permission::orderBy('name')->pluck('name'),
        ])->layout('components.layouts.app', ['title' => 'Roles & Permissions — Radharani Jewellery']);
    }
}
