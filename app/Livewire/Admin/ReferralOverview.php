<?php
namespace App\Livewire\Admin;

use App\Models\Customer\Customer;
use Livewire\Component;
use Livewire\WithPagination;

// Read-only report for the owner: who referred whom, and whether the
// bonus has actually been earned (first sale confirmed) yet.
class ReferralOverview extends Component
{
    use WithPagination;

    public string $search = '';

    public function updatingSearch() { $this->resetPage(); }

    public function render()
    {
        $referrers = Customer::withCount('referrals')
            ->having('referrals_count', '>', 0)
            ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->with(['referrals' => fn ($q) => $q->withCount([
                'sales as confirmed_sales_count' => fn ($s) => $s->where('confirmed_by_accountant', true),
            ])])
            ->orderByDesc('referrals_count')
            ->paginate(15);

        $totalReferrals = Customer::whereNotNull('referred_by')->count();
        $bonusEarned = Customer::whereNotNull('referred_by')
            ->whereHas('sales', fn ($q) => $q->where('confirmed_by_accountant', true))
            ->count();

        return view('livewire.admin.referral-overview', [
            'referrers' => $referrers,
            'stats' => [
                'referrers' => Customer::has('referrals')->count(),
                'totalReferrals' => $totalReferrals,
                'bonusEarned' => $bonusEarned,
                'pending' => $totalReferrals - $bonusEarned,
            ],
        ])->layout('components.layouts.app', ['title' => 'Referrals — Radharani Jewellery']);
    }
}
