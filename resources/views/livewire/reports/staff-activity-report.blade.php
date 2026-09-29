<div>
    <x-ui.page-header title="Staff-wise Activity Report" subtitle="Movements logged and sales created, per staff member." />

    <div class="grid grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
        <x-ui.stat-card icon="repeat" label="Movements logged" :value="number_format($stats['movements'])" />
        <x-ui.stat-card icon="receipt" label="Sales created" :value="number_format($stats['sales'])" />
        <x-ui.stat-card icon="coins" label="Sales value" :value="'₹'.number_format($stats['salesValue'], 2)" />
    </div>

    <div class="flex flex-wrap items-end gap-3 mb-4">
        <x-ui.field label="Staff" for="sa-staff" class="w-[220px]">
            <select id="sa-staff" wire:model.live="staffId" class="rj-select w-full">
                <option value="">All staff</option>
                @foreach ($staffOptions as $s)
                    <option value="{{ $s->id }}">{{ $s->name }}</option>
                @endforeach
            </select>
        </x-ui.field>
        <x-ui.field label="From" for="sa-from" class="w-[170px]">
            <input id="sa-from" type="date" wire:model.live="fromDate" class="rj-input w-full">
        </x-ui.field>
        <x-ui.field label="To" for="sa-to" class="w-[170px]">
            <input id="sa-to" type="date" wire:model.live="toDate" class="rj-input w-full">
        </x-ui.field>
    </div>

    <x-ui.card :padding="false" class="overflow-hidden">
        <x-ui.table :headers="['Staff', 'Movements logged', 'Sales created', 'Sales value']">
            @forelse ($rows as $row)
                <tr class="h-[56px] border-b border-line-light">
                    <td class="px-4 text-ink_text-primary font-semibold">{{ $row['user']->name }}</td>
                    <td class="px-4 tabular text-ink_text-primary">{{ $row['movements'] }}</td>
                    <td class="px-4 tabular text-ink_text-primary">{{ $row['sales'] }}</td>
                    <td class="px-4 tabular text-ink_text-primary">₹{{ number_format($row['sales_amount'], 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4">
                        <x-ui.empty-state icon="users" title="No staff found" message="Nothing to show for this range." compact />
                    </td>
                </tr>
            @endforelse
        </x-ui.table>
    </x-ui.card>
</div>
