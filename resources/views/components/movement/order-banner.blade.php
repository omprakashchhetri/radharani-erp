@props(['order', 'fullUrl', 'fullLabel' => 'Show the full desk'])
{{-- Shown when a desk is opened from a custom order: the screen is about this one order. --}}
<div class="mb-6 flex flex-wrap items-start gap-4 p-5 rounded-card border bg-gold-tint/60 border-gold-soft">
    <div class="w-10 h-10 shrink-0 rounded-xl bg-white/80 flex items-center justify-center text-gold-dark"><x-ui.icon name="clipboard" :size="18" /></div>
    <div class="flex-1 min-w-[220px]">
        <div class="text-[11.5px] font-bold uppercase tracking-wide text-gold-dark">Working on order #{{ $order->id }}</div>
        <div class="text-[15px] font-bold text-ink_text-primary mt-0.5">{{ $order->product_description }}</div>
        <div class="text-[12.5px] text-ink_text-secondary mt-0.5">
            For {{ $order->customer?->name }}
            @if ($order->metal) · {{ ucfirst($order->metal) }}@endif
            @if ($order->estimated_weight) · about {{ number_format((float) $order->estimated_weight, 3) }} g @endif
            @if ($order->expected_ready_date) · due {{ $order->expected_ready_date->format('j M') }}@endif
        </div>
        <a href="{{ route('orders.show', $order) }}" class="inline-block mt-2 text-[12.5px] font-semibold text-gold-dark hover:underline">Back to the order</a>
        <span class="text-ink_text-muted text-[12.5px]"> · </span>
        <a href="{{ $fullUrl }}" class="text-[12.5px] font-semibold text-ink_text-secondary hover:underline">{{ $fullLabel }}</a>
    </div>
    @if ($order->images->isNotEmpty())
        <div class="flex gap-2">
            @foreach ($order->images->take(3) as $img)
                @if ($img->url)<a href="{{ $img->url }}" target="_blank"><img src="{{ $img->url }}" alt="Reference" class="w-16 h-16 rounded-lg object-cover ring-1 ring-line"></a>@endif
            @endforeach
        </div>
    @endif
</div>
