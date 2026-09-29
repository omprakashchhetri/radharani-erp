<div>
    <x-ui.page-header title="Loyalty & Referral Settings" subtitle="Controls how points are earned and what they're worth — no code changes needed to adjust these.">
        <x-slot:actions>
            <x-ui.button variant="secondary" icon="gift" :href="route('loyalty.award')">Award points</x-ui.button>
            <x-ui.button variant="secondary" icon="history" :href="route('loyalty.ledger')">Points ledger</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid grid-cols-1 xl:grid-cols-[minmax(0,1fr)_360px] gap-6 items-start">
        <x-ui.card>
            <form wire:submit="save" class="space-y-4">
                <x-ui.field label="Points earned per ₹ spent" for="ls-points" error="points_per_rupee" hint="e.g. 0.001 = 1 point per ₹1,000">
                    <input id="ls-points" type="number" step="0.00001" class="rj-input w-full tabular @error('points_per_rupee') is-invalid @enderror" wire:model.live="points_per_rupee">
                </x-ui.field>
                <x-ui.field label="Referral bonus (points)" for="ls-referral" error="referral_bonus_points" hint="Paid to the referrer once the new customer's first sale is confirmed">
                    <input id="ls-referral" type="number" class="rj-input w-full tabular @error('referral_bonus_points') is-invalid @enderror" wire:model.live="referral_bonus_points">
                </x-ui.field>
                <x-ui.field label="Minimum points to redeem" for="ls-min" error="min_redeemable_points">
                    <input id="ls-min" type="number" class="rj-input w-full tabular @error('min_redeemable_points') is-invalid @enderror" wire:model.live="min_redeemable_points">
                </x-ui.field>
                <x-ui.field label="Value of 1 point (₹)" for="ls-value" error="point_value_in_rupees" hint="Used to show &quot;your points are worth ₹X&quot; to customers">
                    <input id="ls-value" type="number" step="0.01" class="rj-input w-full tabular @error('point_value_in_rupees') is-invalid @enderror" wire:model.live="point_value_in_rupees">
                </x-ui.field>

                <x-ui.button type="submit" target="save" variant="primary" icon="check">Save settings</x-ui.button>
            </form>
        </x-ui.card>

        <aside class="space-y-6 xl:sticky xl:top-24">
            <x-ui.card title="Worked example" icon="sliders">
                <dl class="rj-dl">
                    <div>
                        <dt>₹10,000 sale earns</dt>
                        <dd class="tabular">{{ number_format(floor(10000 * $points_per_rupee)) }} pts</dd>
                    </div>
                    <div>
                        <dt>100 points are worth</dt>
                        <dd class="tabular">₹{{ number_format(100 * $point_value_in_rupees, 2) }}</dd>
                    </div>
                    <div>
                        <dt>Referral bonus</dt>
                        <dd class="tabular">{{ number_format($referral_bonus_points) }} pts</dd>
                    </div>
                    <div class="col-span-2 pt-2 mt-1 border-t border-line-light">
                        <dt class="font-bold text-ink_text-primary">That bonus is worth</dt>
                        <dd class="font-display text-[20px] font-semibold tabular text-ink_text-primary">₹{{ number_format($referral_bonus_points * $point_value_in_rupees, 2) }}</dd>
                    </div>
                </dl>
            </x-ui.card>

            <div class="rounded-card bg-surface-sunken ring-1 ring-inset ring-line-light p-5">
                <div class="flex items-center gap-2 text-[12.5px] font-semibold text-ink_text-secondary mb-2">
                    <x-ui.icon name="info" :size="14" /> Fully manual
                </div>
                <p class="text-[12.5px] text-ink_text-secondary leading-relaxed">
                    These rates are not auto-applied — staff award and redeem points by hand from Loyalty → Award Points, using these numbers as a reference. Changing a setting here never rewrites past ledger entries.
                </p>
            </div>
        </aside>
    </div>
</div>
