@props(['counts', 'total'])
{{-- Where the pieces in a box/packet are right now: one stacked bar plus a legend with counts. --}}
@php
$order = [
    'in_stock' => ['In stock', 'bg-success'],
    'on_hold' => ['On hold', 'bg-info/50'],
    'dispatched' => ['Dispatched', 'bg-warning'],
    'pending_review' => ['Pending review', 'bg-info'],
    'reserved' => ['Reserved', 'bg-gold'],
    'sold' => ['Sold', 'bg-ink_text-muted'],
];
$counts = collect($counts);
@endphp
@if ($total > 0)
    <div class="flex h-2 rounded-full overflow-hidden gap-[2px]" role="img" aria-label="Status breakdown">
        @foreach ($order as $key => [$name, $color])
            @if (($counts[$key] ?? 0) > 0)
                <div class="{{ $color }}" style="width: {{ round($counts[$key] / $total * 100, 2) }}%" title="{{ $name }}: {{ $counts[$key] }}"></div>
            @endif
        @endforeach
    </div>
    <dl class="grid grid-cols-2 gap-x-4 gap-y-2 mt-3.5">
        @foreach ($order as $key => [$name, $color])
            <div class="flex items-center justify-between gap-2 text-[12.5px]">
                <dt class="flex items-center gap-2 text-ink_text-secondary"><span class="w-2 h-2 rounded-sm {{ $color }}"></span>{{ $name }}</dt>
                <dd class="font-semibold tabular {{ ($counts[$key] ?? 0) ? 'text-ink_text-primary' : 'text-ink_text-muted' }}">{{ $counts[$key] ?? 0 }}</dd>
            </div>
        @endforeach
    </dl>
@else
    <p class="text-[13px] text-ink_text-muted">No pieces inside yet.</p>
@endif
