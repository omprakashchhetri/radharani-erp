@props(['centres', 'on' => false])
{{-- #7: after a karigar return, send the piece straight on to hallmarking instead of into review.
     Binds $toHallmark, $centreId and $hallmarkDue. --}}
<div @class(['rounded-xl ring-1 ring-inset transition-colors', 'ring-gold-soft bg-gold-tint/50' => $on, 'ring-line-light' => ! $on])>
    <label class="flex items-start gap-3 px-4 py-3.5 cursor-pointer select-none">
        <input type="checkbox" wire:model.live="toHallmark" class="rj-checkbox mt-0.5">
        <span>
            <span class="block text-[13.5px] font-semibold text-ink_text-primary">Send it to hallmarking next</span>
            <span class="block text-[12.5px] text-ink_text-secondary">Skips review for now and records a hallmarking dispatch in the same step.</span>
        </span>
    </label>
    @if ($on)
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 px-4 pb-4">
            <x-ui.field label="Hallmarking centre" for="hc-centre" error="centreId">
                <select id="hc-centre" wire:model="centreId" class="rj-select @error('centreId') is-invalid @enderror">
                    <option value="">Choose a centre</option>
                    @foreach ($centres as $c)
                        <option value="{{ $c->id }}">{{ $c->name }}</option>
                    @endforeach
                </select>
            </x-ui.field>
            <x-ui.field label="Expected back from hallmarking" for="hc-due" error="hallmarkDue">
                <input id="hc-due" type="date" wire:model="hallmarkDue" class="rj-input tabular">
            </x-ui.field>
        </div>
    @endif
</div>
