<div>
    <x-ui.page-header title="Enrol from a CSV" subtitle="Upload a sheet of members. It is checked row by row first, and nothing is saved until you confirm."
        :crumbs="[['label' => 'Installments', 'href' => route('installments.list')], ['label' => 'Enrol from a CSV']]">
        <x-slot:actions>
            <x-ui.button variant="secondary" icon="download" wire:click="template" target="template">Download the template</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    @if ($result)
        <x-ui.card class="max-w-[640px] mb-6" title="Done" icon="check-circle">
            <p class="text-[14px]"><strong>{{ $result['enrolled'] }}</strong> {{ \Illuminate\Support\Str::plural('member', $result['enrolled']) }} enrolled@if ($result['customers']), and <strong>{{ $result['customers'] }}</strong> new {{ \Illuminate\Support\Str::plural('customer', $result['customers']) }} added@endif.
                A welcome message for each is waiting in Messages.</p>
            @if ($result['problems'])<p class="text-[13px] text-ink_text-secondary mt-2">{{ $result['problems'] }} {{ \Illuminate\Support\Str::plural('row', $result['problems']) }} had a problem and {{ $result['problems'] === 1 ? 'was' : 'were' }} not enrolled.</p>@endif
            @foreach ($result['skipped'] as $s)<p class="text-[13px] text-warning mt-1">{{ $s }}</p>@endforeach
            <div class="mt-4 flex gap-2"><x-ui.button :href="route('installments.list')" icon="list">Scheme list</x-ui.button><x-ui.button variant="secondary" :href="route('installments.monthly-status')">Monthly status</x-ui.button></div>
        </x-ui.card>
    @endif

    <x-ui.card class="max-w-[640px] mb-6" title="1. Choose the file" icon="upload">
        <form wire:submit="parse" class="space-y-3">
            <input type="file" wire:model="file" accept=".csv,.txt,.xlsx" class="rj-input w-full">
            @error('file')<p class="rj-error"><x-ui.icon name="alert-triangle" :size="12" />{{ $message }}</p>@enderror
            <p class="rj-help">Columns: <span class="rj-code">name, phone, monthly_amount, total_months, months_already_paid, start_date, late_fee, pending_amount</span>. Only phone and monthly_amount are needed. Months default to 12, the start date to today. A phone that is not a customer yet becomes one, so give a name for those. Up to {{ \App\Livewire\Installments\SchemeImport::MAX_ROWS }} rows.</p>
            <x-ui.button type="submit" icon="check" target="parse,file">Check the file</x-ui.button>
        </form>
    </x-ui.card>

    @if ($parsed)
        @php $ready = collect($rows)->whereNull('error')->count(); $problems = count($rows) - $ready; @endphp
        <x-ui.card :padding="false" title="2. Check the rows" :subtitle="$ready . ' ready, ' . $problems . ' with a problem'" icon="list">
            <x-slot:actions>
                @if ($ready > 0)<x-ui.button icon="check" wire:click="commit" target="commit">Enrol {{ $ready }} {{ \Illuminate\Support\Str::plural('member', $ready) }}</x-ui.button>@endif
            </x-slot:actions>
            <x-ui.table :headers="['Row', 'Customer', 'Monthly', 'Months', 'Paid', 'Starts', 'Result']">
                @foreach ($rows as $r)
                    <tr wire:key="si-{{ $r['line'] }}" class="{{ $r['error'] ? 'bg-danger-bg/40' : '' }}">
                        <td class="tabular text-ink_text-muted">{{ $r['line'] }}</td>
                        <td><span class="font-semibold">{{ $r['name'] ?: '—' }}</span><span class="block text-[12px] text-ink_text-muted tabular">{{ $r['phone'] }}</span></td>
                        <td class="tabular">{{ $r['monthly_amount'] !== null ? '₹' . number_format($r['monthly_amount'], 2) : '—' }}</td>
                        <td class="tabular">{{ $r['total_months'] }}</td>
                        <td class="tabular">{{ $r['months_paid'] }}</td>
                        <td class="tabular text-[12.5px]">{{ \Illuminate\Support\Carbon::parse($r['start_date'])->format('j M Y') }}</td>
                        <td>
                            @if ($r['error'])<span class="text-[12.5px] text-danger">{{ $r['error'] }}</span>
                            @else<x-ui.badge tone="success" size="sm">{{ $r['customer_id'] ? 'Ready' : 'Ready, new customer' }}</x-ui.badge>@endif
                        </td>
                    </tr>
                @endforeach
            </x-ui.table>
        </x-ui.card>
    @endif
</div>
