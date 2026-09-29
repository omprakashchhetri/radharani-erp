<div>
    <x-ui.page-header title="Tally-Compatible Export" subtitle="Exports the ledger as CSV for the chosen date range."
        :crumbs="[['label' => 'Accounting', 'href' => route('accounting.ledger')], ['label' => 'Tally Export']]">
        <x-slot:actions>
            <x-ui.button variant="secondary" icon="receipt" :href="route('accounting.ledger')">Ledger</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card class="max-w-[480px]">
        <div class="grid grid-cols-2 gap-4 mb-5">
            <x-ui.field label="From" for="te-from" error="fromDate">
                <input id="te-from" type="date" class="rj-input w-full @error('fromDate') is-invalid @enderror" wire:model.live="fromDate">
            </x-ui.field>
            <x-ui.field label="To" for="te-to" error="toDate">
                <input id="te-to" type="date" class="rj-input w-full @error('toDate') is-invalid @enderror" wire:model.live="toDate">
            </x-ui.field>
        </div>

        <div class="flex items-center justify-between mb-4 px-3.5 py-2.5 rounded-xl bg-surface-sunken ring-1 ring-inset ring-line-light">
            <span class="text-[12.5px] text-ink_text-secondary">Rows in this range</span>
            <span class="font-display text-[18px] font-semibold tabular text-ink_text-primary">{{ number_format($preview) }}</span>
        </div>

        <x-ui.button type="button" wire:click="download" variant="primary" icon="download" class="w-full">Download CSV</x-ui.button>
    </x-ui.card>

    <div class="mt-5 rounded-card bg-gold-tint ring-1 ring-inset ring-gold-soft p-5 max-w-[480px]">
        <div class="flex items-center gap-2 text-[12.5px] font-semibold text-gold-dark mb-2">
            <x-ui.icon name="info" :size="14" /> Not a true Tally voucher import
        </div>
        <p class="text-[12.5px] text-gold-dark leading-relaxed">
            This is a plain CSV of the ledger, not a Tally XML voucher import — there's no ledger-name mapping table in the schema yet to translate accounts into Tally's own ledger names. Flagging that as a follow-up rather than guessing a mapping.
        </p>
    </div>
</div>
