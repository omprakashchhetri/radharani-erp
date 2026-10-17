<?php
namespace App\Livewire\Admin;

use App\Models\Customer\Customer;
use App\Models\Orders\Order;
use App\Models\Customer\CustomerAdvance;
use App\Services\AdvanceService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Customer Detail (staff-facing) — combined view: purchase history,
 * current custom orders, exchange balance, instalment
 * scheme status.
 */
class CustomerDetail extends Component
{
    public Customer $customer;
    public string $tab = 'purchases';

    // Receive an advance: money paid in with no order behind it (for example the Poila Boishakh halkhata).
    public bool $showAdvance = false;
    public $advanceAmount = '';
    public string $advanceMode = 'cash';
    public string $advanceOccasion = '';
    public string $advanceNote = '';

    public function mount(Customer $customer)
    {
        $this->customer = $customer;
    }

    public function setTab(string $tab)
    {
        $this->tab = $tab;
    }

    public function openAdvance(): void
    {
        abort_unless(Auth::user()?->can('customer.manage'), 403);
        $this->resetValidation();
        $this->reset(['advanceAmount', 'advanceMode', 'advanceOccasion', 'advanceNote']);
        $this->showAdvance = true;
    }

    public function receiveAdvance(): void
    {
        abort_unless(Auth::user()?->can('customer.manage'), 403);
        $this->validate([
            'advanceAmount' => 'required|numeric|min:1|max:99999999',
            'advanceMode' => 'required|in:' . implode(',', array_keys(CustomerAdvance::MODES)),
            'advanceOccasion' => 'nullable|string|max:80',
            'advanceNote' => 'nullable|string|max:255',
        ], [], ['advanceAmount' => 'amount']);

        app(AdvanceService::class)->receive($this->customer->id, (float) $this->advanceAmount, $this->advanceMode, trim($this->advanceOccasion) ?: null, trim($this->advanceNote) ?: null);
        $this->showAdvance = false;
        $this->dispatch('toast', message: 'Advance of ₹' . number_format((float) $this->advanceAmount, 2) . ' recorded.', type: 'success');
    }

    public function render()
    {
        return view('livewire.admin.customer-detail', [
            'sales' => $this->customer->sales()->with('items')->withSum('payments', 'amount')->orderByDesc('id')->get(),
            'ledger' => $this->tab === 'ledger' ? app(\App\Services\LedgerService::class)->forCustomer($this->customer) : null,
            'advanceBalance' => app(AdvanceService::class)->balance($this->customer->id),
            'orders' => Order::where('customer_id', $this->customer->id)->orderByDesc('id')->get(),
            'installmentSchemes' => $this->customer->installmentSchemes()->with('payments')->get(),
        ])->layout('components.layouts.app', ['title' => $this->customer->name.' — Radharani Jewellery']);
    }
}
