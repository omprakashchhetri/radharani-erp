@props(['size' => 36])
{{-- The Radharani golden mark (public/images/brand/mark-golden.png, square, transparent; cut from images/auth/logo-golden.png). The one place the logo file is referenced. --}}
<img src="{{ asset('images/brand/mark-golden.png') }}" alt="Radharani Jewellery Works" draggable="false"
    width="{{ $size }}" height="{{ $size }}"
    {{ $attributes->merge(['class' => 'shrink-0 object-contain select-none']) }}>
