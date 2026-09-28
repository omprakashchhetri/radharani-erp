<?php
namespace App\Livewire\Pricing;

use App\Models\Movement\RateLog;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class DailyRateEntry extends Component
{
    public const METALS = ['gold', 'silver', 'titanium', 'platinum'];

    public array $rates = ['gold' => 0, 'silver' => 0, 'titanium' => 0, 'platinum' => 0];

    public function mount()
    {
        foreach (self::METALS as $metal) {
            $this->rates[$metal] = RateLog::latestFor($metal)?->rate ?? 0;
        }
    }

    public function save()
    {
        $this->validate([
            'rates.gold' => 'required|numeric|min:0.01',
            'rates.silver' => 'required|numeric|min:0.01',
            'rates.titanium' => 'required|numeric|min:0.01',
            'rates.platinum' => 'required|numeric|min:0.01',
        ]);

        foreach (self::METALS as $metal) {
            RateLog::create([
                'metal' => $metal,
                'rate' => $this->rates[$metal],
                'source' => 'manual',
                'updated_by' => Auth::id(),
            ]);
        }

        $this->dispatch('toast', message: "Today's rates saved.", type: 'success');
    }

    public function render()
    {
        $last = [];
        foreach (self::METALS as $metal) {
            $last[$metal] = RateLog::latestFor($metal);
        }

        return view('livewire.pricing.daily-rate-entry', [
            'last' => $last,
        ])->layout('components.layouts.app', ['title' => 'Daily Rate Entry — Radharani Jewellery']);
    }
}
