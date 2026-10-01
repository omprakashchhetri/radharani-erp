{{--
    Public storefront shell. Deliberately separate from the ERP's layouts:
    no Tailwind, no Livewire, no Alpine. The storefront runs its own CSS
    (public/storefront/css) and its own GSAP-driven scripts
    (public/storefront/js), which render the header, footer, cards and
    listings from window.RJ_DATA (built by App\Services\StorefrontCatalog).
--}}
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $title ?? 'Radharani Jewellery Works | Hallmarked gold and silver jewellery' }}</title>
<meta name="description" content="{{ $description ?? "Family jewellers. BIS hallmarked, HUID certified gold and silver jewellery, weighed in front of you and priced at the day's rate." }}">
<meta name="theme-color" content="#f3f4f0">
@isset($canonical)<link rel="canonical" href="{{ $canonical }}">@endisset
@isset($ogImage)
<meta property="og:title" content="{{ $title }}">
<meta property="og:description" content="{{ $description }}">
<meta property="og:image" content="{{ $ogImage }}">
<meta property="og:type" content="product">
@endisset
<link rel="icon" href="{{ \App\Support\StorefrontAsset::url('img/mark.png') }}">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="preconnect" href="https://images.unsplash.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bodoni+Moda:ital,opsz,wght@0,6..96,400..600;1,6..96,400..600&family=Jost:wght@300;400;500;600&display=swap">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.1/src/regular/style.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.1/src/fill/style.css">
<link rel="stylesheet" href="{{ \App\Support\StorefrontAsset::url('css/base.css') }}">
<link rel="stylesheet" href="{{ \App\Support\StorefrontAsset::url('css/'.$page.'.css') }}">
</head>
<body class="no-js" data-page="{{ $page }}">

@yield('before')

<div id="rj-header"></div>

<div id="smooth-wrapper">
<div id="smooth-content">
@yield('content')
<div id="rj-footer"></div>
</div>
</div>

@yield('after')

<script>window.RJ_DATA = @json($rj, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG);</script>
<script src="{{ \App\Support\StorefrontAsset::url('js/gsap-bundle.js') }}"></script>
<script src="{{ \App\Support\StorefrontAsset::url('js/core.js') }}"></script>
<script src="{{ \App\Support\StorefrontAsset::url('js/'.$page.'.js') }}"></script>
@stack('scripts')
</body>
</html>
