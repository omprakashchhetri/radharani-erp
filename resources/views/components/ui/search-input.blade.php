@props(['placeholder' => 'Search...', 'scan' => false, 'scanTitle' => 'Scan to search', 'scanContinuous' => false])
{{-- Debounced live search box with a clear button. Pass wire:model.live.debounce... through.
     scan: adds a camera button that fills the box from a QR sticker or barcode. --}}
@php
$model = $attributes->wire('model')->value();
$inputId = $attributes->get('id') ?: 'search-' . substr(md5($model . $placeholder), 0, 8);
@endphp
<div class="rj-input-icon {{ $attributes->get('class') }}">
    <x-ui.icon name="search" :size="15" />
    <input type="search" id="{{ $inputId }}" placeholder="{{ $placeholder }}" autocomplete="off"
        {{ $attributes->except(['class', 'id'])->merge(['class' => 'rj-input [&::-webkit-search-cancel-button]:hidden ' . ($scan ? 'pr-[4.5rem]' : 'pr-9')]) }}>
    @if ($model)
        <button type="button" x-data x-show="$wire.{{ $model }}" x-cloak x-on:click="$wire.set('{{ $model }}', '')"
            class="absolute {{ $scan ? 'right-10' : 'right-2' }} top-1/2 -translate-y-1/2 w-6 h-6 rounded-md text-ink_text-muted hover:text-ink_text-primary hover:bg-surface-muted flex items-center justify-center"
            aria-label="Clear search">
            <x-ui.icon name="x" :size="13" />
        </button>
    @endif
    @if ($scan)
        <x-ui.scan-button :target="'#' . $inputId" :title="$scanTitle" :continuous="$scanContinuous" class="absolute right-1 top-1/2 -translate-y-1/2 !w-8 !h-8" />
    @endif
</div>
