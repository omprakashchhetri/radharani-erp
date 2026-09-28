<?php
namespace App\Livewire\Pricing;

use App\Models\Pricing\MakingChargePreset;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Making-Charge Configuration — category-level presets.
 *
 * Sets the default making_type/making_value staff see pre-filled when
 * adding an item of a given category under Stock > Add/Edit Item; the
 * per-item value can still be overridden there.
 */
class MakingChargeConfig extends Component
{
    public ?int $editingId = null;
    public string $selectedType = 'percentage'; // percentage | flat_per_piece | flat_per_gram
    public string $category = '';
    public float $value = 0;

    protected function rules(): array
    {
        return [
            'category' => ['required', 'string', 'max:50', Rule::unique('making_charge_presets', 'category')->ignore($this->editingId)],
            'selectedType' => 'required|in:percentage,flat_per_piece,flat_per_gram',
            'value' => 'required|numeric|min:0',
        ];
    }

    protected $validationAttributes = ['category' => 'category', 'selectedType' => 'charge type'];

    public function edit(int $id): void
    {
        $this->resetValidation();
        $preset = MakingChargePreset::findOrFail($id);
        $this->editingId = $preset->id;
        $this->category = $preset->category;
        $this->selectedType = $preset->type;
        $this->value = (float) $preset->value;
    }

    public function save(): void
    {
        $data = $this->validate();

        MakingChargePreset::updateOrCreate(['id' => $this->editingId], [
            'category' => $data['category'],
            'type' => $data['selectedType'],
            'value' => $data['value'],
            'created_by' => $this->editingId ? MakingChargePreset::find($this->editingId)->created_by : Auth::id(),
        ]);

        $this->cancel();
        $this->dispatch('toast', message: 'Making-charge preset saved.', type: 'success');
    }

    public function cancel(): void
    {
        $this->resetValidation();
        $this->reset(['editingId', 'category', 'value']);
        $this->selectedType = 'percentage';
    }

    public function delete(int $id): void
    {
        $preset = MakingChargePreset::findOrFail($id);
        $preset->delete();

        if ($this->editingId === $id) {
            $this->cancel();
        }

        $this->dispatch('toast', message: "Preset for \"{$preset->category}\" removed.", type: 'success');
    }

    public function render()
    {
        return view('livewire.pricing.making-charge-config', [
            'presets' => MakingChargePreset::orderBy('category')->get(),
        ])->layout('components.layouts.app', ['title' => 'Making-Charge Configuration — Radharani Jewellery']);
    }
}
