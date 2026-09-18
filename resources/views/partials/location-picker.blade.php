@props([
    'mapId' => 'location-map-'.Str::random(8),
    'latName' => 'latitude',
    'lngName' => 'longitude',
    'initialLat' => null,
    'initialLng' => null,
])

@once
    @push('styles')
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
    @endpush
    @push('scripts')
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    @endpush
@endonce

<div class="space-y-2">
    <div class="flex items-center justify-between gap-3">
        <label class="block text-xs font-bold text-gray-700">Pin Delivery Location <span class="text-gray-400 font-medium">(optional, helps our riders)</span></label>
        <button
            type="button"
            id="{{ $mapId }}-locate-btn"
            class="inline-flex items-center gap-1.5 text-[11px] font-bold text-brand-700 hover:text-brand-900 disabled:opacity-50 transition-colors"
        >
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            <span id="{{ $mapId }}-locate-label">Use my current location</span>
        </button>
    </div>

    <div id="{{ $mapId }}" class="w-full h-56 sm:h-64 rounded-2xl liquid-input overflow-hidden [&_.leaflet-control-attribution]:text-[9px]"></div>

    <p id="{{ $mapId }}-coords" class="text-[11px] text-gray-400"></p>
    <p id="{{ $mapId }}-error" class="text-[11px] text-rose-500"></p>

    <input type="hidden" name="{{ $latName }}" id="{{ $mapId }}-lat" value="{{ $initialLat }}">
    <input type="hidden" name="{{ $lngName }}" id="{{ $mapId }}-lng" value="{{ $initialLng }}">
</div>

<script>
    (function () {
        function boot() {
            var mapId = '{{ $mapId }}';
            var defaultLat = {{ config('cymarket.pilot_zone_lat') }};
            var defaultLng = {{ config('cymarket.pilot_zone_lng') }};
            var latInput = document.getElementById(mapId + '-lat');
            var lngInput = document.getElementById(mapId + '-lng');
            var coordsEl = document.getElementById(mapId + '-coords');
            var errorEl = document.getElementById(mapId + '-error');
            var locateBtn = document.getElementById(mapId + '-locate-btn');
            var locateLabel = document.getElementById(mapId + '-locate-label');

            var initialLat = parseFloat(latInput.value);
            var initialLng = parseFloat(lngInput.value);
            var hasInitial = !isNaN(initialLat) && !isNaN(initialLng);

            var map = L.map(mapId).setView(
                [hasInitial ? initialLat : defaultLat, hasInitial ? initialLng : defaultLng],
                hasInitial ? 15 : 13
            );

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap contributors',
            }).addTo(map);

            var marker = L.marker(
                [hasInitial ? initialLat : defaultLat, hasInitial ? initialLng : defaultLng],
                { draggable: true }
            ).addTo(map);

            function setCoords(lat, lng) {
                latInput.value = lat;
                lngInput.value = lng;
                coordsEl.textContent = 'Pinned: ' + lat.toFixed(5) + ', ' + lng.toFixed(5);
            }

            if (hasInitial) {
                setCoords(initialLat, initialLng);
            }

            marker.on('dragend', function () {
                var position = marker.getLatLng();
                setCoords(position.lat, position.lng);
            });

            map.on('click', function (event) {
                marker.setLatLng(event.latlng);
                setCoords(event.latlng.lat, event.latlng.lng);
            });

            locateBtn.addEventListener('click', function () {
                if (!navigator.geolocation) {
                    errorEl.textContent = 'Your browser does not support location services.';
                    return;
                }

                locateBtn.disabled = true;
                locateLabel.textContent = 'Locating…';
                errorEl.textContent = '';

                navigator.geolocation.getCurrentPosition(
                    function (position) {
                        var lat = position.coords.latitude;
                        var lng = position.coords.longitude;
                        marker.setLatLng([lat, lng]);
                        map.setView([lat, lng], 16);
                        setCoords(lat, lng);
                        locateBtn.disabled = false;
                        locateLabel.textContent = 'Use my current location';
                    },
                    function () {
                        errorEl.textContent = 'Could not access your location. Please drag the pin instead.';
                        locateBtn.disabled = false;
                        locateLabel.textContent = 'Use my current location';
                    },
                    { enableHighAccuracy: true, timeout: 10000 }
                );
            });

            // A map inside a collapsed `<details>` section (or any
            // initially-hidden container) renders at 0 size until told to
            // recalculate once that ancestor actually becomes visible.
            setTimeout(function () { map.invalidateSize(); }, 200);

            var detailsAncestor = document.getElementById(mapId).closest('details');
            if (detailsAncestor) {
                detailsAncestor.addEventListener('toggle', function () {
                    if (detailsAncestor.open) {
                        setTimeout(function () { map.invalidateSize(); }, 50);
                    }
                });
            }
        }

        if (window.L) {
            boot();
        } else {
            window.addEventListener('load', boot);
        }
    })();
</script>
