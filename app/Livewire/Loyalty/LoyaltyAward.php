<?php
namespace App\Livewire\Loyalty;

use App\Models\Customer\Customer;
use App\Models\Customer\LoyaltyTransaction;
use App\Models\Notification\PendingNotification;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class LoyaltyAward extends Component
{
    public string $customerSearch = '';
    public ?int $customerId = null;
    public string $points = '';
    public string $reason = '';

    public function getCustomerObjectProperty()
    {
        return $this->customerId ? Customer::find($this->customerId) : null;
    }

    public function pickCustomer(int $id)
    {
        $this->customerId = $id;
        $this->customerSearch = '';
    }

    public function award()
    {
        $this->validate([
            'customerId' => 'required|exists:customers,id',
            'points' => 'required|integer|min:1',
            'reason' => 'required|string|max:100',
        ]);

        LoyaltyTransaction::create([
            'customer_id' => $this->customerId,
            'points' => (int) $this->points,
            'reason' => $this->reason,
        ]);

        Customer::whereKey($this->customerId)->increment('loyalty_points', (int) $this->points);

        $customer = Customer::find($this->customerId);

        PendingNotification::create([
            'customer_id' => $customer->id,
            'type' => 'loyalty_award',
            'recipient_name' => $customer->name,
            'recipient_phone' => $customer->phone,
            'message' => "You've been awarded {$this->points} loyalty points! Your new balance is {$customer->loyalty_points} points.",
            'status' => 'pending',
            'created_by' => Auth::id(),
        ]);

        $this->dispatch('toast', message: "Awarded {$this->points} points to {$customer->name}.", type: 'success');
        $this->reset(['customerId', 'points', 'reason']);
    }

    public function render()
    {
        return view('livewire.loyalty.loyalty-award', [
            'customerResults' => $this->customerSearch
                ? Customer::where('name', 'like', "%{$this->customerSearch}%")->orWhere('phone', 'like', "%{$this->customerSearch}%")->limit(8)->get()
                : collect(),
            'recentAwards' => LoyaltyTransaction::with('customer')->latest('id')->limit(5)->get(),
        ])->layout('components.layouts.app', ['title' => 'Award Loyalty Points — Radharani Jewellery ERP']);
    }
}
