<?php
namespace App\Livewire\Admin;

use App\Livewire\Concerns\WithDataTable;
use App\Models\Employee;
use Livewire\Component;

class EmployeeManager extends Component
{
    use WithDataTable;

    public bool $showForm = false;
    public ?int $editingId = null;

    public string $name = '';
    public string $phone = '';
    public string $address = '';
    public string $designation = '';
    public ?float $salary = null;
    public string $joining_date = '';
    public string $status = 'active';

    protected function sortableColumns(): array
    {
        return [
            'name' => 'name',
            'designation' => 'designation',
            'created' => 'id',
        ];
    }

    protected function defaultSort(): array
    {
        return ['created', 'desc'];
    }

    protected $rules = [
        'name' => 'required|string|max:100',
        'phone' => 'nullable|string|max:15',
        'address' => 'nullable|string|max:255',
        'designation' => 'nullable|string|max:50',
        'salary' => 'nullable|numeric|min:0',
        'joining_date' => 'nullable|date',
        'status' => 'required|in:active,inactive',
    ];

    public function create(): void
    {
        $this->resetValidation();
        $this->cancel();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $this->resetValidation();
        $e = Employee::findOrFail($id);
        $this->editingId = $e->id;
        $this->name = $e->name;
        $this->phone = (string) $e->phone;
        $this->address = (string) $e->address;
        $this->designation = (string) $e->designation;
        $this->salary = $e->salary;
        $this->joining_date = optional($e->joining_date)->format('Y-m-d') ?? '';
        $this->status = $e->status;
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->validate();

        Employee::updateOrCreate(['id' => $this->editingId], [
            'name' => $this->name,
            'phone' => $this->phone,
            'address' => $this->address,
            'designation' => $this->designation,
            'salary' => $this->salary,
            'joining_date' => $this->joining_date ?: null,
            'status' => $this->status,
        ]);

        $message = $this->editingId ? 'Employee updated.' : 'Employee added.';
        $this->showForm = false;
        $this->cancel();
        $this->dispatch('toast', message: $message, type: 'success');
    }

    public function cancel(): void
    {
        $this->reset(['editingId', 'name', 'phone', 'address', 'designation', 'salary', 'joining_date']);
        $this->status = 'active';
    }

    // Employees are never deleted — only marked inactive. Salary/HR history
    // and any linked user account must stay attributable.
    public function deactivate(int $id): void
    {
        $employee = Employee::findOrFail($id);
        $employee->update(['status' => 'inactive']);
        $this->dispatch('toast', message: "{$employee->name} marked inactive.", type: 'success');
    }

    public function render()
    {
        $query = Employee::withCount('user')
            ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%")
                ->orWhere('designation', 'like', "%{$this->search}%"));

        return view('livewire.admin.employee-manager', [
            'employees' => $this->applySorting($query)->paginate($this->perPageValue()),
        ])->layout('components.layouts.app', ['title' => 'Employees — Radharani Jewellery']);
    }
}
