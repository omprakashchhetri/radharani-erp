<?php
namespace App\Livewire\Admin;

use App\Livewire\Concerns\WithDataTable;
use App\Models\User;
use App\Models\Employee;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserManager extends Component
{
    use WithDataTable;

    public bool $showForm = false;
    public ?int $editingId = null;

    public string $name = '';
    public string $email = '';
    public string $password = '';
    public ?int $employee_id = null;
    public array $selectedRoles = [];
    public bool $is_active = true;

    protected function sortableColumns(): array
    {
        return [
            'name' => 'name',
            'email' => 'email',
            'created' => 'id',
        ];
    }

    protected function defaultSort(): array
    {
        return ['created', 'desc'];
    }

    protected function rules(): array
    {
        $emailRule = 'required|email|max:100|unique:users,email'.($this->editingId ? ','.$this->editingId : '');

        return [
            'name' => 'required|string|max:100',
            'email' => $emailRule,
            'password' => $this->editingId ? 'nullable|min:8' : 'required|min:8',
            'employee_id' => 'nullable|exists:employees,id',
            'selectedRoles' => 'required|array|min:1',
        ];
    }

    public function create(): void
    {
        $this->resetValidation();
        $this->cancel();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $this->resetValidation();
        $user = User::findOrFail($id);
        $this->editingId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->password = '';
        $this->employee_id = $user->employee_id;
        $this->is_active = $user->is_active;
        $this->selectedRoles = $user->roles->pluck('name')->toArray();
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->validate();

        // Self-lockout guard: if editing your own account, the resulting
        // role set must still grant user.manage — otherwise you could save
        // yourself out of this screen with no way back in except a direct
        // DB fix.
        if ($this->editingId && $this->editingId === Auth::id()) {
            $grantsUserManage = Role::whereIn('name', $this->selectedRoles)
                ->with('permissions')
                ->get()
                ->pluck('permissions')
                ->flatten()
                ->pluck('name')
                ->contains('user.manage');

            if (! $grantsUserManage) {
                $this->addError('selectedRoles', 'You cannot remove your own user.manage access — ask another admin to change your roles instead.');
                return;
            }
        }

        $data = [
            'name' => $this->name,
            'email' => $this->email,
            'employee_id' => $this->employee_id,
            'is_active' => $this->is_active,
        ];

        if ($this->password) {
            $data['password'] = Hash::make($this->password);
        }

        $user = User::updateOrCreate(['id' => $this->editingId], $data);
        $user->syncRoles($this->selectedRoles);

        $message = $this->editingId ? 'User updated.' : 'User added.';
        $this->showForm = false;
        $this->cancel();
        $this->dispatch('toast', message: $message, type: 'success');
    }

    public function cancel(): void
    {
        $this->reset(['editingId', 'name', 'email', 'password', 'employee_id', 'selectedRoles']);
        $this->is_active = true;
    }

    // Never delete a user — every movement/sale references user_id.
    // Deactivation blocks login without breaking audit history.
    public function toggleActive(int $id): void
    {
        $user = User::findOrFail($id);

        if ($user->is_active && $id === Auth::id()) {
            $this->dispatch('toast', message: 'You cannot disable your own account.', type: 'error');
            return;
        }

        $user->update(['is_active' => ! $user->is_active]);
        $this->dispatch('toast', message: $user->is_active ? "{$user->name} enabled." : "{$user->name} disabled.", type: 'success');
    }

    public function render()
    {
        $query = User::with('roles', 'employee')
            ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%")
                ->orWhere('email', 'like', "%{$this->search}%"));

        return view('livewire.admin.user-manager', [
            'users' => $this->applySorting($query)->paginate($this->perPageValue()),
            'roles' => Role::orderBy('name')->pluck('name'),
            'employees' => Employee::where('status', 'active')->orderBy('name')->get(),
        ])->layout('components.layouts.app', ['title' => 'Users — Radharani Jewellery']);
    }
}
