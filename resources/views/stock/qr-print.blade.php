<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>QR Labels ({{ $codes->count() }})</title>
<link rel="icon" type="image/png" href="{{ asset('images/auth/mark.png') }}">
<link rel="apple-touch-icon" href="{{ asset('images/auth/mark.png') }}">
@vite(['resources/css/app.css'])
@php
    $dims = ['sm' => ['w' => 38, 'qr' => 26], 'md' => ['w' => 50, 'qr' => 36], 'lg' => ['w' => 70, 'qr' => 52]][$size];
@endphp
<style>
    @page { size: A4; margin: 10mm; }
    .sheet { display: grid; grid-template-columns: repeat(auto-fill, {{ $dims['w'] }}mm); gap: 4mm; }
    .label { width: {{ $dims['w'] }}mm; border: 0.3mm dashed #D9D3C6; border-radius: 2.5mm; padding: 2.5mm; break-inside: avoid; }
    .label .qr { width: {{ $dims['qr'] }}mm; height: {{ $dims['qr'] }}mm; margin: 0 auto; }
    @media print {
        body { background: #fff !important; }
        .no-print { display: none !important; }
        .sheet-wrap { padding: 0 !important; box-shadow: none !important; border: 0 !important; }
    }
</style>
</head>
<body class="bg-surface-bg">
    <div class="no-print sticky top-0 z-topbar bg-ink text-white">
        <div class="max-w-[1100px] mx-auto px-6 h-16 flex items-center gap-4">
            <div class="w-8 h-8 rounded-[10px] gold-sheen flex items-center justify-center text-ink"><x-ui.icon name="qr-code" :size="16" /></div>
            <div class="leading-tight">
                <div class="font-display text-[20px] font-semibold">QR labels</div>
                <div class="text-[12px] text-ink-dim">{{ $codes->count() }} {{ \Illuminate\Support\Str::plural('sticker', $codes->count()) }} ready to print</div>
            </div>
            <div class="ml-auto flex items-center gap-2">
                <div class="flex items-center gap-1 p-1 rounded-control bg-white/5 ring-1 ring-white/10">
                    @foreach (['sm' => 'Small', 'md' => 'Medium', 'lg' => 'Large'] as $key => $name)
                        <a href="{{ request()->fullUrlWithQuery(['size' => $key]) }}"
                           class="h-8 px-3 rounded-lg text-[12.5px] font-semibold inline-flex items-center {{ $size === $key ? 'bg-white text-ink' : 'text-ink-fg hover:text-white' }}">{{ $name }}</a>
                    @endforeach
                </div>
                <button type="button" onclick="window.print()" class="press h-10 px-4 rounded-control gold-sheen text-white text-[13px] font-semibold inline-flex items-center gap-2 shadow-gold">
                    <x-ui.icon name="printer" :size="15" /> Print
                </button>
            </div>
        </div>
    </div>

    <div class="max-w-[1100px] mx-auto p-6">
        <div class="sheet-wrap bg-white rounded-card shadow-card border border-line-light p-8">
            <div class="sheet">
                @foreach ($codes as $c)
                    <div class="label text-center bg-white">
                        <div class="qr">{!! $c['qr']->svg() !!}</div>
                        <div class="mt-1.5 font-mono font-bold text-[11px] tracking-wide text-ink_text-primary truncate">{{ $c['label'] }}</div>
                        @if ($size !== 'sm')
                            <div class="text-[9px] text-ink_text-secondary truncate">{{ $c['sub'] }}</div>
                        @endif
                        <div class="mt-0.5 font-mono text-[8px] tracking-[0.15em] text-ink_text-muted">{{ strtoupper($c['type']) }} · {{ $c['qr']->code }}</div>
                    </div>
                @endforeach
            </div>
        </div>
        <p class="no-print text-center text-[12px] text-ink_text-muted mt-4">Scanning any label with a logged-in phone opens that {{ $codes->pluck('type')->unique()->implode(' / ') }}'s detail page and records the scan in its history.</p>
    </div>
</body>
</html>
