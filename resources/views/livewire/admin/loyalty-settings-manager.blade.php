<div>
    <x-ui.page-header title="Loyalty & Referral Settings" subtitle="Controls how points are earned and what they're worth — no code changes needed to adjust these." />

    <x-ui.card class="max-w-[520px]">
        <form wire:submit="save" class="space-y-4">
            <x-ui.field label="Points earned per ₹ spent" for="ls-points" error="points_per_rupee" hint="e.g. 0.001 = 1 point per ₹1,000">
                <input id="ls-points" type="number" step="0.00001" class="rj-input w-full tabular @error('points_per_rupee') is-invalid @enderror" wire:model="points_per_rupee">
            </x-ui.field>
            <x-ui.field label="Referral bonus (points)" for="ls-referral" error="referral_bonus_points" hint="Paid to the referrer once the new customer's first sale is confirmed">
                <input id="ls-referral" type="number" class="rj-input w-full tabular @error('referral_bonus_points') is-invalid @enderror" wire:model="referral_bonus_points">
            </x-ui.field>
            <x-ui.field label="Minimum points to redeem" for="ls-min" error="min_redeemable_points">
                <input id="ls-min" type="number" class="rj-input w-full tabular @error('min_redeemable_points') is-invalid @enderror" wire:model="min_redeemable_points">
            </x-ui.field>
            <x-ui.field label="Value of 1 point (₹)" for="ls-value" error="point_value_in_rupees" hint="Used to show &quot;your points are worth ₹X&quot; to customers">
                <input id="ls-value" type="number" step="0.01" class="rj-input w-full tabular @error('point_value_in_rupees') is-invalid @enderror" wire:model="point_value_in_rupees">
            </x-ui.field>

            <x-ui.button type="submit" target="save" variant="primary" icon="check">Save settings</x-ui.button>
        </form>
    </x-ui.card>
</div>
