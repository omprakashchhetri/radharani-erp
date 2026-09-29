<div>
    <x-ui.page-header title="Location Report" subtitle="Stock quantity and live-calculated value by current location." />

    <div class="grid grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
        <x-ui.stat-card icon="map-pin" label="Locations" :value="number_format($stats['locations'])" />
        <x-ui.stat-card icon="gem" label="Items" :value="number_format($stats['items'])" />
        <x-ui.stat-card icon="scale" label="Weight" :value="number_format($stats['weight'], 3).' g'" />
        <x-ui.stat-card icon="coins" label="Value" :value="'₹'.number_format($stats['value'], 2)" />
    </div>

    <x-ui.card :padding="false" class="overflow-hidden">
        <x-ui.table :headers="['Location', 'Items', 'Weight', 'Value']">
            @forelse ($rows as $row)
                <tr class="h-[56px] border-b border-line-light">
                    <td class="px-4"><x-ui.badge tone="neutral">{{ $row['location'] }}</x-ui.badge></td>
                    <td class="px-4 tabular text-ink_text-primary">{{ $row['qty'] }}</td>
                    <td class="px-4 tabular text-ink_text-primary">{{ number_format($row['weight'], 3) }}g</td>
                    <td class="px-4 tabular text-ink_text-primary">₹{{ number_format($row['value'], 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4">
                        <x-ui.empty-state icon="archive" title="No items in stock" message="Location breakdown will show up here once items exist." compact />
                    </td>
                </tr>
            @endforelse
        </x-ui.table>
    </x-ui.card>
</div>
