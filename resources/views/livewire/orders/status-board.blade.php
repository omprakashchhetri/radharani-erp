<div x-data="{ tab: 'placed' }">
    <x-ui.page-header title="Order Status Board" subtitle="placed → confirmed → ready → delivered · cancelled off to the side"
        :crumbs="[['label' => 'Custom Orders'], ['label' => 'Status Board']]">
        <x-slot:actions>
            <x-ui.button icon="plus" :href="route('orders.new')">New order</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    @php
        $columns = ['placed' => 'Placed', 'confirmed' => 'Confirmed', 'ready' => 'Ready', 'delivered' => 'Delivered', 'cancelled' => 'Cancelled'];
        $tones = ['placed' => 'warning', 'confirmed' => 'warning', 'ready' => 'gold', 'delivered' => 'success', 'cancelled' => 'danger'];
    @endphp

    {{-- Mobile: segmented tab switcher, one column at a time --}}
    <div class="lg:hidden rj-segment mb-5 w-full overflow-x-auto flex-nowrap">
        @foreach ($columns as $key => $label)
            <button type="button" x-on:click="tab = '{{ $key }}'" :class="tab === '{{ $key }}' ? 'is-active' : ''" class="shrink-0">
                {{ $label }} <span class="tabular">({{ $orders->get($key, collect())->count() }})</span>
            </button>
        @endforeach
    </div>

    @foreach ($columns as $key => $label)
        <div x-show="tab === '{{ $key }}'" class="lg:hidden" x-cloak>
            @if ($orders->get($key, collect())->isEmpty())
                <x-ui.empty-state icon="clipboard" :title="'No orders ' . strtolower($label)" compact />
            @else
                <div class="flex flex-col gap-2.5">
                    @foreach ($orders->get($key, collect()) as $o)
                        <a href="{{ route('orders.show', $o) }}" class="block bg-white border border-line-light rounded-card shadow-card p-4 hover:border-gold-soft {{ $key === 'cancelled' ? 'opacity-60' : '' }}">
                            <div class="flex items-start justify-between gap-2">
                                <div class="font-bold text-[13px] text-ink_text-primary">{{ $o->customer?->name ?? 'Unknown' }}</div>
                                <x-ui.badge :tone="$tones[$key] ?? 'neutral'" size="sm">#{{ $o->id }}</x-ui.badge>
                            </div>
                            <div class="text-[12.5px] text-ink_text-secondary mt-1 truncate">{{ $o->product_description }}</div>
                            <div class="flex items-center justify-between mt-2.5">
                                <span class="text-[13px] font-bold text-gold-dark tabular">₹{{ number_format((float) $o->estimated_value) }}</span>
                                @if ($o->expected_ready_date && ! in_array($o->status, ['ready', 'delivered', 'cancelled'], true) && $o->expected_ready_date->isPast())
                                    <x-ui.badge tone="danger" size="sm"><x-ui.icon name="alert-triangle" :size="10" /> Overdue</x-ui.badge>
                                @endif
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    @endforeach

    {{-- Desktop: full 5-column board --}}
    <div class="hidden lg:grid grid-cols-5 gap-4">
        @foreach ($columns as $key => $label)
            <div>
                <div class="flex items-center justify-between mb-2.5 pl-0.5">
                    <span class="text-[11.5px] font-bold uppercase tracking-wide text-ink_text-secondary">{{ $label }}</span>
                    <span class="text-[11.5px] font-bold text-ink_text-muted tabular">{{ $orders->get($key, collect())->count() }}</span>
                </div>
                <div class="flex flex-col gap-2.5">
                    @forelse ($orders->get($key, collect()) as $o)
                        <a href="{{ route('orders.show', $o) }}" class="block bg-white border border-line-light rounded-card shadow-card p-3.5 hover:border-gold-soft hover:shadow-raised transition-[border-color,box-shadow] {{ $key === 'cancelled' ? 'opacity-60' : '' }}">
                            <div class="font-bold text-[12.5px] text-ink_text-primary truncate">{{ $o->customer?->name ?? 'Unknown' }}</div>
                            <div class="text-[11.5px] text-ink_text-secondary mt-1 line-clamp-2">{{ $o->product_description }}</div>
                            <div class="flex items-center justify-between mt-2.5">
                                <span class="text-xs font-semibold text-gold-dark tabular">₹{{ number_format((float) $o->estimated_value) }}</span>
                                @if ($o->expected_ready_date && ! in_array($o->status, ['ready', 'delivered', 'cancelled'], true) && $o->expected_ready_date->isPast())
                                    <x-ui.icon name="alert-triangle" :size="13" class="text-danger" />
                                @endif
                            </div>
                        </a>
                    @empty
                        <div class="text-[12px] text-ink_text-muted px-1 py-3">Nothing here</div>
                    @endforelse
                </div>
            </div>
        @endforeach
    </div>
</div>
