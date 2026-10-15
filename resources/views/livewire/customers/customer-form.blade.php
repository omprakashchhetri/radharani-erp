<div>
    <x-ui.modal wire:model="show" title="New customer" icon="user" max-width="md" submit="save"
        subtitle="Name and mobile number are enough. The rest can be added later.">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <x-ui.field label="Name" for="cf-name" error="name" class="sm:col-span-2">
                <input id="cf-name" type="text" wire:model="name" maxlength="100" class="rj-input @error('name') is-invalid @enderror" autofocus autocomplete="off">
            </x-ui.field>
            <x-ui.field label="Mobile number" for="cf-phone" error="phone">
                <input id="cf-phone" type="text" wire:model="phone" inputmode="numeric" class="rj-input tabular @error('phone') is-invalid @enderror" autocomplete="off">
            </x-ui.field>
            <x-ui.field label="GSTIN" for="cf-gstin" error="gstin" optional hint="For a business bill. Leave empty for a person.">
                <input id="cf-gstin" type="text" wire:model="gstin" maxlength="15" class="rj-input rj-code uppercase @error('gstin') is-invalid @enderror" autocomplete="off">
            </x-ui.field>
            <x-ui.field label="Address" for="cf-address" error="address" optional class="sm:col-span-2">
                <input id="cf-address" type="text" wire:model="address" maxlength="255" class="rj-input">
            </x-ui.field>
            <x-ui.field label="Email" for="cf-email" error="email" optional class="sm:col-span-2">
                <input id="cf-email" type="email" wire:model="email" maxlength="100" class="rj-input @error('email') is-invalid @enderror">
            </x-ui.field>
        </div>
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="show = false">Cancel</x-ui.button>
            <x-ui.button type="submit" target="save" icon="check">Add customer</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
