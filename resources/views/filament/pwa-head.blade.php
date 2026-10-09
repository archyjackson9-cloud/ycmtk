{{-- Makes the admin panel installable too, sharing the same manifest/icons
     and service worker as the storefront (PanelsRenderHook::HEAD_END) - the
     service worker only ever caches static assets/offline fallback, so it's
     safe to register here: every admin/Livewire request still just passes
     straight through to the network untouched (see public/sw.js). --}}
<link rel="manifest" href="/manifest.webmanifest">
<meta name="theme-color" content="#14532d">
<link rel="icon" type="image/png" sizes="192x192" href="/icons/icon-192.png">
<link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="CY-Market Admin">
<script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function () {
            navigator.serviceWorker.register('/sw.js').catch(function () {});
        });
    }
</script>
