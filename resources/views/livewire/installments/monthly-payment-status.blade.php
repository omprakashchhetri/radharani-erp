<div>
    <x-ui.page-header :title="'Monthly Payment Status — '.now()->format('F Y')" subtitle="Mark each active scheme paid or not-paid for this month.">
        <x-slot:actions>
            <x-ui.button variant="secondary" icon="list" :href="route('installments.list')">Scheme list</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
        <x-ui.stat-card icon="calendar" label="Active schemes" :value="number_format($stats['active'])" />
        <x-ui.stat-card icon="check-circle" label="Paid this month" :value="number_format($stats['paid'])" />
        <x-ui.stat-card icon="clock" label="Still due" :value="number_format($stats['due'])" />
        <x-ui.stat-card icon="coins" label="Due amount" :value="'₹'.number_format($stats['dueAmount'], 2)" />
    </div>

    <x-ui.card :padding="false" class="overflow-hidden">
        <x-ui.table :headers="['Customer', 'Monthly amount', 'Months paid', 'This month', '']">
            @forelse ($schemes as $scheme)
                <tr wire:key="mps-{{ $scheme->id }}" class="h-[56px] border-b border-line-light">
                    <td class="px-4 text-ink_text-primary font-semibold">{{ $scheme->customer->name ?? '—' }}</td>
                    <td class="px-4 tabular text-ink_text-primary">₹{{ number_format($scheme->monthly_amount, 2) }}</td>
                    <td class="px-4 tabular text-ink_text-secondary">{{ $scheme->months_paid }}</td>
                    <td class="px-4">
                        @if ($scheme->paidThisMonth)
                            <x-ui.badge tone="success" size="sm" dot>Paid</x-ui.badge>
                        @else
                            <x-ui.badge tone="warning" size="sm" dot>Not paid</x-ui.badge>
                        @endif
                    </td>
                    <td class="px-4">
                        <div class="flex items-center justify-end gap-1.5">
                            @unless ($scheme->paidThisMonth)
                                <x-ui.button type="button" variant="secondary" size="sm" icon="bell" wire:click="sendReminder({{ $scheme->id }})">Send reminder</x-ui.button>
                                <x-ui.button type="button" variant="primary" size="sm" icon="check"
                                    x-on:click="$dispatch('rj-confirm', { title: 'Mark this month paid?', message: 'Records a payment of ₹{{ number_format($scheme->monthly_amount, 2) }} for {{ $scheme->customer->name }} and increments months paid. This cannot be undone from here.', confirm: 'Mark paid', action: () => $wire.markPaid({{ $scheme->id }}) })">
                                    Mark paid
                                </x-ui.button>
                            @endunless
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5">
                        <x-ui.empty-state icon="calendar" title="No active schemes" message="Enrol a customer to start tracking their monthly payments.">
                            <x-ui.button size="sm" icon="plus" :href="route('installments.enrol')">Enrol customer</x-ui.button>
                        </x-ui.empty-state>
                    </td>
                </tr>
            @endforelse
        </x-ui.table>
    </x-ui.card>
</div>
