<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="theme-color" content="#151515">
<title>{{ $title ?? 'Radharani Jewellery Works' }}</title>
<link rel="icon" type="image/png" href="{{ asset('images/auth/mark.png') }}">
<link rel="apple-touch-icon" href="{{ asset('images/auth/mark.png') }}">
<link rel="preload" as="image" href="{{ asset('images/auth/kundan-choker.webp') }}" media="(min-width: 1024px)">
@vite(['resources/css/app.css', 'resources/js/app.js'])
@livewireStyles
</head>
<body class="font-sans antialiased text-ink_text-primary bg-surface-bg">
{{ $slot }}
@livewireScripts
</body>
</html>
