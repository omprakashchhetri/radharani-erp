@php
use App\Livewire\Dashboard;
use Illuminate\Support\Facades\Route;

$g = fn ($w) => number_format((float) $w, (float) $w >= 100 ? 1 : 2) . ' g';
$metalNames = ['gold' => 'Gold', 'silver' => 'Silver', 'platinum' => 'Platinum', 'titanium' => 'Titanium'];
$toneIcon = [
    'danger' => 'bg-danger-bg text-danger ring-danger/15',
    'warning' => 'bg-warning-bg text-warning ring-warning/15',
    'info' => 'bg-info-bg text-info ring-info/15',
    'neutral' => 'bg-surface-muted text-ink_text-secondary ring-line',
];
$urgent = collect($attention)->where('tone', 'danger')->count();
@endphp
<div class="space-y-6">
    <script>
// Sales trend chart: one gold series, crosshair + tooltip, arrow-key readout.
// Plain SVG paths and HTML labels (no chart library); the sr-only table under the chart is its text view.
window.rjTrendChart = window.rjTrendChart || function (points) {
    const compact = (v) => {
        const a = Math.abs(v);
        const trim = (n, d) => n.toFixed(d).replace(/\.?0+$/, '');
        if (a >= 1e7) return trim(v / 1e7, 2) + ' Cr';
        if (a >= 1e5) return trim(v / 1e5, 2) + ' L';
        if (a >= 1e3) return trim(v / 1e3, 1) + 'K';
        return String(Math.round(v));
    };
    const niceStep = (raw) => {
        const p = Math.pow(10, Math.floor(Math.log10(raw)));
        const f = raw / p;
        return (f <= 1 ? 1 : f <= 2 ? 2 : f <= 2.5 ? 2.5 : f <= 5 ? 5 : 10) * p;
    };

    return {
        points,
        w: 600,
        h: 250,
        pad: { l: 56, r: 14, t: 12, b: 26 },
        hover: null,
        init() {
            const box = this.$refs.box;
            const measure = () => { if (box.isConnected) this.w = box.clientWidth || 600; };
            measure();
            this.ro = new ResizeObserver(measure);
            this.ro.observe(box);
        },
        // Changing the range swaps this chart for a fresh one; stop watching the old box.
        destroy() { this.ro && this.ro.disconnect(); },
        get empty() { return !this.points.some(p => p.total > 0); },
        get top() {
            const max = Math.max(0, ...this.points.map(p => p.total));
            if (max <= 0) return 1000;
            return Math.ceil(max / niceStep(max / 4)) * niceStep(max / 4);
        },
        get ticks() {
            const step = this.top / 4;
            return [0, 1, 2, 3, 4].map(i => i * step);
        },
        x(i) {
            const n = this.points.length;
            return this.pad.l + (n <= 1 ? 0 : (i / (n - 1)) * (this.w - this.pad.l - this.pad.r));
        },
        y(v) { return this.pad.t + (1 - v / this.top) * (this.h - this.pad.t - this.pad.b); },
        get linePath() {
            return this.points.map((p, i) => (i ? 'L' : 'M') + this.x(i).toFixed(1) + ' ' + this.y(p.total).toFixed(1)).join(' ');
        },
        get areaPath() {
            if (!this.points.length) return '';
            const base = this.y(0).toFixed(1);
            return this.linePath + ` L${this.x(this.points.length - 1).toFixed(1)} ${base} L${this.x(0).toFixed(1)} ${base} Z`;
        },
        get gridPath() {
            return this.ticks.map(t => `M${this.pad.l} ${Math.round(this.y(t)) + 0.5} H${this.w - this.pad.r}`).join(' ');
        },
        get xLabels() {
            const n = this.points.length;
            if (!n) return [];
            const want = Math.max(2, Math.min(n, Math.floor((this.w - this.pad.l) / 80)));
            const step = Math.max(1, Math.ceil((n - 1) / (want - 1)));
            const idx = [];
            for (let i = 0; i < n; i += step) idx.push(i);
            if (n - 1 - idx[idx.length - 1] < step / 2 && idx.length > 1) idx.pop();
            if (idx[idx.length - 1] !== n - 1) idx.push(n - 1);
            return idx;
        },
        get cur() { return this.hover === null ? null : this.points[this.hover]; },
        get hx() { return this.hover === null ? 0 : this.x(this.hover); },
        get hy() { return this.hover === null ? 0 : this.y(this.cur.total); },
        get tipStyle() {
            const left = this.hx > this.w * 0.6 ? this.hx - 184 : this.hx + 14;
            // An object, not a string: a string :style would overwrite x-show's display:none.
            return { left: Math.max(0, left) + 'px', top: Math.max(0, Math.min(this.hy - 36, this.h - 104)) + 'px' };
        },
        pointer(e) {
            const r = this.$refs.box.getBoundingClientRect();
            const px = e.clientX - r.left;
            const n = this.points.length;
            if (!n) return;
            const t = (px - this.pad.l) / (this.w - this.pad.l - this.pad.r);
            this.hover = Math.max(0, Math.min(n - 1, Math.round(t * (n - 1))));
        },
        step(d) {
            const n = this.points.length;
            this.hover = Math.max(0, Math.min(n - 1, (this.hover ?? n - 1) + d));
        },
        money(v) { return '₹' + compact(v); },
        full(v) { return Math.round(v).toLocaleString('en-IN'); },
    };
};
    </script>

    {{-- Hero: greeting, what needs doing, today's rates --}}
    <section class="relative overflow-hidden rounded-[18px] bg-ink ink-grain text-ink-fg shadow-raised animate-rise-in">
        <div aria-hidden="true" class="pointer-events-none absolute -right-24 -top-24 w-[380px] h-[380px] rounded-full border border-gold/10"></div>
        <div aria-hidden="true" class="pointer-events-none absolute -right-10 -top-10 w-[240px] h-[240px] rounded-full border border-gold/10"></div>

        <div class="relative flex flex-col xl:flex-row xl:items-end justify-between gap-6 p-6 sm:p-7 lg:p-8">
            <div class="min-w-0">
                <div class="text-[11px] font-semibold tracking-[0.2em] uppercase text-gold-light/80">{{ now()->format('l, j F Y') }}</div>
                <h1 class="font-display text-[34px] sm:text-[40px] leading-[1.05] font-semibold text-white mt-2">{{ $greeting }}</h1>
                <p class="text-[13.5px] text-ink-fg mt-2 max-w-[60ch]">
                    @if (count($attention) === 0)
                        Everything is up to date. Nothing is waiting on you right now.
                    @else
                        {{ count($attention) }} {{ count($attention) === 1 ? 'thing needs' : 'things need' }} your attention{!! $urgent ? ', <span class="text-[#F2A0A0]">' . $urgent . ' urgent</span>' : '' !!}.
                        <a href="#attention" class="text-gold-light hover:text-white underline decoration-gold/40 underline-offset-4">See the list</a>
                    @endif
                </p>
            </div>

            @if ($rates->isNotEmpty())
                <div class="shrink-0">
                    <div class="flex items-center justify-between gap-4 mb-2.5">
                        <span class="text-[11px] font-semibold tracking-[0.16em] uppercase text-ink-dim">Today's rates · per gram</span>
                        @if ($ratesStale)
                            @if ($canRates)
                                <a href="{{ route('pricing.rates') }}" class="inline-flex items-center gap-1.5 h-6 px-2.5 rounded-full bg-warning/20 text-gold-light text-[11px] font-bold hover:bg-warning/30">
                                    <x-ui.icon name="alert-triangle" :size="12" /> Not updated today
                                </a>
                            @else
                                <span class="inline-flex items-center gap-1.5 h-6 px-2.5 rounded-full bg-warning/20 text-gold-light text-[11px] font-bold">
                                    <x-ui.icon name="alert-triangle" :size="12" /> Not updated today
                                </span>
                            @endif
                        @else
                            <span class="text-[11px] text-ink-dim">Set {{ $rates->first()['at']->format('g:i a') }}</span>
                        @endif
                    </div>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-px rounded-xl overflow-hidden bg-ink-line/80 ring-1 ring-ink-line">
                        @foreach ($rates as $r)
                            <div class="bg-ink-soft/95 px-4 py-3 min-w-[132px]">
                                <div class="flex items-center gap-2 text-[12px] text-ink-dim">
                                    <x-movement.metal-dot :metal="$r['metal']" class="!ring-ink-soft" /> {{ $metalNames[$r['metal']] }}
                                </div>
                                <div class="font-display text-[24px] leading-tight font-semibold text-white mt-1">
                                    ₹{{ number_format($r['rate'], $r['rate'] < 1000 ? 2 : 0) }}
                                </div>
                                <div class="text-[11.5px] font-semibold mt-0.5 tabular">
                                    @if ($r['change'] === null || abs($r['change']) < 0.005)
                                        <span class="text-ink-dim">No change</span>
                                    @elseif ($r['change'] > 0)
                                        <span class="text-[#6FD3A5]">↑ ₹{{ number_format($r['change'], abs($r['change']) < 10 ? 2 : 0) }} <span class="opacity-75">({{ number_format($r['pct'], 1) }}%)</span></span>
                                    @else
                                        <span class="text-[#F2A0A0]">↓ ₹{{ number_format(abs($r['change']), abs($r['change']) < 10 ? 2 : 0) }} <span class="opacity-75">({{ number_format(abs($r['pct']), 1) }}%)</span></span>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @elseif ($canRates)
                <x-ui.button variant="primary" icon="coins" :href="route('pricing.rates')">Enter today's rates</x-ui.button>
            @endif
        </div>
    </section>

    {{-- Quick actions --}}
    @php
        // Literal class names so Tailwind emits them; one row on wide screens whatever the user can see.
        $actionCols = [1 => 'sm:grid-cols-1', 2 => 'sm:grid-cols-2', 3 => 'sm:grid-cols-3', 4 => 'sm:grid-cols-4',
            5 => 'sm:grid-cols-3 xl:grid-cols-5', 6 => 'sm:grid-cols-3 xl:grid-cols-6',
            7 => 'sm:grid-cols-4 xl:grid-cols-7', 8 => 'sm:grid-cols-4 xl:grid-cols-8'][count($actions)] ?? 'sm:grid-cols-4';
    @endphp
    <nav aria-label="Quick actions" class="grid grid-cols-2 {{ $actionCols }} gap-3">
        @foreach ($actions as $a)
            @php $tileClass = 'press group flex items-center xl:flex-col xl:items-start gap-3 bg-white border border-line-light rounded-card shadow-card px-4 py-3.5 text-left transition-[border-color,box-shadow] duration-200 hover:border-gold-soft hover:shadow-raised'; @endphp
            @if (! empty($a['scan']))
                <button type="button" x-on:click="$dispatch('rj-scan', { mode: 'go' })" class="{{ $tileClass }}">
            @else
                <a href="{{ $a['href'] }}" class="{{ $tileClass }}">
            @endif
                <span class="w-9 h-9 shrink-0 rounded-[10px] flex items-center justify-center transition-colors {{ $loop->first ? 'gold-sheen text-white shadow-gold' : 'bg-gold-tint text-gold-dark ring-1 ring-inset ring-gold-soft/60 group-hover:bg-[#F6EAD0]' }}">
                    <x-ui.icon :name="$a['icon']" :size="16" />
                </span>
                <span class="text-[13px] font-semibold text-ink_text-primary leading-tight">{{ $a['label'] }}</span>
            @if (! empty($a['scan']))
                </button>
            @else
                </a>
            @endif
        @endforeach
    </nav>

    {{-- Headline figures --}}
    <div class="grid grid-cols-2 {{ $finance ? 'xl:grid-cols-4' : 'lg:grid-cols-3' }} gap-4">
        @if ($finance)
            <x-ui.stat-card icon="receipt" label="Sales today" :href="route('sales.history')"
                :value="'₹' . Dashboard::inr($sales['today'])" title="{{ Dashboard::inrFull($sales['today']) }}"
                :hint="$sales['todayCount'] . ' ' . str('bill')->plural($sales['todayCount']) . ' entered'" />
            <x-ui.stat-card icon="bar-chart" label="Sales this month" :href="route('sales.history')"
                :value="'₹' . Dashboard::inr($sales['month'])" title="{{ Dashboard::inrFull($sales['month']) }}"
                :delta="$sales['monthDelta'] !== null ? number_format(abs($sales['monthDelta']), 0) . '%' : null"
                :delta-positive="($sales['monthDelta'] ?? 0) >= 0"
                :hint="$sales['monthDelta'] !== null ? 'vs same days last month' : $sales['monthCount'] . ' ' . str('bill')->plural($sales['monthCount']) . ' so far'" />
        @endif
        <x-ui.stat-card icon="gem" label="Pieces the shop owns" :href="Route::has('stock.items') && auth()->user()->can('stock.manage') ? route('stock.items') : null"
            :value="number_format($stock['owned'])"
            :hint="$g($stock['ownedWeight']) . ' across all metals'" />
        @if ($finance)
            <x-ui.stat-card icon="coins" label="Metal value at today's rate"
                :value="'₹' . Dashboard::inr($stock['metalValue'])" title="{{ Dashboard::inrFull($stock['metalValue']) }}"
                hint="Weight × rate, before making charges" />
        @else
            <x-ui.stat-card icon="layers" label="On the counter" :href="route('movements.vault-counter')"
                :value="number_format($stock['counter'])" hint="Out of the vault right now" />
            <x-ui.stat-card icon="truck" label="Out of the shop"
                :value="number_format($stock['outCount'])"
                :hint="$stock['overdue'] ? $stock['overdue'] . ' overdue' : 'Karigar, hallmarking and others'" />
        @endif
    </div>

    {{-- Trend + attention --}}
    <div class="grid grid-cols-1 xl:grid-cols-12 gap-4 items-start">
        @if ($finance)
            <x-ui.card class="xl:col-span-8" :padding="false">
                <div class="flex flex-wrap items-start justify-between gap-4 px-5 pt-5">
                    <div>
                        <h2 class="text-[14.5px] font-bold text-ink_text-primary">Sales, last {{ $range }} days</h2>
                        <div class="flex items-baseline gap-3 mt-1.5">
                            <span class="font-display text-[30px] leading-none font-semibold text-ink_text-primary">₹{{ Dashboard::inr($trend['total']) }}</span>
                            <span class="text-[12.5px] text-ink_text-secondary">
                                {{ number_format($trend['count']) }} {{ str('bill')->plural($trend['count']) }}
                                @if ($trend['count']) · avg ₹{{ Dashboard::inr($trend['average']) }} @endif
                            </span>
                        </div>
                    </div>
                    <div class="rj-segment" role="group" aria-label="Chart range">
                        @foreach (Dashboard::RANGES as $days)
                            <button type="button" wire:click="setRange({{ $days }})" @class(['is-active' => $range === $days]) aria-pressed="{{ $range === $days ? 'true' : 'false' }}">{{ $days }}d</button>
                        @endforeach
                    </div>
                </div>

                <div class="px-5 pb-4 pt-3 transition-opacity" wire:loading.class="opacity-50" wire:target="setRange">
                    <div wire:key="trend-{{ $range }}" x-data="rjTrendChart(@js($trend['points']))" class="relative select-none">
                        <div x-ref="box" class="relative h-[250px] outline-none rounded-lg focus-visible:ring-2 focus-visible:ring-gold/40"
                             tabindex="0" role="img" aria-label="Daily sales for the last {{ $range }} days. Use the arrow keys to read each day."
                             x-on:pointermove="pointer($event)" x-on:pointerleave="hover = null"
                             x-on:keydown.arrow-right.prevent="step(1)" x-on:keydown.arrow-left.prevent="step(-1)"
                             x-on:focus="if (hover === null) hover = points.length - 1" x-on:blur="hover = null">
                            {{-- y-axis labels --}}
                            <template x-for="t in ticks" :key="t">
                                <div class="absolute left-0 w-[48px] -translate-y-1/2 text-right text-[11px] text-ink_text-muted tabular" :style="`top:${y(t)}px`" x-text="money(t)"></div>
                            </template>
                            <svg class="absolute inset-0 overflow-visible" :width="w" :height="h" aria-hidden="true">
                                <path :d="gridPath" stroke="#EFEBE3" stroke-width="1" fill="none" shape-rendering="crispEdges" />
                                <path :d="areaPath" fill="#B8862D" fill-opacity="0.10" />
                                <path :d="linePath" stroke="#B8862D" stroke-width="2" fill="none" stroke-linejoin="round" stroke-linecap="round" />
                                <line x-show="hover !== null" :x1="hx" :x2="hx" :y1="pad.t" :y2="h - pad.b" stroke="#D9D3C6" stroke-width="1" shape-rendering="crispEdges" />
                                <circle x-show="hover === null && points.length" :cx="x(points.length - 1)" :cy="y(points[points.length - 1]?.total || 0)" r="4.5" fill="#B8862D" stroke="#fff" stroke-width="2" />
                                <circle x-show="hover !== null" :cx="hx" :cy="hy" r="5" fill="#B8862D" stroke="#fff" stroke-width="2" />
                            </svg>
                            {{-- x-axis labels --}}
                            <template x-for="i in xLabels" :key="i">
                                <div class="absolute bottom-0 -translate-x-1/2 text-[11px] text-ink_text-muted whitespace-nowrap" :style="`left:${x(i)}px`" x-text="points[i].short"></div>
                            </template>
                            <div x-show="empty" class="absolute inset-0 flex items-center justify-center text-[13px] text-ink_text-muted">No sales entered in this period.</div>

                            {{-- tooltip --}}
                            <div x-show="hover !== null" x-cloak
                                 class="absolute z-10 pointer-events-none min-w-[168px] bg-white border border-line rounded-xl shadow-pop px-3.5 py-2.5"
                                 :style="tipStyle">
                                <div class="text-[11.5px] font-semibold text-ink_text-muted" x-text="cur?.label"></div>
                                <div class="flex items-center gap-2 mt-1">
                                    <span class="w-3 h-[2px] rounded-full bg-gold"></span>
                                    <span class="font-display text-[20px] leading-none font-semibold text-ink_text-primary" x-text="'₹' + full(cur?.total || 0)"></span>
                                </div>
                                <div class="text-[11.5px] text-ink_text-secondary mt-1.5">
                                    <span x-text="(cur?.count || 0) + ((cur?.count === 1) ? ' bill' : ' bills')"></span><template x-if="cur?.pending"><span> · <span x-text="cur.pending"></span> awaiting verification</span></template>
                                </div>
                            </div>
                        </div>
                    </div>

                    <table class="sr-only">
                        <caption>Daily sales, last {{ $range }} days</caption>
                        <thead><tr><th>Date</th><th>Sales</th><th>Bills</th></tr></thead>
                        <tbody>
                            @foreach ($trend['points'] as $p)
                                <tr><td>{{ $p['label'] }}</td><td>{{ Dashboard::inrFull($p['total']) }}</td><td>{{ $p['count'] }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="flex flex-wrap items-center gap-x-6 gap-y-2 px-5 py-3.5 border-t border-line-light bg-surface-sunken/60 rounded-b-card text-[12.5px]">
                    @if ($trend['best'] && $trend['best']['total'] > 0)
                        <span class="text-ink_text-secondary">Best day <span class="font-semibold text-ink_text-primary">{{ $trend['best']['label'] }}</span> · ₹{{ Dashboard::inr($trend['best']['total']) }}</span>
                    @endif
                    @if ($sales['unverified'] > 0)
                        <span class="text-ink_text-secondary">Awaiting verification <span class="font-semibold text-ink_text-primary">₹{{ Dashboard::inr($sales['unverified']) }}</span></span>
                    @endif
                    <a href="{{ route('sales.history') }}" class="ml-auto inline-flex items-center gap-1 font-semibold text-gold-dark hover:text-gold">Sales history <x-ui.icon name="arrow-right" :size="13" /></a>
                </div>
            </x-ui.card>
        @endif

        <x-ui.card id="attention" class="scroll-mt-24 {{ $finance ? 'xl:col-span-4' : 'xl:col-span-12' }}" title="Needs attention" icon="bell"
            :subtitle="count($attention) ? 'Most urgent first' : null" :padding="false">
            @if (count($attention))
                <x-slot:actions>
                    <x-ui.badge :tone="$urgent ? 'danger' : 'gold'">{{ count($attention) }}</x-ui.badge>
                </x-slot:actions>
                <ul @class(['divide-y divide-line-light', 'grid sm:grid-cols-2 lg:grid-cols-3 sm:divide-y-0' => ! $finance])>
                    @foreach ($attention as $a)
                        <li>
                            <a href="{{ $a['href'] }}" class="group flex items-center gap-3.5 px-5 py-3.5 hover:bg-surface-sunken transition-colors">
                                <span class="w-9 h-9 shrink-0 rounded-[10px] ring-1 ring-inset flex items-center justify-center {{ $toneIcon[$a['tone']] }}">
                                    <x-ui.icon :name="$a['icon']" :size="16" />
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="block text-[13.5px] font-semibold text-ink_text-primary leading-snug">{{ $a['title'] }}</span>
                                    <span class="block text-[12px] text-ink_text-secondary leading-snug mt-0.5">{{ $a['detail'] }}</span>
                                </span>
                                @unless (! empty($a['hideCount']))
                                    <span class="font-display text-[24px] leading-none font-semibold text-ink_text-primary tabular">{{ $a['count'] }}</span>
                                @endunless
                                <x-ui.icon name="chevron-right" :size="15" class="shrink-0 text-ink_text-muted group-hover:text-gold-dark group-hover:translate-x-0.5 transition-transform" />
                            </a>
                        </li>
                    @endforeach
                </ul>
            @else
                <x-ui.empty-state icon="check-circle" title="All caught up" message="No sales to verify, returns to review or overdue pieces." compact />
            @endif
        </x-ui.card>
    </div>

    {{-- Stock, work in progress, activity --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-4 items-start">

        <x-ui.card title="Where your stock is" icon="map-pin"
            :subtitle="number_format($stock['owned']) . ' pieces · ' . $g($stock['ownedWeight'])">
            @if (auth()->user()->can('audit.view') && Route::has('reports.location'))
                <x-slot:actions>
                    <x-ui.button variant="ghost" size="xs" :href="route('reports.location')" icon-right="arrow-right">Report</x-ui.button>
                </x-slot:actions>
            @endif

            @php $maxLoc = max(1, $stock['locations']->max('count') ?? 1); @endphp
            @if ($stock['locations']->isEmpty())
                <p class="text-[13px] text-ink_text-muted">No pieces recorded yet.</p>
            @else
                <ul class="space-y-3.5">
                    @foreach ($stock['locations'] as $key => $loc)
                        <li>
                            <div class="flex items-baseline justify-between gap-3 text-[13px]">
                                <span class="text-ink_text-primary font-medium flex items-center gap-2">
                                    {{ $loc['label'] }}
                                    @if ($loc['overdue'])
                                        <x-ui.badge tone="danger" size="sm" dot>{{ $loc['overdue'] }} overdue</x-ui.badge>
                                    @endif
                                </span>
                                <span class="shrink-0 tabular">
                                    <span class="font-semibold text-ink_text-primary">{{ number_format($loc['count']) }}</span>
                                    <span class="text-ink_text-muted text-[12px] ml-1.5">{{ $g($loc['weight']) }}</span>
                                </span>
                            </div>
                            <div class="mt-1.5 h-1.5 rounded-full bg-surface-muted overflow-hidden" title="{{ $loc['label'] }}: {{ $loc['count'] }} pieces">
                                <div class="h-full rounded-full {{ $key === 'vault' || $key === 'counter' ? 'bg-gold' : 'bg-gold-light' }}" style="width: {{ max(2, round($loc['count'] / $maxLoc * 100, 1)) }}%"></div>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif

            @if ($stock['byMetal']->isNotEmpty())
                <div class="mt-5 pt-4 border-t border-line-light grid grid-cols-2 gap-3">
                    @foreach ($stock['byMetal'] as $m)
                        <div class="rounded-control bg-surface-sunken px-3 py-2.5">
                            <div class="flex items-center gap-2 text-[12px] text-ink_text-secondary">
                                <x-movement.metal-dot :metal="$m['metal']" /> {{ $metalNames[$m['metal']] ?? ucfirst($m['metal']) }}
                                <span class="ml-auto text-ink_text-muted tabular">{{ $m['count'] }} pcs</span>
                            </div>
                            <div class="font-display text-[19px] leading-tight font-semibold text-ink_text-primary mt-1">{{ $g($m['weight']) }}</div>
                            @if ($finance)
                                <div class="text-[11.5px] text-ink_text-muted tabular" title="{{ Dashboard::inrFull($m['value']) }}">≈ ₹{{ Dashboard::inr($m['value']) }}</div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </x-ui.card>

        <x-ui.card title="Work in progress" icon="clipboard" subtitle="Orders, exchanges and material out">
            @php
                $orderSteps = ['placed' => 'Placed', 'confirmed' => 'Confirmed', 'ready' => 'Ready'];
                $exchangeSteps = ['received' => 'Received', 'melted' => 'Melted', 'tested' => 'Tested', 'valued' => 'Valued'];
            @endphp

            @if ($pipeline['orders'] !== null)
                <div class="flex items-center justify-between mb-2.5">
                    <h4 class="text-[12px] font-bold uppercase tracking-[0.12em] text-ink_text-muted">Custom orders</h4>
                    <a href="{{ route('orders.board') }}" class="text-[12px] font-semibold text-gold-dark hover:text-gold">Board</a>
                </div>
                <div class="grid grid-cols-3 gap-2">
                    @foreach ($orderSteps as $key => $label)
                        <a href="{{ route('orders.board') }}" class="rounded-control border border-line-light px-3 py-2.5 hover:border-gold-soft transition-colors {{ $key === 'ready' && ($pipeline['orders'][$key] ?? 0) ? 'bg-gold-tint border-gold-soft/70' : 'bg-white' }}">
                            <div class="font-display text-[24px] leading-none font-semibold text-ink_text-primary">{{ $pipeline['orders'][$key] ?? 0 }}</div>
                            <div class="text-[11.5px] text-ink_text-secondary mt-1">{{ $label }}</div>
                        </a>
                    @endforeach
                </div>

                @if ($pipeline['dueSoon']->isNotEmpty())
                    <ul class="mt-3 divide-y divide-line-light">
                        @foreach ($pipeline['dueSoon'] as $o)
                            @php $late = $o->expected_ready_date->isBefore(today()); @endphp
                            <li>
                                <a href="{{ route('orders.show', $o) }}" class="flex items-center gap-3 py-2.5 group">
                                    <span class="min-w-0 flex-1">
                                        <span class="block text-[13px] font-medium text-ink_text-primary truncate group-hover:text-gold-dark">{{ $o->product_description }}</span>
                                        <span class="block text-[11.5px] text-ink_text-muted truncate">{{ $o->customer->name ?? 'Customer' }}</span>
                                    </span>
                                    <x-ui.badge :tone="$late ? 'danger' : ($o->expected_ready_date->isToday() ? 'warning' : 'neutral')" size="sm">
                                        {{ $late ? 'Late · ' : 'Due ' }}{{ $o->expected_ready_date->isToday() ? 'today' : $o->expected_ready_date->format('j M') }}
                                    </x-ui.badge>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            @endif

            @if ($pipeline['exchange'] !== null)
                <div @class(['flex items-center justify-between mb-2.5', 'mt-5 pt-4 border-t border-line-light' => $pipeline['orders'] !== null])>
                    <h4 class="text-[12px] font-bold uppercase tracking-[0.12em] text-ink_text-muted">Old gold exchange</h4>
                    <a href="{{ route('exchange.tracker') }}" class="text-[12px] font-semibold text-gold-dark hover:text-gold">Tracker</a>
                </div>
                <ol class="flex items-stretch">
                    @foreach ($exchangeSteps as $key => $label)
                        @php $n = $pipeline['exchange'][$key] ?? 0; @endphp
                        <li class="flex-1 min-w-0 relative">
                            <div class="flex items-center">
                                <span @class([
                                    'w-7 h-7 shrink-0 rounded-full flex items-center justify-center text-[12px] font-bold tabular ring-1 ring-inset',
                                    'gold-sheen text-white ring-gold-dark/30' => $n > 0,
                                    'bg-surface-muted text-ink_text-muted ring-line' => $n === 0,
                                ])>{{ $n }}</span>
                                @unless ($loop->last)<span class="flex-1 h-px bg-line mx-1.5"></span>@endunless
                            </div>
                            <div class="text-[11.5px] text-ink_text-secondary mt-1.5">{{ $label }}</div>
                        </li>
                    @endforeach
                </ol>
            @endif

            <div @class(['grid grid-cols-3 gap-2', 'mt-5 pt-4 border-t border-line-light' => $pipeline['orders'] !== null || $pipeline['exchange'] !== null])>
                <a href="{{ route('movements.karigar-return') }}" class="rounded-control bg-surface-sunken px-3 py-2.5 hover:bg-surface-muted transition-colors">
                    <div class="font-display text-[20px] leading-none font-semibold text-ink_text-primary">{{ $pipeline['karigarRaw'] }}</div>
                    <div class="text-[11.5px] text-ink_text-secondary mt-1 leading-snug">Raw batches at karigar</div>
                </a>
                <a href="{{ route('movements.karigar-return') }}" class="rounded-control bg-surface-sunken px-3 py-2.5 hover:bg-surface-muted transition-colors">
                    <div class="font-display text-[20px] leading-none font-semibold text-ink_text-primary">{{ $pipeline['customerJobs'] }}</div>
                    <div class="text-[11.5px] text-ink_text-secondary mt-1 leading-snug">Customer repairs out</div>
                </a>
                @if ($pipeline['refinery'] !== null)
                    <a href="{{ route('exchange.refinery.return') }}" class="rounded-control bg-surface-sunken px-3 py-2.5 hover:bg-surface-muted transition-colors">
                        <div class="font-display text-[20px] leading-none font-semibold text-ink_text-primary">{{ $pipeline['refinery'] }}</div>
                        <div class="text-[11.5px] text-ink_text-secondary mt-1 leading-snug">Batches at refinery</div>
                    </a>
                @endif
            </div>
        </x-ui.card>

        <x-ui.card title="Recent activity" icon="history" :padding="false" class="lg:col-span-2 xl:col-span-1">
            @if (Route::has('movements.log'))
                <x-slot:actions>
                    <x-ui.button variant="ghost" size="xs" :href="route('movements.log')" icon-right="arrow-right">Movement log</x-ui.button>
                </x-slot:actions>
            @endif
            @if ($activity->isEmpty())
                <x-ui.empty-state icon="history" title="Nothing yet" message="Movements and sales will appear here as they happen." compact />
            @else
                <ol class="px-5 py-2">
                    @foreach ($activity as $e)
                        <li class="relative flex gap-3.5 py-2.5">
                            @unless ($loop->last)<span aria-hidden="true" class="absolute left-[15px] top-10 bottom-[-6px] w-px bg-line-light"></span>@endunless
                            <span @class([
                                'relative w-8 h-8 shrink-0 rounded-full ring-1 ring-inset flex items-center justify-center',
                                'bg-gold-tint text-gold-dark ring-gold-soft/70' => $e['icon'] === 'receipt',
                                'bg-surface-sunken text-ink_text-secondary ring-line' => $e['icon'] !== 'receipt',
                            ])>
                                <x-ui.icon :name="$e['icon']" :size="14" />
                            </span>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-baseline justify-between gap-3">
                                    <span class="text-[13px] font-semibold text-ink_text-primary truncate">{{ $e['title'] }}</span>
                                    <time class="shrink-0 text-[11.5px] text-ink_text-muted" datetime="{{ $e['at']?->toIso8601String() }}" title="{{ $e['at']?->format('j M Y, g:i a') }}">{{ $e['at']?->isToday() ? $e['at']->format('g:i a') : $e['at']?->format('j M') }}</time>
                                </div>
                                <div class="text-[12px] text-ink_text-secondary truncate mt-0.5">
                                    @if ($e['url'])
                                        <a href="{{ $e['url'] }}" class="{{ $e['icon'] === 'receipt' ? 'font-semibold' : 'rj-code' }} text-ink_text-primary hover:text-gold-dark">{{ $e['code'] }}</a>
                                    @else
                                        <span class="{{ $e['icon'] === 'receipt' ? 'font-semibold' : 'rj-code' }} text-ink_text-primary">{{ $e['code'] }}</span>
                                    @endif
                                    @if ($e['detail']) · {{ $e['detail'] }} @endif
                                    <span class="text-ink_text-muted">· {{ $e['by'] }}</span>
                                </div>
                            </div>
                        </li>
                    @endforeach
                </ol>
            @endif
        </x-ui.card>
    </div>
</div>
