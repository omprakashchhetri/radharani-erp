<?php
namespace App\Livewire\Accounting;

use App\Models\Accounting\Account;
use Illuminate\Validation\Rule;
use Livewire\Component;

class AccountsList extends Component
{
    public bool $showForm = false;
    public ?int $editingId = null;
    public string $name = '';
    public string $type = 'asset';

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:50', Rule::unique('accounts', 'name')->ignore($this->editingId)],
            'type' => 'required|in:asset,liability,income,expense',
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
        $a = Account::findOrFail($id);
        $this->editingId = $a->id;
        $this->name = $a->name;
        $this->type = $a->type;
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->validate();

        Account::updateOrCreate(['id' => $this->editingId], [
            'name' => $this->name,
            'type' => $this->type,
        ]);

        $message = $this->editingId ? 'Account updated.' : 'Account added.';
        $this->showForm = false;
        $this->cancel();
        $this->dispatch('toast', message: $message, type: 'success');
    }

    public function cancel(): void
    {
        $this->reset(['editingId', 'name']);
        $this->type = 'asset';
    }

    public function render()
    {
        return view('livewire.accounting.accounts-list', [
            'accounts' => Account::withCount('transactions')->orderBy('type')->orderBy('name')->get(),
        ])->layout('components.layouts.app', ['title' => 'Accounts — Radharani Jewellery ERP']);
    }
}
