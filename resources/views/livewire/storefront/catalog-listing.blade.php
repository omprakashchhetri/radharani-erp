<div class="min-h-screen bg-surface-bg text-ink_text-primary">
  <div class="max-w-[960px] mx-auto px-5 pt-8 pb-16">

    <div class="text-center mb-7">
      <x-ui.logo :size="72" class="w-[72px] h-[72px] mx-auto mb-3" />
      <div class="font-display text-[32px] leading-tight font-semibold">Radharani Jewellery Works</div>
      <div class="text-[13px] text-ink_text-secondary mt-1">Browse our current collection — prices update with the day's rates.</div>
    </div>

    <div class="bg-[#FFF7E6] rounded-control px-3.5 py-2.5 text-xs text-gold-dark mb-5">
      Product photos aren't available yet — there's no photo field on stock items in the schema (only dispatch
      documentation photos exist, which are a different thing). Showing a placeholder swatch per item until that's added.
    </div>

    <div class="flex gap-2.5 mb-5 flex-wrap">
      <input type="text" wire:model.live.debounce.400ms="search" placeholder="Search category or description…"
        class="rj-input flex-1 min-w-[220px]">
      <select wire:model.live="categoryFilter" class="rj-select">
        <option value="all">All categories</option>
        @foreach ($categories as $cat)
          <option value="{{ $cat }}">{{ ucfirst($cat) }}</option>
        @endforeach
      </select>
    </div>

    <div class="grid gap-4" style="grid-template-columns:repeat(auto-fill,minmax(210px,1fr));">
      @forelse ($items as $item)
        <a href="{{ route('storefront.product', $item) }}" wire:navigate
          class="block bg-white border border-line-light rounded-card overflow-hidden shadow-card hover:border-gold transition-colors">
          <div class="h-[140px] bg-gradient-to-br from-[#FBF3E6] to-[#F3EFE8] flex items-center justify-center text-gold text-[13px] font-medium">
            {{ ucfirst($item->category) }}
          </div>
          <div class="px-3.5 py-3">
            <div class="font-bold text-[13.5px] text-ink_text-primary">{{ ucfirst($item->category) }} — {{ $item->purity }}</div>
            <div class="text-[11.5px] text-ink_text-secondary mt-0.5">{{ number_format($item->weight, 3) }}g</div>
            <div class="text-[17px] font-semibold text-ink_text-primary mt-2">₹{{ number_format($pricing->priceFor($item), 2) }}</div>
          </div>
        </a>
      @empty
        <div class="col-span-full text-ink_text-secondary text-[13px] text-center py-10">No items available right now.</div>
      @endforelse
    </div>

    <div class="mt-6">{{ $items->links() }}</div>
  </div>
</div>
