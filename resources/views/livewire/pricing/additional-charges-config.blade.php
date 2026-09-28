<div>
    <x-ui.page-header title="Additional Charges" subtitle="Named preset charges staff can pick from when building a sale."
        :crumbs="[['label' => 'Pricing & Rates', 'href' => route('pricing.rates')], ['label' => 'Additional Charges']]" />

    <div class="grid grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
        <x-ui.stat-card icon="receipt" label="Presets" :value="number_format($stats['count'])" />
        <x-ui.stat-card icon="coins" label="Combined value" :value="'₹'.number_format($stats['total'], 2)" />
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-[minmax(0,1fr)_360px] gap-6 items-start">
        <x-ui.card title="New charge" subtitle="A short name and a default value staff can adjust per sale." icon="plus">
            <form wire:submit="add">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <x-ui.field label="Name" for="ac-name" error="newName">
                        <input id="ac-name" type="text" wire:model="newName" placeholder="e.g. Sakha" class="rj-input @error('newName') is-invalid @enderror">
                    </x-ui.field>
                    <x-ui.field label="Value (₹)" for="ac-value" error="newValue">
                        <div class="rj-input-icon">
                            <x-ui.icon name="coins" :size="16" />
                            <input id="ac-value" type="number" step="0.01" wire:model="newValue" class="rj-input tabular @error('newValue') is-invalid @enderror">
                        </div>
                    </x-ui.field>
                </div>
                <x-ui.button type="submit" variant="secondary" target="add" icon="plus" class="w-full mt-5">Add charge</x-ui.button>
            </form>

            <div class="mt-8 pt-6 border-t border-line-light">
                <div class="flex items-end justify-between gap-4 mb-3.5">
                    <div>
                        <h2 class="font-display text-[20px] font-semibold leading-tight">All presets</h2>
                        <p class="text-[12.5px] text-ink_text-secondary">Every named charge staff can add to a sale.</p>
                    </div>
                </div>

                <div class="rounded-xl border border-line-light overflow-hidden">
                    <x-ui.table :headers="['Name', 'Value (₹)', '']">
                        @forelse ($charges as $c)
                        <tr wire:key="charge-{{ $c->id }}" class="h-[56px] border-b border-line-light">
                            <td class="px-4 text-ink_text-primary font-semibold">{{ $c->name }}</td>
                            <td class="px-4">
                                <div class="rj-input-icon max-w-[140px]">
                                    <x-ui.icon name="coins" :size="15" />
                                    <input type="number" step="0.01" value="{{ $c->value }}" wire:change="updateValue({{ $c->id }}, $event.target.value)" class="rj-input rj-input-sm tabular">
                                </div>
                            </td>
                            <td class="px-4">
                                <div class="flex items-center justify-end">
                                    <x-ui.button variant="ghost" size="icon-sm" icon="x" title="Remove" aria-label="Remove {{ $c->name }}"
                                        x-on:click="$dispatch('rj-confirm', { title: 'Remove this charge?', message: 'Staff will no longer see &quot;{{ $c->name }}&quot; as a preset when billing.', confirm: 'Remove', tone: 'danger', action: () => $wire.remove({{ $c->id }}) })" />
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="3">
                                <x-ui.empty-state icon="receipt" title="No charges yet" message="Add a preset charge using the form above." compact />
                            </td>
                        </tr>
                        @endforelse
                    </x-ui.table>
                </div>
            </div>
        </x-ui.card>

        <aside class="space-y-6 xl:sticky xl:top-24">
            <x-ui.card title="Most used" icon="bar-chart">
                @php $top = $charges->sortByDesc('value')->take(5); @endphp
                @if ($top->isEmpty())
                    <p class="text-[12.5px] text-ink_text-secondary">Presets you add will be ranked here by value.</p>
                @else
                    <dl class="rj-dl">
                        @foreach ($top as $c)
                            <div>
                                <dt>{{ $c->name }}</dt>
                                <dd class="tabular">₹{{ number_format($c->value, 2) }}</dd>
                            </div>
                        @endforeach
                    </dl>
                @endif
            </x-ui.card>

            <div class="rounded-card bg-surface-sunken ring-1 ring-inset ring-line-light p-5">
                <div class="flex items-center gap-2 text-[12.5px] font-semibold text-ink_text-secondary mb-2">
                    <x-ui.icon name="info" :size="14" /> Where these show up
                </div>
                <p class="text-[12.5px] text-ink_text-secondary leading-relaxed">
                    Presets here are a reusable named list — each sale still just stores its own <code class="rj-code text-[11px]">name</code>/<code class="rj-code text-[11px]">value</code> pairs in <code class="rj-code text-[11px]">sales.additional_charges</code>, so editing or removing a preset never changes a past sale.
                </p>
            </div>
        </aside>
    </div>
</div>
