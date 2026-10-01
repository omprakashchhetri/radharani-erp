@extends('storefront.layout')

{{-- Listing page. Filters, sort, search and saved pieces all run in shop.js
     over RJ_DATA.products; the URL keeps the state (see shop.js header). --}}
@section('content')
<main class="wrap" id="top">
  <nav class="crumbs" aria-label="Breadcrumb" data-crumbs></nav>

  <header class="plp-head">
    <div class="plp-head__txt">
      <h1 data-title>All jewellery</h1>
      <p data-blurb></p>
      <span class="plp-head__count" data-count></span>
    </div>
    <figure class="media media--arch plp-head__art" data-art hidden><img alt=""></figure>
  </header>

  <nav class="strip" aria-label="Categories" data-strip></nav>

  <div class="plp">
    <div class="scrim" data-scrim></div>
    <aside class="filters" aria-label="Filters" data-filters>
      <div class="filters__head">
        <h2>Filters</h2>
        <div>
          <button class="filters__clear" data-clear>Clear all</button>
          <button class="icon-btn filters__close" data-close-filters aria-label="Close filters"><i class="ph ph-x"></i></button>
        </div>
      </div>
      <div class="filters__body" data-groups></div>
      <div class="filters__foot">
        <button class="btn btn--ghost btn--small" data-clear>Clear</button>
        <button class="btn btn--solid btn--small" data-close-filters data-show>Show pieces</button>
      </div>
    </aside>

    <section aria-label="Results">
      <div class="toolbar">
        <div class="toolbar__left">
          <button class="btn btn--ghost btn--small filter-btn" data-open-filters><i class="ph ph-sliders-horizontal"></i>Filters</button>
          <div class="toolbar__left" data-pills></div>
        </div>
        <label class="sort">Sort
          <select data-sort>
            <option value="featured">Featured</option>
            <option value="new">Newest first</option>
            <option value="popular">Bestsellers</option>
            <option value="price-asc">Price, low to high</option>
            <option value="price-desc">Price, high to low</option>
            <option value="weight-desc">Weight, heaviest first</option>
          </select>
        </label>
      </div>
      <div class="grid" data-grid></div>
    </section>
  </div>
</main>
@endsection
