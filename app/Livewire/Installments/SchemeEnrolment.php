<?php
namespace App\Livewire\Installments;

use App\Models\Customer\Customer;
use App\Models\Customer\InstallmentScheme;
use Livewire\Attributes\On;
use Livewire\Component;

class SchemeEnrolment extends Component
{
    public string $customerSearch = '';
    public ?int $customerId = null;
    public string $monthlyAmount = '';
    public $lateFee = '';
    public string $startDate = '';
    public $totalMonths = 12;
    public bool $existingMember = false;
    public $monthsAlreadyPaid = '';
    public $amountPending = '';

    public function mount()
    {
        $this->startDate = now()->toDateString();
    }

    public function getCustomerObjectProperty()
    {
        return $this->customerId ? Customer::find($this->customerId) : null;
    }

    public function pickCustomer(int $id)
    {
        $this->customerId = $id;
        $this->customerSearch = '';
    }

    // The shared customer form saved (or found) someone: they are the one being enrolled.
    #[On('customer-saved')]
    public function customerSaved(int $id): void
    {
        $this->pickCustomer($id);
    }

    public function enrol()
    {
        $this->validate([
            'customerId' => 'required|exists:customers,id',
            'monthlyAmount' => 'required|numeric|min:1',
            'lateFee' => 'nullable|numeric|min:0|max:99999999',
            'startDate' => 'required|date',
            'totalMonths' => 'required|integer|min:1|max:60',
            'monthsAlreadyPaid' => $this->existingMember ? 'required|integer|min:0|lte:totalMonths' : 'nullable',
            'amountPending' => $this->existingMember ? 'required|numeric|min:0' : 'nullable',
        ], ['monthsAlreadyPaid.lte' => 'That is more than the months in the scheme.'], ['monthsAlreadyPaid' => 'months already paid', 'amountPending' => 'amount pending', 'totalMonths' => 'months in the scheme', 'lateFee' => 'late fee']);

        $scheme = InstallmentScheme::create([
            'customer_id' => $this->customerId,
            'monthly_amount' => $this->monthlyAmount,
            'late_fee' => $this->lateFee !== '' ? $this->lateFee : 0,
            'total_months' => (int) $this->totalMonths,
            'months_paid' => $this->existingMember ? (int) $this->monthsAlreadyPaid : 0,
            'opening_pending_amount' => $this->existingMember ? $this->amountPending : (int) $this->totalMonths * (float) $this->monthlyAmount,
            'start_date' => $this->startDate,
            'status' => 'active',
        ]);

        \App\Support\MessageTemplates::queue('scheme_welcome', $scheme->customer, \App\Support\MessageTemplates::schemeWelcome($scheme), 'installment_scheme', $scheme->id);

        $this->dispatch('toast', message: "{$scheme->customer->name} enrolled in the installment scheme.", type: 'success');
        $this->reset(['customerId', 'monthlyAmount', 'lateFee', 'existingMember', 'monthsAlreadyPaid', 'amountPending']);
        $this->totalMonths = 12;
        $this->startDate = now()->toDateString();
    }

    public function render()
    {
        return view('livewire.installments.scheme-enrolment', [
            'customerResults' => $this->customerSearch
                ? Customer::where('name', 'like', "%{$this->customerSearch}%")->orWhere('phone', 'like', "%{$this->customerSearch}%")->limit(8)->get()
                : collect(),
            'recentEnrolments' => InstallmentScheme::with('customer')->latest('id')->limit(5)->get(),
        ])->layout('components.layouts.app', ['title' => 'Scheme Enrolment — Radharani Jewellery ERP']);
    }
}
