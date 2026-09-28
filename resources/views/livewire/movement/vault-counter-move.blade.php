<div>
    @php $toCounter = $direction === 'to_counter'; @endphp
    <x-ui.page-header title="Vault ↔ Counter" subtitle="Morning: scan what goes out to the counter. Evening: scan what is left and send it back. Confirm once when the tray is done."
        :crumbs="[['label' => 'Movements'], ['label' => 'Vault ↔ Counter']]">
        <x-slot:meta>
            <x-ui.badge tone="gold">{{ now()->format('l, j M') }}</x-ui.badge>
        </x-slot:meta>
    </x-ui.page-header>

    {{-- The two big buttons --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
        @foreach ([
            'to_counter' => ['arrow-right', 'Send to Counter', 'Morning. Stock leaving the vault for display.', $stats['sent'] . ' sent today'],
            'to_vault' => ['archive', 'Return to Vault', 'Evening. Unsold stock going back in.', $stats['returned'] . ' returned today'],
        ] as $key => [$icon, $title, $text, $count])
            @php $active = $direction === $key; @endphp
            <button type="button"
                @if (! $active && count($tray))
                    x-on:click="$dispatch('rj-confirm', { title: 'Switch and empty the tray?', message: 'The {{ count($tray) }} scanned {{ \Illuminate\Support\Str::plural('entry', count($tray)) }} have not been confirmed yet.', confirm: 'Switch', tone: 'danger', action: () => $wire.setDirection('{{ $key }}') })"
                @else
                    wire:click="setDirection('{{ $key }}')"
                @endif
                @class([
                    'press group relative text-left rounded-card p-5 sm:p-6 flex items-center gap-5 overflow-hidden transition-[border-color,box-shadow,background-color] duration-200',
                    'bg-ink ink-grain text-white ring-1 ring-black/40 shadow-raised' => $active,
                    'bg-white border border-line-light shadow-card hover:border-gold-soft hover:shadow-raised' => ! $active,
                ])>
                <span @class([
                    'w-14 h-14 shrink-0 rounded-2xl flex items-center justify-center',
                    'gold-sheen text-ink shadow-gold' => $active,
                    'bg-gold-tint text-gold-dark ring-1 ring-inset ring-gold-soft/70' => ! $active,
                ])>
                    <x-ui.icon :name="$icon" :size="24" />
                </span>
                <span class="flex-1 min-w-0">
                    <span @class(['block font-display text-[28px] leading-tight font-semibold', 'text-gold-light' => $active, 'text-ink_text-primary' => ! $active])>{{ $title }}</span>
                    <span @class(['block text-[13px] mt-0.5', 'text-ink-fg' => $active, 'text-ink_text-secondary' => ! $active])>{{ $text }}</span>
                </span>
                <span @class(['hidden md:block text-[12px] font-semibold tabular whitespace-nowrap', 'text-gold-light/80' => $active, 'text-ink_text-muted' => ! $active])>{{ $count }}</span>
                @if ($active)
                    <span class="absolute right-4 top-4 w-2 h-2 rounded-full bg-gold-light shadow-[0_0_0_4px_rgba(212,175,90,.18)]"></span>
                @endif
            </button>
        @endforeach
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-[minmax(0,1fr)_400px] gap-6 items-start"
         x-data="{ focusScan() { if (window.matchMedia('(pointer: fine)').matches) this.$nextTick(() => this.$refs.scan && this.$refs.scan.focus()) } }"
         x-init="focusScan()" x-on:scan-ready.window="focusScan()">
        <div class="space-y-6 min-w-0">
            {{-- Scanner --}}
            <x-ui.card :title="$toCounter ? 'Scan what is going to the counter' : 'Scan what is going back to the vault'"
                subtitle="Pieces, packets or whole boxes. HUID, internal code or QR sticker." icon="scan">
                <x-ui.scan-button target="#vault-scan" submit="form" continuous variant="button"
                    :title="$toCounter ? 'Scan for the counter' : 'Scan back to the vault'" label="Scan with camera"
                    class="w-full !h-14 !text-[15px] mb-3 sm:hidden" />
                <form x-on:submit.prevent="const v = $refs.scan.value; $refs.scan.value = ''; if (v.trim()) $wire.scan(v)" class="flex gap-2.5">
                    <div class="relative flex-1 min-w-0">
                        <x-ui.icon name="scan" :size="19" class="absolute left-4 top-1/2 -translate-y-1/2 text-gold pointer-events-none" />
                        <input id="vault-scan" x-ref="scan" type="text" autocomplete="off" aria-label="Scan code"
                            class="rj-input h-14 pl-12 pr-11 sm:pr-28 text-[15px] sm:text-[17px] font-mono tracking-wider" placeholder="Waiting for scan">
                        <span class="hidden sm:inline absolute right-3.5 top-1/2 -translate-y-1/2 text-[11.5px] text-ink_text-muted" wire:loading.remove wire:target="scan">Press Enter</span>
                        <span class="absolute right-4 top-1/2 -translate-y-1/2 text-gold" wire:loading wire:target="scan"><x-ui.icon name="loader" :size="17" class="animate-spin" /></span>
                    </div>
                    <x-ui.scan-button target="#vault-scan" submit="form" continuous variant="button"
                        :title="$toCounter ? 'Scan for the counter' : 'Scan back to the vault'" label="Camera"
                        class="max-sm:!hidden !h-14 !px-5" />
                </form>

                @if ($feedback)
                    <div wire:key="fb-{{ md5(json_encode($feedback) . count($tray)) }}" @class([
                        'mt-3 flex items-center gap-3 px-3.5 py-2.5 rounded-control text-[13px] animate-rise-in',
                        'bg-success-bg text-success' => $feedback['tone'] === 'success',
                        'bg-danger-bg text-danger' => $feedback['tone'] === 'error',
                        'bg-warning-bg text-warning' => $feedback['tone'] === 'warning',
                        'bg-info-bg text-info' => $feedback['tone'] === 'info',
                    ])>
                        <x-ui.icon :name="['success' => 'check-circle', 'error' => 'x-circle', 'warning' => 'alert-triangle', 'info' => 'info'][$feedback['tone']]" :size="16" class="shrink-0" />
                        <span class="rj-code">{{ $feedback['code'] }}</span>
                        <span class="text-ink_text-secondary">{{ $feedback['message'] }}</span>
                    </div>
                @else
                    <p class="rj-help">A scanner types the code and presses Enter for you, so you can keep scanning without touching the screen.</p>
                @endif
            </x-ui.card>

            {{-- Tray --}}
            <x-ui.card :padding="false" :title="$toCounter ? 'Tray for the counter' : 'Tray for the vault'"
                :subtitle="count($tray) ? count($tray) . ' ' . \Illuminate\Support\Str::plural('entry', count($tray)) . ($trayWeight ? ' · ' . number_format($trayWeight, 3) . ' g in loose pieces' : '') : 'Nothing is recorded until you confirm'"
                icon="{{ $toCounter ? 'arrow-right' : 'archive' }}">
                <x-slot:actions>
                    @if (count($tray))
                        <x-ui.button variant="ghost" size="sm" wire:click="clearTray">Empty tray</x-ui.button>
                    @endif
                </x-slot:actions>

                @if (count($tray))
                    <ul class="divide-y divide-line-light max-h-[420px] overflow-y-auto">
                        @foreach ($tray as $i => $t)
                            <li wire:key="tray-{{ $t['type'] }}-{{ $t['id'] }}" class="flex items-center gap-3.5 px-5 py-3 {{ $i === 0 ? 'animate-rise-in' : '' }}">
                                <span class="w-8 h-8 shrink-0 rounded-lg bg-surface-muted text-ink_text-secondary flex items-center justify-center">
                                    <x-ui.icon :name="['item' => 'gem', 'packet' => 'package', 'box' => 'archive'][$t['type']]" :size="15" />
                                </span>
                                <div class="flex-1 min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="rj-code text-ink_text-primary">{{ $t['code'] }}</span>
                                        @if ($t['warning'])<x-ui.badge tone="warning" size="sm">{{ $t['warning'] }}</x-ui.badge>@endif
                                    </div>
                                    <div class="text-[12.5px] text-ink_text-muted truncate">{{ $t['detail'] }}</div>
                                </div>
                                <button type="button" wire:click="removeFromTray({{ $i }})" aria-label="Remove {{ $t['code'] }}"
                                    class="w-8 h-8 shrink-0 rounded-lg text-ink_text-muted hover:text-danger hover:bg-danger-bg flex items-center justify-center">
                                    <x-ui.icon name="x" :size="15" />
                                </button>
                            </li>
                        @endforeach
                    </ul>
                    <div class="flex flex-wrap items-center gap-3 px-5 py-4 bg-surface-sunken border-t border-line-light rounded-b-card">
                        @if (! $toCounter && $missingCount)
                            <span class="flex items-center gap-1.5 text-[12.5px] font-semibold text-warning">
                                <x-ui.icon name="alert-triangle" :size="14" /> {{ $missingCount }} still on the counter not scanned
                            </span>
                        @endif
                        @php
                            $confirmJs = ! $toCounter && $missingCount
                                ? "\$dispatch('rj-confirm', { title: 'Return with {$missingCount} missing?', message: '{$missingCount} still recorded on the counter were not scanned back. They will stay listed as on the counter.', confirm: 'Return anyway', action: () => \$wire.confirmMove() })"
                                : '$wire.confirmMove()';
                        @endphp
                        <x-ui.button size="lg" class="ml-auto" :icon="$toCounter ? 'arrow-right' : 'archive'" target="confirmMove" x-on:click="{{ $confirmJs }}">
                            {{ $toCounter ? 'Send ' . count($tray) . ' to Counter' : 'Return ' . count($tray) . ' to Vault' }}
                        </x-ui.button>
                    </div>
                @else
                    <x-ui.empty-state icon="scan" title="The tray is empty"
                        :message="$toCounter ? 'Scan each piece, packet or box as it leaves the vault.' : 'Scan everything left unsold. The list on the right shows what is still expected back.'" compact />
                @endif
            </x-ui.card>
        </div>

        {{-- Right: what is on the counter now --}}
        <x-ui.card :padding="false" :title="$toCounter ? 'On the counter now' : 'Still on the counter'"
            :subtitle="$expectedCount . ' ' . \Illuminate\Support\Str::plural('entry', $expectedCount) . ' expected back tonight'" icon="grid" class="xl:sticky xl:top-24">
            @if (! $toCounter && $expectedCount)
                <div class="px-5 py-3.5 border-b border-line-light">
                    @php $done = $expectedCount - $missingCount; @endphp
                    <div class="flex items-baseline justify-between text-[12.5px] mb-2">
                        <span class="text-ink_text-secondary">Scanned back</span>
                        <span class="font-display text-[22px] leading-none font-semibold tabular">{{ $done }}<span class="text-ink_text-muted text-[15px]"> / {{ $expectedCount }}</span></span>
                    </div>
                    <div class="h-1.5 rounded-full bg-surface-muted overflow-hidden">
                        <div class="h-full gold-sheen transition-[width] duration-300" style="width: {{ $expectedCount ? round($done / $expectedCount * 100) : 0 }}%"></div>
                    </div>
                </div>
            @endif
            <ul class="divide-y divide-line-light max-h-[560px] overflow-y-auto">
                @forelse ($counterRows->sortBy('scanned') as $r)
                    <li class="flex items-center gap-3 px-5 py-3 {{ $r['sold'] ? 'opacity-60' : '' }}">
                        @if (! $toCounter && ! $r['sold'])
                            <span @class([
                                'w-6 h-6 shrink-0 rounded-full flex items-center justify-center',
                                'bg-success-bg text-success' => $r['scanned'],
                                'ring-1 ring-inset ring-line-strong text-transparent' => ! $r['scanned'],
                            ])><x-ui.icon name="check" :size="12" /></span>
                        @else
                            <x-ui.icon :name="['item' => 'gem', 'packet' => 'package', 'box' => 'archive'][$r['type']]" :size="15" class="text-ink_text-muted shrink-0" />
                        @endif
                        <div class="flex-1 min-w-0">
                            @if ($r['url'])
                                <a href="{{ $r['url'] }}" class="rj-code text-ink_text-primary hover:text-gold-dark">{{ $r['code'] }}</a>
                            @else
                                <span class="rj-code">{{ $r['code'] }}</span>
                            @endif
                            <div class="text-[12px] text-ink_text-muted truncate">{{ $r['detail'] }}</div>
                        </div>
                        @if ($r['sold'])
                            <x-ui.badge size="sm">Sold</x-ui.badge>
                        @else
                            <span class="text-[11.5px] text-ink_text-muted text-right leading-tight whitespace-nowrap">
                                {{ $r['since']->isToday() ? $r['since']->format('g:i a') : $r['since']->format('j M') }}
                                @unless ($r['since']->isToday())<br><span class="text-warning font-semibold">not today</span>@endunless
                            </span>
                        @endif
                    </li>
                @empty
                    <li><x-ui.empty-state icon="archive" title="Everything is in the vault" message="Nothing is recorded as on the counter right now." compact /></li>
                @endforelse
            </ul>
        </x-ui.card>
    </div>

    {{-- Today's log --}}
    <x-ui.card :padding="false" title="Today's log" :subtitle="$today->count() . ' ' . \Illuminate\Support\Str::plural('movement', $today->count()) . ' between the vault and the counter'" icon="history" class="mt-6">
        <x-slot:actions>
            @if (\Illuminate\Support\Facades\Route::has('movements.log'))
                <x-ui.button variant="ghost" size="sm" iconRight="arrow-right" :href="route('movements.log', ['type' => 'vault'])">Full log</x-ui.button>
            @endif
        </x-slot:actions>
        @if ($today->isEmpty())
            <x-ui.empty-state icon="clock" title="No movements yet today" message="Confirmed trays show up here with who moved what, and when." compact />
        @else
            <div class="overflow-x-auto">
                <x-ui.table :headers="['Time', 'Direction', 'What', 'By']">
                    @foreach ($today as $row)
                        <tr wire:key="today-{{ $row['movement']->id }}">
                            <td class="tabular whitespace-nowrap text-ink_text-secondary">{{ $row['movement']->created_at->format('g:i a') }}</td>
                            <td>
                                @if ($row['movement']->movement_type === 'vault_out')
                                    <x-ui.badge tone="gold" size="sm"><x-ui.icon name="arrow-right" :size="11" /> To counter</x-ui.badge>
                                @else
                                    <x-ui.badge tone="dark" size="sm"><x-ui.icon name="archive" :size="11" /> To vault</x-ui.badge>
                                @endif
                            </td>
                            <td>
                                @if ($row['url'])
                                    <a href="{{ $row['url'] }}" class="rj-code text-ink_text-primary hover:text-gold-dark">{{ $row['code'] }}</a>
                                @else
                                    <span class="rj-code">{{ $row['code'] }}</span>
                                @endif
                                <span class="text-[12.5px] text-ink_text-muted ml-2">{{ $row['detail'] }}</span>
                            </td>
                            <td class="whitespace-nowrap">{{ $row['movement']->user->name ?? '-' }}</td>
                        </tr>
                    @endforeach
                </x-ui.table>
            </div>
        @endif
    </x-ui.card>
</div>
