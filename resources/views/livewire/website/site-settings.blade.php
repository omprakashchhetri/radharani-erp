<div>
    <x-ui.page-header title="Website settings" subtitle="Shop details the public website shows in its header, footer, visit section and WhatsApp buttons."
        :crumbs="[['label' => 'Website'], ['label' => 'Settings']]">
        <x-slot:actions>
            <x-ui.button variant="secondary" icon="external-link" :href="route('home').'#visit'" target="_blank">View on website</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    @if ($placeholders)
        <div class="flex items-start gap-3 mb-5 px-4 py-3 rounded-card bg-warning-bg ring-1 ring-inset ring-warning/20 text-[13px] text-ink_text-primary max-w-[880px]">
            <x-ui.icon name="alert-triangle" :size="16" class="shrink-0 mt-0.5 text-warning" />
            <div><span class="font-semibold">The website still shows placeholder details.</span> Fill in the real WhatsApp number, phone and address before the site goes live.</div>
        </div>
    @endif

    <form wire:submit="save" class="grid grid-cols-1 lg:grid-cols-2 gap-6 max-w-[880px]">
        <x-ui.card title="Contact" subtitle="Every Enquire and Book a visit button opens WhatsApp with this number." icon="phone">
            <div class="space-y-4">
                <x-ui.field label="WhatsApp number" for="s-wa" error="whatsapp" hint="With country code, e.g. 91 98765 43210.">
                    <input id="s-wa" type="tel" wire:model="whatsapp" class="rj-input tabular @error('whatsapp') is-invalid @enderror" autocomplete="off" placeholder="91 98765 43210">
                </x-ui.field>
                <x-ui.field label="Phone, as shown on the site" for="s-phone" error="phone">
                    <input id="s-phone" type="text" wire:model="phone" class="rj-input @error('phone') is-invalid @enderror" placeholder="+91 98765 43210">
                </x-ui.field>
            </div>
        </x-ui.card>

        <x-ui.card title="Showroom" subtitle="The Visit section and the footer." icon="map-pin">
            <div class="space-y-4">
                <x-ui.field label="Address" for="s-address" error="address">
                    <input id="s-address" type="text" wire:model="address" class="rj-input @error('address') is-invalid @enderror" placeholder="Shop no., road, town">
                </x-ui.field>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <x-ui.field label="Opening hours" for="s-hours" error="hours">
                        <input id="s-hours" type="text" wire:model="hours" class="rj-input @error('hours') is-invalid @enderror">
                    </x-ui.field>
                    <x-ui.field label="Parking" for="s-parking" error="parking" optional>
                        <input id="s-parking" type="text" wire:model="parking" class="rj-input">
                    </x-ui.field>
                </div>
                <x-ui.field label="Map search" for="s-maps" error="maps_query" hint="What Google Maps should search for to find the showroom.">
                    <input id="s-maps" type="text" wire:model="maps_query" class="rj-input @error('maps_query') is-invalid @enderror">
                </x-ui.field>
            </div>
        </x-ui.card>

        <x-ui.card title="Social links" subtitle="Leave one empty to hide its icon." icon="link" class="lg:col-span-2">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <x-ui.field label="Instagram" for="s-ig" error="instagram" optional>
                    <input id="s-ig" type="url" wire:model="instagram" class="rj-input @error('instagram') is-invalid @enderror" placeholder="https://instagram.com/...">
                </x-ui.field>
                <x-ui.field label="Facebook" for="s-fb" error="facebook" optional>
                    <input id="s-fb" type="url" wire:model="facebook" class="rj-input @error('facebook') is-invalid @enderror" placeholder="https://facebook.com/...">
                </x-ui.field>
                <x-ui.field label="YouTube" for="s-yt" error="youtube" optional>
                    <input id="s-yt" type="url" wire:model="youtube" class="rj-input @error('youtube') is-invalid @enderror" placeholder="https://youtube.com/...">
                </x-ui.field>
            </div>
        </x-ui.card>

        <div class="lg:col-span-2 flex justify-end">
            <x-ui.button type="submit" target="save" icon="check">Save settings</x-ui.button>
        </div>
    </form>
</div>
