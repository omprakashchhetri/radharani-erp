<div>
    <x-ui.page-header :title="$customer->name" :subtitle="$customer->phone.' · '.($customer->email ?: 'no email').' · Referral code '.($customer->referral_code ?? '—')"
        :crumbs="[['label' => 'Customers', 'href' => route('admin.customers')], ['label' => $customer->name]]" />

    <div class="grid grid-cols-2 xl:grid-cols-3 gap-4 mb-6">
        <x-ui.stat-card icon="coins" label="Exchange balance" :value="'₹'.number_format($customer->balance, 2)" />
        <x-ui.stat-card icon="gift" label="Loyalty points" :value="number_format($customer->loyalty_points)" />
        <x-ui.stat-card icon="user-check" label="Status" :value="strtoupper(str_replace('_', ' ', $customer->status))" />
    </div>

    <div class="rj-segment mb-5">
        @foreach (['purchases' => 'Purchase History', 'orders' => 'Current Orders', 'installments' => 'Installment Scheme'] as $key => $label)
            <button type="button" wire:click="setTab('{{ $key }}')" class="{{ $tab === $key ? 'is-active' : '' }}">{{ $label }}</button>
        @endforeach
    </div>

    @if ($tab === 'purchases')
        <x-ui.card :padding="false" class="overflow-hidden">
            <x-ui.table :headers="['Invoice', 'Date', 'Items', 'Total', 'Status']">
                @forelse ($sales as $sale)
                    <tr class="h-[56px] border-b border-line-light">
                        <td class="px-4">
                            <a href="{{ route('sales.invoice', $sale) }}" wire:navigate class="text-gold font-semibold rj-code">{{ $sale->invoice_number }}</a>
                        </td>
                        <td class="px-4 text-ink_text-primary">{{ $sale->created_at?->format('d M Y') }}</td>
                        <td class="px-4 tabular text-ink_text-primary">{{ $sale->items->count() }}</td>
                        <td class="px-4 tabular text-ink_text-primary">₹{{ number_format($sale->total, 2) }}</td>
                        <td class="px-4">
                            @if ($sale->confirmed_by_accountant)
                                <x-ui.badge tone="success" size="sm" dot>Confirmed</x-ui.badge>
                            @else
                                <x-ui.badge tone="warning" size="sm" dot>Reserved</x-ui.badge>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5">
                            <x-ui.empty-state icon="receipt" title="No purchases yet" compact />
                        </td>
                    </tr>
                @endforelse
            </x-ui.table>
        </x-ui.card>
    @endif

    @if ($tab === 'orders')
        <x-ui.card :padding="false" class="overflow-hidden">
            <x-ui.table :headers="['Order', 'Metal', 'Estimated value', 'Status', 'Placed']">
                @forelse ($orders as $order)
                    <tr class="h-[56px] border-b border-line-light">
                        <td class="px-4 text-ink_text-primary">{{ $order->product_description }}</td>
                        <td class="px-4 text-ink_text-secondary">{{ $order->metal ? ucfirst($order->metal) : '—' }}</td>
                        <td class="px-4 tabular text-ink_text-primary">₹{{ number_format($order->estimated_value, 2) }}</td>
                        <td class="px-4">
                            <x-ui.badge :tone="match($order->status) { 'delivered' => 'success', 'cancelled' => 'danger', 'ready' => 'info', default => 'warning' }" size="sm" dot>
                                {{ ucfirst($order->status) }}
                            </x-ui.badge>
                        </td>
                        <td class="px-4 text-[12.5px] text-ink_text-secondary whitespace-nowrap">{{ $order->created_at?->format('d M Y') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5">
                            <x-ui.empty-state icon="clipboard" title="No custom orders yet" compact />
                        </td>
                    </tr>
                @endforelse
            </x-ui.table>
        </x-ui.card>
    @endif

    @if ($tab === 'installments')
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            @forelse ($installmentSchemes as $scheme)
                <x-ui.card>
                    <div class="flex justify-between items-start mb-1">
                        <span class="font-display text-[18px] font-semibold text-ink_text-primary">₹{{ number_format($scheme->monthly_amount, 2) }}<span class="text-[12px] text-ink_text-secondary font-sans">/month</span></span>
                        <x-ui.badge :tone="$scheme->status === 'active' ? 'success' : 'neutral'" size="sm">{{ ucfirst($scheme->status) }}</x-ui.badge>
                    </div>
                    <div class="text-[12px] text-ink_text-secondary mb-3">
                        {{ $scheme->months_paid }} month(s) paid · started {{ \Illuminate\Support\Carbon::parse($scheme->start_date)->format('d M Y') }}
                    </div>
                    <div class="border-t border-line-light pt-2.5 space-y-1.5">
                        @forelse ($scheme->payments as $payment)
                            <div class="flex justify-between text-[12.5px] text-ink_text-primary">
                                <span>{{ \Illuminate\Support\Carbon::parse($payment->paid_on)->format('d M Y') }}</span>
                                <span class="tabular">₹{{ number_format($payment->amount, 2) }}</span>
                            </div>
                        @empty
                            <span class="text-[12.5px] text-ink_text-secondary">No payments recorded yet.</span>
                        @endforelse
                    </div>
                </x-ui.card>
            @empty
                <x-ui.empty-state icon="calendar" title="No installment scheme" message="This customer is not enrolled in the installment scheme." compact />
            @endforelse
        </div>
    @endif
</div>
