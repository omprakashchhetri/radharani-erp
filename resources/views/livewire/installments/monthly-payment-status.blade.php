<div>
    <x-ui.page-header :title="'Monthly Payment Status — '.now()->format('F Y')" subtitle="Every active scheme: paid up, due this month, or late. Each instalment falls due a month after the one before, counting from the start date.">
        <x-slot:actions>
            <x-ui.button variant="secondary" icon="list" :href="route('installments.list')">Scheme list</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid grid-cols-2 xl:grid-cols-5 gap-4 mb-6">
        <x-ui.stat-card icon="calendar" label="Active schemes" :value="number_format($stats['active'])" />
        <x-ui.stat-card icon="check-circle" label="Paid up" :value="number_format($stats['paid'])" />
        <x-ui.stat-card icon="clock" label="Due this month" :value="number_format($stats['due'])" />
        <x-ui.stat-card icon="alert-triangle" label="Late" :value="number_format($stats['late'])" hint="past the due date" />
        <x-ui.stat-card icon="coins" label="To collect" :value="'₹'.number_format($stats['dueAmount'], 2)" hint="late and due" />
    </div>

    <div class="rj-segment mb-5">
        @foreach (['all' => 'All', 'late' => 'Late', 'due' => 'Due this month', 'paid' => 'Paid up'] as $k => $l)
            <button type="button" wire:click="setShow('{{ $k }}')" class="{{ $show === $k ? 'is-active' : '' }}">{{ $l }}</button>
        @endforeach
    </div>

    <x-ui.card :padding="false" class="overflow-hidden">
        <x-ui.table :headers="['Customer', 'Monthly amount', 'Months paid', 'Next due', 'Status', '']">
            @forelse ($schemes as $scheme)
                <tr wire:key="mps-{{ $scheme->id }}" class="h-[56px] border-b border-line-light">
                    <td class="px-4 text-ink_text-primary font-semibold">{{ $scheme->customer->name ?? '—' }}<span class="block text-[12px] font-normal text-ink_text-muted tabular">{{ $scheme->customer->phone ?? '' }}</span></td>
                    <td class="px-4 tabular text-ink_text-primary">₹{{ number_format($scheme->monthly_amount, 2) }}</td>
                    <td class="px-4 tabular text-ink_text-secondary">{{ $scheme->months_paid }} / {{ $scheme->total_months }}</td>
                    <td class="px-4 tabular text-ink_text-secondary whitespace-nowrap">{{ $scheme->next_due_on?->format('j M Y') ?? 'All paid' }}</td>
                    <td class="px-4">
                        @if ($scheme->state === 'late')
                            <x-ui.badge tone="danger" size="sm" dot>Late · {{ $scheme->months_late }} {{ \Illuminate\Support\Str::plural('month', $scheme->months_late) }}, {{ $scheme->days_late }} {{ \Illuminate\Support\Str::plural('day', $scheme->days_late) }}</x-ui.badge>
                        @elseif ($scheme->state === 'due')
                            <x-ui.badge tone="warning" size="sm" dot>Due this month</x-ui.badge>
                        @else
                            <x-ui.badge tone="success" size="sm" dot>Paid up</x-ui.badge>
                        @endif
                    </td>
                    <td class="px-4">
                        <div class="flex items-center justify-end gap-1.5">
                            @if ($scheme->state !== 'paid')
                                <x-ui.button type="button" variant="secondary" size="sm" icon="bell" wire:click="sendReminder({{ $scheme->id }})">Send reminder</x-ui.button>
                            @endif
                            @if (! $scheme->is_matured)
                                <x-ui.button type="button" :variant="$scheme->state === 'paid' ? 'ghost' : 'primary'" size="sm" icon="check" wire:click="openPay({{ $scheme->id }})">Record payment</x-ui.button>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6">
                        <x-ui.empty-state icon="calendar" :title="$show === 'all' ? 'No active schemes' : 'Nothing here'" :message="$show === 'all' ? 'Enrol a customer to start tracking their monthly payments.' : 'No scheme is in this group right now.'">
                            @if ($show === 'all')<x-ui.button size="sm" icon="plus" :href="route('installments.enrol')">Enrol customer</x-ui.button>@endif
                        </x-ui.empty-state>
                    </td>
                </tr>
            @endforelse
        </x-ui.table>
    </x-ui.card>

    <x-ui.modal wire:model="showPay" title="Record a payment" icon="coins" max-width="sm" submit="recordPayment"
        :subtitle="$paySchemeModel ? $paySchemeModel->customer->name . ', instalment ' . ($paySchemeModel->months_paid + 1) . ' of ' . $paySchemeModel->total_months : null">
        @if ($paySchemeModel && $paySchemeModel->next_due_on)
            <dl class="rj-dl mb-4">
                <div><dt>Amount</dt><dd class="tabular font-semibold">₹{{ number_format($paySchemeModel->monthly_amount, 2) }}</dd></div>
                <div><dt>Due on</dt><dd class="tabular">{{ $paySchemeModel->next_due_on->format('j M Y') }}</dd></div>
            </dl>
            @if ($paySchemeModel->next_due_on->lt(today()))
                <p class="text-[13px] text-danger mb-3">This instalment is {{ (int) $paySchemeModel->next_due_on->diffInDays(today()) }} {{ \Illuminate\Support\Str::plural('day', (int) $paySchemeModel->next_due_on->diffInDays(today())) }} late.</p>
                <x-ui.field label="Late fee (₹)" for="mp-late" error="lateFee" optional hint="Typed in by you, never worked out. Leave empty for none.">
                    <input id="mp-late" type="number" step="0.01" min="0" wire:model="lateFee" class="rj-input tabular @error('lateFee') is-invalid @enderror" autofocus>
                </x-ui.field>
            @else
                <p class="text-[13px] text-ink_text-secondary">On time. No late fee.</p>
            @endif
        @endif
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="show = false">Cancel</x-ui.button>
            <x-ui.button type="submit" target="recordPayment" icon="check">Record payment</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
