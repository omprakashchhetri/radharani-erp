@props(['size' => 36])
{{-- The Radharani "R" mark (public/images/auth/mark.png, 360 x 336, transparent). The one place the logo file is referenced. --}}
<img src="{{ asset('images/auth/mark.png') }}" alt="Radharani Jewellery Works" draggable="false"
    width="{{ $size }}" height="{{ (int) round($size * 336 / 360) }}"
    {{ $attributes->merge(['class' => 'shrink-0 object-contain select-none']) }}>
