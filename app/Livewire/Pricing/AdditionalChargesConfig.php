<?php
namespace App\Livewire\Pricing;

use App\Models\Pricing\AdditionalChargePreset;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Additional Charges Config — named preset charges (Sakha, Pola, etc).
 *
 * These are reusable presets staff can pick from when building a sale's
 * additional_charges JSON; the sale itself still just stores name+value.
 */
class AdditionalChargesConfig extends Component
{
    public string $newName = '';
    public float $newValue = 0;

    public function updateValue(int $id, $value)
    {
        if (! is_numeric($value) || $value < 0) {
            return;
        }

        $preset = AdditionalChargePreset::findOrFail($id);
        $preset->update(['value' => (float) $value]);
        $this->dispatch('toast', message: "\"{$preset->name}\" updated.", type: 'success');
    }

    public function remove(int $id)
    {
        $preset = AdditionalChargePreset::findOrFail($id);
        $preset->delete();
        $this->dispatch('toast', message: "\"{$preset->name}\" removed.", type: 'success');
    }

    public function add()
    {
        $this->validate([
            'newName' => ['required', 'string', 'max:50', Rule::unique('additional_charge_presets', 'name')],
            'newValue' => 'required|numeric|min:0',
        ]);

        AdditionalChargePreset::create([
            'name' => $this->newName,
            'value' => $this->newValue,
            'created_by' => Auth::id(),
        ]);

        $this->reset(['newName', 'newValue']);
        $this->dispatch('toast', message: 'Charge added.', type: 'success');
    }

    public function render()
    {
        $charges = AdditionalChargePreset::orderBy('name')->get();

        return view('livewire.pricing.additional-charges-config', [
            'charges' => $charges,
            'stats' => [
                'count' => $charges->count(),
                'total' => $charges->sum('value'),
            ],
        ])->layout('components.layouts.app', ['title' => 'Additional Charges — Radharani Jewellery']);
    }
}
