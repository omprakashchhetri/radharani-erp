<div>
    <x-ui.page-header :title="'Invoice '.$sale->invoice_number" subtitle="Read-only — items, GST breakdown, and payment as recorded on this sale."
        :crumbs="[['label' => 'Sales History', 'href' => route('sales.history')], ['label' => $sale->invoice_number]]">
        <x-slot:actions>
            <x-ui.button type="button" variant="secondary" icon="printer" onclick="window.print()">Print</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    @unless ($sale->confirmed_by_accountant)
    <div class="bg-warning-bg text-warning rounded-control px-3.5 py-2.5 mb-5 max-w-[720px] text-xs flex items-center gap-2">
        <x-ui.icon name="alert-triangle" :size="13" class="shrink-0" />
        This sale is still reserved, not yet verified by admin — treat this as a preview, not a final invoice.
    </div>
    @endunless

    <x-ui.card class="max-w-[720px]">
        <div class="text-center mb-6">
            <x-ui.logo :size="64" class="w-16 h-16 mx-auto mb-2" />
            <div class="font-display text-[24px] font-semibold text-ink_text-primary">Radharani Jewellery Works</div>
            <div class="text-[11px] text-ink_text-secondary uppercase tracking-wide">Tax Invoice</div>
        </div>

        <div class="flex justify-between text-[12.5px] mb-5">
            <div>
                <div class="text-ink_text-secondary text-[11px]">Billed to</div>
                <div class="font-bold text-ink_text-primary">{{ $sale->customer->name }}</div>
                <div class="text-ink_text-primary">{{ $sale->customer->phone }}</div>
            </div>
            <div class="text-right">
                <div class="text-ink_text-secondary text-[11px]">Invoice</div>
                <div class="font-bold rj-code text-ink_text-primary">{{ $sale->invoice_number }}</div>
                <div class="text-ink_text-primary">{{ \Carbon\Carbon::parse($sale->created_at)->format('d M Y') }}</div>
            </div>
        </div>

        <div class="rounded-xl border border-line-light overflow-hidden">
            <x-ui.table :headers="['Item', 'Status', 'Price']">
                @foreach ($sale->items as $item)
                <tr class="h-[56px] border-b border-line-light">
                    <td class="px-4 text-ink_text-primary">{{ $item->huid_code ?: $item->internal_code }} — {{ $item->category }}</td>
                    <td class="px-4">
                        <x-ui.badge :tone="$item->status === 'sold' ? 'success' : 'warning'" size="sm" dot>
                            {{ $item->status === 'sold' ? 'Sold' : 'Reserved — pending verification' }}
                        </x-ui.badge>
                    </td>
                    <td class="px-4 text-right tabular text-ink_text-primary">₹{{ number_format($item->pivot->price_at_sale, 2) }}</td>
                </tr>
                @endforeach
            </x-ui.table>
        </div>

        <dl class="rj-dl mt-4">
            <div><dt>CGST</dt><dd class="tabular">₹{{ number_format($sale->cgst, 2) }}</dd></div>
            <div><dt>SGST</dt><dd class="tabular">₹{{ number_format($sale->sgst, 2) }}</dd></div>
            @if ($sale->discount > 0)
            <div><dt class="text-success">Discount</dt><dd class="tabular text-success">-₹{{ number_format($sale->discount, 2) }}</dd></div>
            @endif
            <div class="col-span-2 pt-2 mt-1 border-t border-line-light">
                <dt class="font-bold text-ink_text-primary">Total</dt>
                <dd class="font-display text-[22px] font-semibold tabular text-ink_text-primary">₹{{ number_format($sale->total, 2) }}</dd>
            </div>
        </dl>

        <div class="mt-4 text-[11.5px] text-ink_text-secondary">
            Payment: {{ collect($sale->payment_modes)->map(fn($p) => ucfirst($p['mode']).' ₹'.number_format($p['amount'], 2))->join(', ') ?: '—' }}
        </div>
    </x-ui.card>
</div>
