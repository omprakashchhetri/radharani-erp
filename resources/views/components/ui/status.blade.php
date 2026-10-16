@props(['status', 'size' => 'md'])
{{-- Single source of truth for how an items.status value is shown anywhere in the UI. --}}
@php
$map = [
    'in_stock'       => ['success', 'In stock'],
    'on_hold'        => ['info', 'On hold'],
    'dispatched'     => ['warning', 'Dispatched'],
    'pending_review' => ['info', 'Pending review'],
    'reserved'       => ['gold', 'Reserved'],
    'sold'           => ['neutral', 'Sold'],
];
[$tone, $label] = $map[$status] ?? ['neutral', ucwords(str_replace('_', ' ', (string) $status))];
@endphp
<x-ui.badge :tone="$tone" :size="$size" dot {{ $attributes }}>{{ $label }}</x-ui.badge>
