@php
    $record = $getRecord();
    $lat = (float) $record->delivery_latitude;
    $lng = (float) $record->delivery_longitude;
    $googleMapsKey = config('services.google_maps.key');
    $mapId = 'order-location-map-'.$record->id;
@endphp

@if(!$googleMapsKey)
    <div class="fi-section rounded-xl p-4 text-sm text-gray-500 dark:text-gray-400">
        Customer pinned a location, but Google Maps isn't configured yet - add GOOGLE_MAPS_API_KEY to .env to display it here.
        <span class="block mt-1 font-mono text-xs">{{ $lat }}, {{ $lng }}</span>
    </div>
@else
    <div class="space-y-2">
        <div id="{{ $mapId }}" class="w-full h-72 rounded-xl overflow-hidden"></div>
        <div class="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
            <span class="font-mono">{{ number_format($lat, 5) }}, {{ number_format($lng, 5) }}</span>
            <a
                href="https://www.google.com/maps/search/?api=1&query={{ $lat }},{{ $lng }}"
                target="_blank"
                rel="noopener"
                class="font-semibold text-primary-600 hover:text-primary-700 dark:text-primary-400"
            >
                Open in Google Maps ↗
            </a>
        </div>
    </div>

    <script>
        (function () {
            function boot() {
                var position = { lat: {{ $lat }}, lng: {{ $lng }} };
                var map = new google.maps.Map(document.getElementById('{{ $mapId }}'), {
                    center: position,
                    zoom: 15,
                    mapTypeControl: false,
                    streetViewControl: false,
                    fullscreenControl: true,
                });

                new google.maps.Marker({ position: position, map: map });
            }

            if (window.google && window.google.maps) {
                boot();
                return;
            }

            if (!window.__resolveOrderMapReady) {
                window.__orderMapReady = new Promise(function (resolve) {
                    window.__resolveOrderMapReady = resolve;
                });

                var script = document.createElement('script');
                script.src = 'https://maps.googleapis.com/maps/api/js?key={{ $googleMapsKey }}&callback=__resolveOrderMapReady&loading=async';
                script.async = true;
                script.defer = true;
                document.head.appendChild(script);
            }

            window.__orderMapReady.then(boot);
        })();
    </script>
@endif
