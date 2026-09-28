@props(['sent' => null, 'diff' => null, 'weightLabel' => 'Weight on the scale now'])
{{-- Return weights for components with $weightReturned, $weightLoss and $returnDate.
     The loss is always typed in by staff (#6); the scale difference is shown for reference only. --}}
<div>
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <x-ui.field :label="$weightLabel" for="wf-returned" error="weightReturned">
            <div class="relative">
                <input id="wf-returned" type="number" step="0.001" min="0" wire:model.live.debounce.500ms="weightReturned"
                    class="rj-input pr-9 tabular @error('weightReturned') is-invalid @enderror">
                <span class="absolute right-3.5 top-1/2 -translate-y-1/2 text-[13px] text-ink_text-muted pointer-events-none">g</span>
            </div>
        </x-ui.field>
        <x-ui.field label="Weight loss" for="wf-loss" error="weightLoss" hint="Entered by hand. 0 if none.">
            <div class="relative">
                <input id="wf-loss" type="number" step="0.001" min="0" wire:model="weightLoss"
                    class="rj-input pr-9 tabular bg-gold-tint/40 border-gold-soft @error('weightLoss') is-invalid @enderror">
                <span class="absolute right-3.5 top-1/2 -translate-y-1/2 text-[13px] text-ink_text-muted pointer-events-none">g</span>
            </div>
        </x-ui.field>
        <x-ui.field label="Came back on" for="wf-date" error="returnDate">
            <input id="wf-date" type="date" wire:model="returnDate" max="{{ today()->toDateString() }}" class="rj-input tabular @error('returnDate') is-invalid @enderror">
        </x-ui.field>
    </div>

    @if ($sent !== null)
        <div class="flex flex-wrap items-center gap-x-5 gap-y-1 mt-3.5 px-3.5 py-2.5 rounded-control bg-surface-sunken ring-1 ring-inset ring-line-light text-[12.5px] text-ink_text-secondary">
            <span class="inline-flex items-center gap-1.5"><x-ui.icon name="weight" :size="13" class="text-gold-dark" /> Sent out at <span class="font-semibold text-ink_text-primary tabular">{{ number_format($sent, 3) }} g</span></span>
            @if ($diff !== null)
                <span>Scale difference <span class="font-semibold tabular {{ $diff > 0 ? 'text-warning' : 'text-ink_text-primary' }}">{{ number_format($diff, 3) }} g</span></span>
                <span class="text-ink_text-muted">For reference. Enter the loss you measured.</span>
            @endif
        </div>
    @endif
</div>
