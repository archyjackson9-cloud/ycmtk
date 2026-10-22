{{-- Filament remembers "sidebar open" and defaults to open, which on a phone
     means the drawer covers the page on every load. Close it below the lg
     breakpoint once Alpine and Filament's sidebar store are ready. --}}
<script>
    document.addEventListener('alpine:initialized', () => {
        if (window.matchMedia('(max-width: 1023px)').matches && window.Alpine?.store('sidebar')) {
            window.Alpine.store('sidebar').isOpen = false;
        }
    });
</script>
