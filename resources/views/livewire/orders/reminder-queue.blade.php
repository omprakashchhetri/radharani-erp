<div>
    <x-ui.page-header title="Ready Reminders" subtitle="Copy the message, send it manually, mark it sent — admin verifies once the customer actually collects."
        :crumbs="[['label' => 'Custom Orders'], ['label' => 'Ready Reminders']]" />

    @if ($ready->isEmpty())
        <x-ui.empty-state icon="gift" title="Nothing waiting on collection" message="Orders appear here once they're marked Ready on their detail page." />
    @else
        {{-- Mobile: stacked cards --}}
        <div class="sm:hidden space-y-3">
            @foreach ($ready as $o)
                <div class="bg-white border border-line-light rounded-card shadow-card p-4">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <div class="font-bold text-[13.5px] text-ink_text-primary">{{ $o->customer?->name }}</div>
                            <div class="text-[12px] text-ink_text-muted">{{ $o->customer?->phone }}</div>
                        </div>
                        @if ($o->notificationSent)
                            <x-ui.badge tone="warning" size="sm">Sent</x-ui.badge>
                        @endif
                    </div>
                    <div class="text-[12.5px] text-ink_text-secondary mt-2">{{ $o->product_description }}</div>
                    <div class="text-[11.5px] text-ink_text-muted mt-1">Ready {{ $o->updated_at->diffForHumans() }}</div>
                    <div class="flex flex-wrap gap-2 mt-3">
                        <x-ui.button variant="secondary" size="sm" icon="copy" class="flex-1"
                            x-on:click="navigator.clipboard.writeText('Hi {{ $o->customer?->name }}, your order ({{ $o->product_description }}) is ready for collection at Radharani Jewellery Works.'); $dispatch('toast', { message: 'Message copied.', type: 'success' })">
                            Copy text
                        </x-ui.button>
                        @if ($o->notificationSent)
                            <x-ui.button variant="dark" size="sm" icon="check" class="flex-1"
                                x-on:click="$dispatch('rj-confirm', { title: 'Confirm collected?', message: 'This marks the order delivered.', confirm: 'Yes, collected', action: () => $wire.verify({{ $o->id }}) })">
                                Verify collected
                            </x-ui.button>
                        @else
                            <x-ui.button size="sm" icon="check" class="flex-1" wire:click="markSent({{ $o->id }})">Mark sent</x-ui.button>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Desktop: table --}}
        <x-ui.card :padding="false" class="hidden sm:block overflow-hidden">
            <x-ui.table :headers="['Customer', 'Item', 'Ready since', 'Message', ['label' => 'Status', 'class' => 'w-px']]">
                @foreach ($ready as $o)
                    <tr wire:key="rq-{{ $o->id }}">
                        <td>
                            <div class="font-semibold text-ink_text-primary">{{ $o->customer?->name }}</div>
                            <div class="text-[12px] text-ink_text-muted">{{ $o->customer?->phone }}</div>
                        </td>
                        <td class="max-w-[220px] truncate">{{ $o->product_description }}</td>
                        <td class="text-[12.5px] text-ink_text-secondary whitespace-nowrap">{{ $o->updated_at->diffForHumans() }}</td>
                        <td>
                            <x-ui.button variant="secondary" size="sm" icon="copy"
                                x-on:click="navigator.clipboard.writeText('Hi {{ $o->customer?->name }}, your order ({{ $o->product_description }}) is ready for collection at Radharani Jewellery Works.'); $dispatch('toast', { message: 'Message copied.', type: 'success' })">
                                Copy text
                            </x-ui.button>
                        </td>
                        <td class="whitespace-nowrap">
                            @if ($o->notificationSent)
                                <div class="flex items-center gap-2">
                                    <x-ui.badge tone="warning" size="sm">Sent</x-ui.badge>
                                    <button type="button" class="text-[12px] font-bold text-gold-dark hover:underline"
                                        x-on:click="$dispatch('rj-confirm', { title: 'Confirm collected?', message: 'This marks the order delivered.', confirm: 'Yes, collected', action: () => $wire.verify({{ $o->id }}) })">
                                        Verify collected
                                    </button>
                                </div>
                            @else
                                <button type="button" wire:click="markSent({{ $o->id }})" class="text-[12px] font-bold text-gold-dark hover:underline">Mark sent</button>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </x-ui.table>
        </x-ui.card>
    @endif
</div>
