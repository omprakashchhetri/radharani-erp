@props(['metal' => null, 'size' => 'md'])
{{-- The small metal swatch used next to pieces and batches (same gradients as Inventory). --}}
<span {{ $attributes->class([
    'shrink-0 rounded-full ring-2 ring-white shadow',
    'w-2.5 h-2.5' => $size === 'md',
    'w-3.5 h-3.5' => $size === 'lg',
    'bg-gradient-to-br from-gold-light to-gold' => $metal === 'gold',
    'bg-gradient-to-br from-[#E4E4E4] to-[#A9A9A9]' => $metal === 'silver',
    'bg-gradient-to-br from-[#E9E6E1] to-[#B8B3AA]' => $metal === 'platinum',
    'bg-gradient-to-br from-[#9EA4AA] to-[#5F666D]' => $metal === 'titanium',
    'bg-line' => ! $metal,
]) }}></span>
