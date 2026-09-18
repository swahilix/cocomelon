<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <script>
        window.dataLayer = window.dataLayer || [];
        window.gtag = window.gtag || function () { window.dataLayer.push(arguments); };
        window.addEventListener('load', function () {
            const loadAnalytics = function () {
                const script = document.createElement('script');
                script.async = true;
                script.src = 'https://www.googletagmanager.com/gtag/js?id=GT-WVG5CCB7';
                script.onload = function () {
                    window.gtag('js', new Date());
                    window.gtag('config', 'GT-WVG5CCB7');
                };
                document.head.appendChild(script);
            };

            if ('requestIdleCallback' in window) {
                window.requestIdleCallback(loadAnalytics, { timeout: 3000 });
            } else {
                window.setTimeout(loadAnalytics, 1500);
            }
        }, { once: true });
    </script>

    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#ff4d0a">
    <link rel="icon" type="image/png" href="/assets/prinx-icon.png">
    @if (request()->is('admin*'))
        <link href="https://iconsax.gitlab.io/i/icons.css" rel="stylesheet">
        <link href="https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/css/select2.min.css" rel="stylesheet">
        <link href="/assets/admin-barcodes.css" rel="stylesheet">
        <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/js/select2.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
    @endif
    @if (!empty($heroPreload['url']))
        <link
            rel="preload"
            as="image"
            href="{{ $heroPreload['url'] }}"
            @if (!empty($heroPreload['srcset'])) imagesrcset="{{ $heroPreload['srcset'] }}" @endif
            imagesizes="(max-width: 640px) calc(100vw - 16px), (max-width: 1280px) calc(100vw - 48px), 1280px"
            fetchpriority="high"
        >
    @endif

    @if (app()->environment('production') && request()->path() === '/')
        <style>{!! \Illuminate\Support\Facades\Vite::content('resources/css/storefront-bundle.css') !!}</style>
        @vite('resources/js/app.js')
    @else
        @vite(['resources/css/storefront-bundle.css', 'resources/js/app.js'])
    @endif
    @inertiaHead
</head>
<body class="font-sans antialiased bg-slate-50 text-slate-950">
    @inertia
</body>
</html>
