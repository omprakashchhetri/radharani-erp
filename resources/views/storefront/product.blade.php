@extends('storefront.layout')

{{-- Product detail. product.js renders the piece named by RJ_DATA.currentId,
     or the "no longer online" state when it has sold or been unlisted. --}}
@section('content')
<main class="wrap" id="top" data-pdp>
  <!-- rendered by public/storefront/js/product.js from RJ_DATA -->
</main>
<div data-more></div>
@endsection

@section('after')
<div class="dock" data-dock aria-hidden="true"></div>
<div class="toast" role="status" aria-live="polite" data-toast></div>
@endsection
