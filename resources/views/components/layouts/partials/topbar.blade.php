@php
$user = auth()->user();
$goldRate = \App\Models\Movement\RateLog::latestFor('gold');
$silverRate = \App\Models\Movement\RateLog::latestFor('silver');
$unread = \App\Models\Notification\PendingNotification::where('status', 'pending')->count();
$rateStale = $goldRate && $goldRate->created_at && ! $goldRate->created_at->isToday();
@endphp
<header class="sticky top-0 z-topbar h-16 shrink-0 bg-white/85 backdrop-blur-md border-b border-line-light supports-[backdrop-filter]:bg-white/75">
    <div class="h-full flex items-center gap-3 px-4 sm:px-6 lg:px-9">
        <button type="button" x-on:click="toggleSidebar()"
            class="press w-9 h-9 shrink-0 rounded-control text-ink_text-secondary hover:text-ink_text-primary hover:bg-surface-muted flex items-center justify-center"
            aria-label="Toggle navigation" title="Toggle sidebar (Ctrl + \)">
            <x-ui.icon name="menu" :size="18" class="lg:hidden" />
            <x-ui.icon name="panel-left" :size="18" class="hidden lg:block" />
        </button>

        @livewire('layout.global-search')

        {{-- Scan any sticker, HUID, piece, packet or box code and open it (phones have no hand scanner) --}}
        <button type="button" x-on:click="$dispatch('rj-scan', { mode: 'go' })" aria-label="Scan a tag with the camera" title="Scan a tag with the camera"
            class="press h-9 shrink-0 rounded-control px-2.5 sm:px-3 inline-flex items-center gap-2 text-[12.5px] font-semibold text-gold-dark bg-gold-tint ring-1 ring-inset ring-gold-soft/70 hover:bg-[#F6EAD0] transition-colors">
            <x-ui.icon name="scan" :size="17" />
            <span class="hidden xl:inline">Scan</span>
        </button>

        <div class="ml-auto flex items-center gap-1.5 sm:gap-2.5">
            @if ($goldRate || $silverRate)
                <a href="{{ \Illuminate\Support\Facades\Route::has('pricing.rates') ? route('pricing.rates') : '#' }}"
                   class="hidden md:flex items-center h-9 rounded-full border border-line-light bg-surface-sunken pl-1 pr-3.5 gap-2.5 hover:border-gold-soft transition-colors group"
                   title="{{ $rateStale ? 'Rates were last updated ' . $goldRate->created_at->diffForHumans() . '. Enter today\'s rate.' : 'Today\'s metal rates' }}">
                    <span class="w-7 h-7 rounded-full gold-sheen flex items-center justify-center text-white">
                        <x-ui.icon name="coins" :size="14" />
                    </span>
                    @if ($goldRate)
                        <span class="text-[12.5px] text-ink_text-secondary">Gold <span class="font-bold text-ink_text-primary tabular">₹{{ number_format($goldRate->rate, 0) }}</span><span class="text-ink_text-muted">/g</span></span>
                    @endif
                    @if ($silverRate)
                        <span class="w-px h-4 bg-line"></span>
                        <span class="text-[12.5px] text-ink_text-secondary">Silver <span class="font-bold text-ink_text-primary tabular">₹{{ number_format($silverRate->rate, 2) }}</span><span class="text-ink_text-muted">/g</span></span>
                    @endif
                    @if ($rateStale)
                        <span class="text-[11px] font-bold text-warning">Update</span>
                    @endif
                </a>
            @endif

            <a href="{{ route('notifications.queue') }}" aria-label="Pending messages"
               class="press relative w-9 h-9 rounded-control text-ink_text-secondary hover:text-ink_text-primary hover:bg-surface-muted flex items-center justify-center">
                <x-ui.icon name="bell" :size="18" />
                @if ($unread > 0)
                    <span class="absolute top-1 right-1 min-w-[16px] h-4 px-1 rounded-full bg-gold text-white ring-2 ring-white text-[9.5px] font-bold tabular flex items-center justify-center">
                        {{ $unread > 9 ? '9+' : $unread }}
                    </span>
                @endif
            </a>

            <div class="hidden sm:block w-px h-7 bg-line-light mx-1"></div>

            <x-ui.dropdown width="w-60">
                <x-slot:trigger>
                    <button type="button" class="press flex items-center gap-2.5 h-10 pl-1 pr-2 rounded-full hover:bg-surface-muted transition-colors">
                        <span class="w-8 h-8 rounded-full gold-sheen text-white flex items-center justify-center font-display text-[16px] font-semibold ring-2 ring-white shadow-card">
                            {{ strtoupper(mb_substr($user->name ?? 'U', 0, 1)) }}
                        </span>
                        <span class="hidden sm:flex flex-col items-start leading-tight">
                            <span class="text-[13px] font-semibold text-ink_text-primary max-w-[140px] truncate">{{ $user->name ?? 'Staff' }}</span>
                            <span class="text-[11px] text-ink_text-muted capitalize">{{ str_replace('_', ' ', $user?->getRoleNames()->first() ?? 'staff') }}</span>
                        </span>
                        <x-ui.icon name="chevron-down" :size="14" class="hidden sm:block text-ink_text-muted" />
                    </button>
                </x-slot:trigger>

                <div class="px-2.5 pt-2 pb-2.5 mb-1 border-b border-line-light">
                    <div class="text-[13px] font-semibold text-ink_text-primary truncate">{{ $user->name ?? 'Staff' }}</div>
                    <div class="text-[12px] text-ink_text-muted truncate">{{ $user->email ?? $user->phone }}</div>
                </div>
                <x-ui.dropdown-item icon="user" :href="route('profile.edit')">Profile</x-ui.dropdown-item>
                <x-ui.dropdown-item icon="panel-left" x-on:click="toggleSidebar()">Toggle sidebar</x-ui.dropdown-item>
                <form method="POST" action="{{ route('logout') }}" class="mt-1 pt-1 border-t border-line-light">
                    @csrf
                    <x-ui.dropdown-item icon="log-out" type="submit" tone="danger">Log out</x-ui.dropdown-item>
                </form>
            </x-ui.dropdown>
        </div>
    </div>
</header>
