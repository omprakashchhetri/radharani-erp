{{--
    Customer portal shell: the public website's own header, footer, fonts and
    CSS (public/storefront), so signing in from the site feels like the same
    place. Livewire runs the portal pages inside it. No Tailwind here, and
    no staff-side assumptions (this is the `customer` guard's layout).
    RJ_DATA (header/footer data) is supplied by a view composer in
    AppServiceProvider.
--}}
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="robots" content="noindex">
<meta name="theme-color" content="#f3f4f0">
<title>{{ $title ?? 'My account | Radharani Jewellery Works' }}</title>
<link rel="icon" href="{{ \App\Support\StorefrontAsset::url('img/mark.png') }}">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bodoni+Moda:ital,opsz,wght@0,6..96,400..600;1,6..96,400..600&family=Jost:wght@300;400;500;600&display=swap">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.1/src/regular/style.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.1/src/fill/style.css">
<link rel="stylesheet" href="{{ \App\Support\StorefrontAsset::url('css/base.css') }}">
<link rel="stylesheet" href="{{ \App\Support\StorefrontAsset::url('css/portal.css') }}">
@livewireStyles
</head>
<body class="no-js" data-page="portal">

<div id="rj-header"></div>

<div id="smooth-wrapper">
<div id="smooth-content">
<main class="portal">
{{ $slot }}
</main>
<div id="rj-footer"></div>
</div>
</div>

<script>window.RJ_DATA = @json($rj, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG);</script>
<script src="{{ \App\Support\StorefrontAsset::url('js/gsap-bundle.js') }}"></script>
<script src="{{ \App\Support\StorefrontAsset::url('js/core.js') }}"></script>
<script>window.RJ && RJ.boot({ smooth: false, keepHeader: true });</script>
@livewireScripts
</body>
</html>
