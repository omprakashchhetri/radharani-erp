<?php
namespace App\Livewire\Customers;

use App\Models\Customer\Customer;
use App\Rules\Gstin;
use App\Support\Phone;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The one "add a customer" form for the places that need a customer in the middle of something else
 * (a sale, an order, a scheme, an exchange). Opened with
 *   Livewire.dispatch('open-customer-form', { name: '...', phone: '...' })
 * and announces the customer it saved or found with the 'customer-saved' event ({ id }), which the page
 * that opened it listens for. The Administration page keeps its own full screen but shares the GSTIN rule.
 */
class CustomerForm extends Component
{
    public bool $show = false;

    public string $name = '';
    public string $phone = '';
    public string $gstin = '';
    public string $address = '';
    public string $email = '';
    public string $status = 'past_customer';

    // What was typed in the search box comes across: digits are a phone number, anything else a name.
    #[On('open-customer-form')]
    public function open(string $name = '', string $phone = '', string $status = 'past_customer'): void
    {
        $this->resetValidation();
        $this->reset(['name', 'phone', 'gstin', 'address', 'email']);

        $typed = trim($name ?: $phone);
        if ($typed !== '' && preg_match('/^[\d\s+()-]+$/', $typed)) {
            $this->phone = $typed;
        } elseif ($typed !== '') {
            $this->name = $typed;
        }
        $this->status = in_array($status, ['past_customer', 'order_given', 'order_pending'], true) ? $status : 'past_customer';
        $this->show = true;
    }

    public function save(): void
    {
        $this->phone = Phone::normalize($this->phone);
        $this->gstin = (string) Gstin::normalize($this->gstin);

        $this->validate([
            'name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'digits:10'],
            'gstin' => ['nullable', new Gstin],
            'address' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:100'],
        ], ['phone.digits' => 'Enter a 10 digit mobile number.', 'name.required' => 'Enter the customer\'s name.'], ['phone' => 'phone']);

        // A number that is already in the system is the same person: use them, do not add a second.
        if ($existing = Customer::where('phone', $this->phone)->first()) {
            $this->show = false;
            $this->dispatch('toast', message: "{$existing->name} already has this number, so I chose them.", type: 'info');
            $this->dispatch('customer-saved', id: $existing->id);

            return;
        }

        $customer = Customer::create([
            'name' => $this->name,
            'phone' => $this->phone,
            'gstin' => $this->gstin ?: null,
            'address' => $this->address ?: null,
            'email' => $this->email ?: null,
            'status' => $this->status,
        ]);

        $this->show = false;
        $this->dispatch('toast', message: "{$customer->name} added.", type: 'success');
        $this->dispatch('customer-saved', id: $customer->id);
    }

    public function render()
    {
        return view('livewire.customers.customer-form');
    }
}
