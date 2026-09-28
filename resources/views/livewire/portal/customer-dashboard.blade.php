<div class="min-h-screen">
    <div class="max-w-[720px] mx-auto px-5 pt-8 pb-16">

        <div class="flex justify-between items-center mb-6">
            <div class="flex items-center gap-3.5">
                <x-ui.logo :size="48" class="w-12 h-12" />
                <div>
                    <div class="text-[22px] font-semibold">Hello, {{ $customer->name }}</div>
                    <div class="text-xs text-ink_text-secondary mt-0.5">Loyalty points: <strong class="text-ink_text-primary">{{ $customer->loyalty_points }}</strong></div>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('portal.change-password') }}" wire:navigate>
                    <x-ui.button variant="secondary">Change Password</x-ui.button>
                </a>
                <x-ui.button type="button" wire:click="logout" variant="secondary">Log Out</x-ui.button>
            </div>
        </div>

        <div class="flex gap-0 mb-6 border-b border-line">
            @foreach (['purchases' => 'Past Purchases', 'loyalty' => 'Loyalty & Referral', 'installments' => 'Installments'] as $key => $label)
                <button wire:click="setTab('{{ $key }}')"
                    class="py-2.5 px-1 mr-5 bg-transparent border-0 border-b-2 text-[13px] {{ $tab === $key ? 'border-gold text-gold font-bold' : 'border-transparent text-ink_text-secondary font-semibold' }}">
                    {{ $label }}
                </button>
            @endforeach
        </div>

        @if ($tab === 'purchases')
            <div class="flex flex-col gap-3">
                @forelse ($customer->sales as $sale)
                    <x-ui.card>
                        <div class="flex justify-between">
                            <span class="font-bold text-[13.5px]">Invoice {{ $sale->invoice_number }}</span>
                            <span class="text-[13px]">₹{{ number_format($sale->total, 2) }}</span>
                        </div>
                        <div class="text-[11.5px] text-ink_text-secondary mt-1">
                            {{ $sale->created_at?->format('d M Y') }} · {{ $sale->items->count() }} item(s)
                        </div>
                    </x-ui.card>
                @empty
                    <div class="text-ink_text-secondary text-[13px]">No purchases yet.</div>
                @endforelse
            </div>
        @endif

        @if ($tab === 'loyalty')
            <x-ui.card class="!p-0 overflow-hidden mb-5">
                <x-ui.table :headers="['Date', 'Reason', 'Points']">
                    @forelse ($customer->loyaltyTransactions as $tx)
                        <tr class="border-b border-line-light">
                            <td class="px-4 py-2.5">{{ $tx->created_at?->format('d M Y') }}</td>
                            <td class="px-4 py-2.5">{{ ucfirst($tx->reason) }}</td>
                            <td class="px-4 py-2.5 text-right {{ $tx->points >= 0 ? 'text-success' : 'text-danger' }}">
                                {{ $tx->points >= 0 ? '+' : '' }}{{ $tx->points }}
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="px-4 py-4 text-ink_text-secondary">No loyalty activity yet.</td></tr>
                    @endforelse
                </x-ui.table>
            </x-ui.card>

            <div class="bg-[#FFF7E6] rounded-control p-4">
                <div class="text-[12.5px] text-gold-dark font-bold">Your Referral Code</div>
                <div class="text-xl font-semibold mt-1">{{ $customer->referral_code ?? '—' }}</div>
                <div class="text-[11.5px] text-gold-dark mt-1">Share this — you earn bonus points when they make their first purchase.</div>
            </div>

            @if ($customer->referrals->count())
            <x-ui.card class="mt-4">
                <div class="text-[12.5px] font-bold mb-2.5">People You've Referred</div>
                @foreach ($customer->referrals as $referred)
                    <div class="flex justify-between py-1.5 text-[12.5px] border-b border-line-light last:border-0">
                        <span>{{ $referred->name }}</span>
                        <span class="{{ $referred->sales_count > 0 ? 'text-success' : 'text-ink_text-secondary' }}">
                            {{ $referred->sales_count > 0 ? 'Bonus earned' : 'Awaiting first purchase' }}
                        </span>
                    </div>
                @endforeach
            </x-ui.card>
            @endif
        @endif

        @if ($tab === 'installments')
            <div class="flex flex-col gap-3.5">
                @forelse ($customer->installmentSchemes as $scheme)
                    <x-ui.card>
                        <div class="flex justify-between">
                            <span class="font-bold text-[13.5px]">₹{{ number_format($scheme->monthly_amount,2) }}/month</span>
                            <x-ui.badge :tone="$scheme->status === 'active' ? 'success' : 'neutral'">{{ strtoupper($scheme->status) }}</x-ui.badge>
                        </div>
                        <div class="text-[11.5px] text-ink_text-secondary mt-1">
                            {{ $scheme->months_paid }} month(s) paid · started {{ $scheme->start_date?->format('d M Y') }}
                        </div>
                        <div class="mt-2.5 border-t border-line-light pt-2.5">
                            @foreach ($scheme->payments as $payment)
                                <div class="flex justify-between text-xs py-1">
                                    <span>{{ $payment->paid_on?->format('d M Y') }}</span>
                                    <span>₹{{ number_format($payment->amount,2) }}</span>
                                </div>
                            @endforeach
                        </div>
                    </x-ui.card>
                @empty
                    <div class="text-ink_text-secondary text-[13px]">No installment schemes yet.</div>
                @endforelse
            </div>
        @endif

    </div>
</div>
