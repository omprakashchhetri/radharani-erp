<div>
    <x-ui.page-header title="Referral Overview" subtitle="Who's referring customers, and whether the bonus has actually been earned." />

    <div class="grid grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
        <x-ui.stat-card icon="users" label="Active referrers" :value="number_format($stats['referrers'])" />
        <x-ui.stat-card icon="gift" label="Total referrals" :value="number_format($stats['totalReferrals'])" />
        <x-ui.stat-card icon="check-circle" label="Bonus earned" :value="number_format($stats['bonusEarned'])" />
        <x-ui.stat-card icon="clock" label="Pending first sale" :value="number_format($stats['pending'])" />
    </div>

    <x-ui.search-input wire:model.live.debounce.300ms="search" placeholder="Search referrer name" class="w-full sm:w-[280px] mb-5" />

    <div class="flex flex-col gap-4">
        @forelse ($referrers as $referrer)
            <x-ui.card :padding="false">
                <div class="flex justify-between items-center px-5 py-3.5 border-b border-line-light">
                    <div>
                        <span class="font-bold text-[13.5px] text-ink_text-primary">{{ $referrer->name }}</span>
                        <span class="rj-code text-[11.5px] text-ink_text-secondary ml-2">{{ $referrer->referral_code }}</span>
                    </div>
                    <x-ui.badge tone="success">{{ $referrer->referrals_count }} referred</x-ui.badge>
                </div>

                <x-ui.table :headers="['Referred customer', 'Confirmed purchases', 'Bonus']">
                    @foreach ($referrer->referrals as $referred)
                        <tr class="h-[52px] border-b border-line-light last:border-0">
                            <td class="px-4 text-ink_text-primary">{{ $referred->name }}</td>
                            <td class="px-4 tabular text-ink_text-secondary">{{ $referred->confirmed_sales_count }}</td>
                            <td class="px-4">
                                <x-ui.badge :tone="$referred->confirmed_sales_count > 0 ? 'success' : 'warning'" size="sm" dot>
                                    {{ $referred->confirmed_sales_count > 0 ? 'Earned' : 'Pending first sale' }}
                                </x-ui.badge>
                            </td>
                        </tr>
                    @endforeach
                </x-ui.table>
            </x-ui.card>
        @empty
            <x-ui.empty-state icon="gift" title="No referrals recorded yet" message="Customers who refer others will show up here once they have a referral code." />
        @endforelse
    </div>

    <div class="mt-4">{{ $referrers->links() }}</div>
</div>
