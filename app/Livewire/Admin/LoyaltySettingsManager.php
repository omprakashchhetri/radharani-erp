<?php
namespace App\Livewire\Admin;

use App\Models\Customer\LoyaltySetting;
use Livewire\Component;
use Illuminate\Support\Facades\Auth;

class LoyaltySettingsManager extends Component
{
    public float $points_per_rupee;
    public int $referral_bonus_points;
    public int $min_redeemable_points;
    public float $point_value_in_rupees;

    public function mount()
    {
        $s = LoyaltySetting::current();
        $this->points_per_rupee = $s->points_per_rupee;
        $this->referral_bonus_points = $s->referral_bonus_points;
        $this->min_redeemable_points = $s->min_redeemable_points;
        $this->point_value_in_rupees = $s->point_value_in_rupees;
    }

    protected $rules = [
        'points_per_rupee' => 'required|numeric|min:0',
        'referral_bonus_points' => 'required|integer|min:0',
        'min_redeemable_points' => 'required|integer|min:0',
        'point_value_in_rupees' => 'required|numeric|min:0',
    ];

    public function save()
    {
        $this->validate();

        $s = LoyaltySetting::current();
        $s->update([
            'points_per_rupee' => $this->points_per_rupee,
            'referral_bonus_points' => $this->referral_bonus_points,
            'min_redeemable_points' => $this->min_redeemable_points,
            'point_value_in_rupees' => $this->point_value_in_rupees,
            'updated_by' => Auth::id(),
        ]);

        $this->dispatch('toast', message: 'Loyalty settings updated.', type: 'success');
    }

    public function render()
    {
        return view('livewire.admin.loyalty-settings-manager')->layout('components.layouts.app', ['title' => 'Loyalty Settings — Radharani Jewellery']);
    }
}
