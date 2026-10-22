<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Karigar issue · {{ $batch->vendor->name }} · {{ $batch->label }}</title>
<style>
  body { font: 14px/1.5 system-ui, sans-serif; color: #1a1a1a; margin: 0; padding: 32px; max-width: 760px; margin-inline: auto; }
  h1 { font-size: 22px; margin: 0 0 4px; }
  .sub { color: #6f6b63; margin-bottom: 24px; }
  table { width: 100%; border-collapse: collapse; margin: 16px 0; }
  th, td { text-align: left; padding: 8px 10px; border-bottom: 1px solid #ddd; vertical-align: top; }
  th { width: 34%; color: #6f6b63; font-weight: 600; }
  .sign { display: flex; gap: 48px; margin-top: 64px; }
  .sign div { flex: 1; border-top: 1px solid #1a1a1a; padding-top: 6px; color: #6f6b63; }
  .actions { margin-bottom: 20px; }
  button { font: inherit; padding: 8px 16px; cursor: pointer; }
  .photos { display: flex; flex-wrap: wrap; gap: 12px; margin: 8px 0 4px; }
  .photos figure { margin: 0; } .photos img { height: 150px; width: auto; max-width: 220px; object-fit: cover; border: 1px solid #ddd; border-radius: 6px; }
  .photos figcaption { font-size: 12px; color: #6f6b63; margin-top: 3px; }
  h2 { font-size: 15px; margin: 22px 0 0; }
  @media print { .actions { display: none; } body { padding: 0; } .photos img { height: 120px; } }
</style>
</head>
<body>
<div class="actions"><button onclick="window.print()">Print</button></div>
@php
  $order = $batch->order_id ? \App\Models\Orders\Order::with('images', 'customer')->find($batch->order_id) : null;
  $issuedPhoto = $batch->photo_path && \Illuminate\Support\Facades\Storage::disk('public')->exists($batch->photo_path) ? asset('storage/' . $batch->photo_path) : null;
  $reference = $order ? $order->images->filter(fn ($i) => $i->url) : collect();
@endphp
<h1>Issued to karigar</h1>
<div class="sub">Radharani Jewellery Works · {{ $batch->label }}</div>
<table>
  <tr><th>Karigar</th><td>{{ $batch->vendor->name }}{{ $batch->vendor->phone ? ' · ' . $batch->vendor->phone : '' }}</td></tr>
  <tr><th>Description</th><td>{{ $batch->description ?: $batch->purpose_label }}</td></tr>
  @foreach ($batch->lines as $l)
  <tr><th>Item {{ $loop->iteration }}</th><td>{{ $l->description }} · {{ $l->pieces }} {{ \Illuminate\Support\Str::plural('piece', $l->pieces) }} · {{ number_format($l->weight, 3) }} g</td></tr>
  @endforeach
  @if ($batch->categories)<tr><th>Categories</th><td>{{ implode(', ', $batch->categories) }}</td></tr>@endif
  <tr><th>Metal</th><td>{{ ucfirst($batch->metal) }}</td></tr>
  <tr><th>Pieces expected</th><td>{{ $batch->pieces_expected }}</td></tr>
  <tr><th>Estimated weight</th><td>{{ number_format($batch->weight_out, 3) }} g</td></tr>
  <tr><th>Expected back</th><td>{{ $batch->expected_return?->format('j M Y') ?? '-' }}</td></tr>
  <tr><th>Advance</th><td>
    @if ($batch->advance_cash > 0 || $batch->advance_metal_weight > 0)
      @if ($batch->advance_cash > 0)Cash ₹{{ number_format($batch->advance_cash) }}@endif
      @if ($batch->advance_cash > 0 && $batch->advance_metal_weight > 0) and @endif
      @if ($batch->advance_metal_weight > 0){{ number_format($batch->advance_metal_weight, 3) }} g {{ $batch->metal }} {{ $batch->advance_metal_purity }}@endif
    @else No advance @endif
  </td></tr>
  @if ($batch->order_id)<tr><th>For order</th><td>#{{ $batch->order_id }}@if ($order?->customer) · {{ $order->customer->name }}@endif @if ($order?->product_description) · {{ $order->product_description }}@endif</td></tr>@endif
  @if ($batch->note)<tr><th>Comments</th><td>{{ $batch->note }}</td></tr>@endif
  <tr><th>Issued by</th><td>{{ $batch->user?->name }}</td></tr>
</table>
@if ($issuedPhoto)
  <h2>Photo of what went out</h2>
  <div class="photos"><figure><img src="{{ $issuedPhoto }}" alt="What went out"></figure></div>
@endif
@if ($reference->isNotEmpty())
  <h2>Customer's reference images{{ $order->customer ? ' · ' . $order->customer->name : '' }}</h2>
  <div class="photos">@foreach ($reference as $img)<figure><img src="{{ $img->url }}" alt="Reference"></figure>@endforeach</div>
@endif
<div class="sign"><div>Karigar signature</div><div>Shop signature</div></div>
</body>
</html>
