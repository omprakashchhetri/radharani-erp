@props(['date' => null, 'size' => 'sm'])
{{-- Expected-return date as a badge: overdue in red, due today in amber, otherwise neutral. --}}
@php
$date = $date ? \Carbon\Carbon::parse($date)->startOfDay() : null;
$days = $date ? (int) today()->diffInDays($date, false) : null;
@endphp
@if (! $date)
    <x-ui.badge :size="$size" {{ $attributes }}>No due date</x-ui.badge>
@elseif ($days < 0)
    <x-ui.badge tone="danger" :size="$size" dot {{ $attributes }}>Overdue {{ abs($days) }} {{ \Illuminate\Support\Str::plural('day', abs($days)) }}</x-ui.badge>
@elseif ($days === 0)
    <x-ui.badge tone="warning" :size="$size" dot {{ $attributes }}>Due today</x-ui.badge>
@else
    <x-ui.badge :size="$size" {{ $attributes }}>Due {{ $date->format('j M') }}</x-ui.badge>
@endif
