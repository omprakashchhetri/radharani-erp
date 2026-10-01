<?php

namespace App\Livewire\Portal;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Component;

class ChangePassword extends Component
{
    public string $current_password = '';
    public string $password = '';
    public string $password_confirmation = '';

    public bool $saved = false;

    public function update(): void
    {
        $customer = Auth::guard('customer')->user();

        $this->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if (! Hash::check($this->current_password, $customer->password)) {
            $this->addError('current_password', 'That current password is not correct.');

            return;
        }

        $customer->forceFill([
            'password' => Hash::make($this->password),
        ])->save();

        $this->reset(['current_password', 'password', 'password_confirmation']);
        $this->saved = true;
    }

    public function render()
    {
        return view('livewire.portal.change-password')
            ->layout('components.layouts.portal', ['title' => 'Change password | Radharani Jewellery Works']);
    }
}
