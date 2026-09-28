@props([
    'target',                 // CSS selector of the input the code goes into, e.g. '#pick-input'
    'submit' => false,        // false: just fill | 'enter': press Enter on it | 'form': submit its form
    'continuous' => false,    // keep the camera open for tag after tag
    'title' => 'Scan a code',
    'variant' => 'icon',      // icon: small square inside/next to a field | button: labelled button
    'label' => 'Scan with camera',
])
{{-- Opens the phone-camera scanner (components/layouts/partials/scanner.blade.php) for one field. --}}
@php
$payload = ['target' => $target, 'submit' => $submit, 'continuous' => (bool) $continuous, 'title' => $title];
$classes = $variant === 'button'
    ? 'press inline-flex items-center justify-center gap-2 h-10 px-4 rounded-control text-[13px] font-semibold bg-ink text-gold-light border border-ink hover:bg-ink-charcoal transition-colors'
    : 'press inline-flex items-center justify-center w-9 h-9 shrink-0 rounded-lg text-gold-dark bg-gold-tint ring-1 ring-inset ring-gold-soft/70 hover:bg-[#F6EAD0] transition-colors';
@endphp
<button type="button" x-data x-on:click.prevent="$dispatch('rj-scan', @js($payload))"
    title="{{ $label }}" aria-label="{{ $label }}" {{ $attributes->merge(['class' => $classes]) }}>
    <x-ui.icon name="camera" :size="$variant === 'button' ? 16 : 16" />
    @if ($variant === 'button')<span>{{ $label }}</span>@endif
</button>
