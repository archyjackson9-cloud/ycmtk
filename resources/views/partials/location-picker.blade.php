@props([
    'mapId' => 'location-map-'.Str::random(8),
    'latName' => 'latitude',
    'lngName' => 'longitude',
    'initialLat' => null,
    'initialLng' => null,
    'label' => 'Or Pin Your Exact Location',
    'helpText' => 'Skip the zone above and drop a pin instead - we dispatch straight to the exact spot.',
])

@php
    $googleMapsKey = config('services.google_maps.key');
@endphp

@if(!$googleMapsKey)
    {{-- No API key configured yet - show what's missing instead of a
         silently broken map (Google's script errors out on a blank key). --}}
    <div class="space-y-2">
        <label class="block text-xs font-bold text-gray-700">{{ $label }}</label>
        <div class="w-full rounded-2xl liquid-input px-4 py-6 text-center">
            <p class="text-xs font-semibold text-gray-500">Location pinning isn't set up yet.</p>
            <p class="text-[11px] text-gray-400 mt-1">Add a Google Maps API key (GOOGLE_MAPS_API_KEY) to enable it.</p>
        </div>
        <input type="hidden" name="{{ $latName }}" value="{{ $initialLat }}">
        <input type="hidden" name="{{ $lngName }}" value="{{ $initialLng }}">
    </div>
@else
    @once
        @push('scripts')
            <script>
                // Resolved once Google's SDK calls back, so any number of
                // picker instances on the page can safely wait on it
                // regardless of load order.
                window.__googleMapsReady = new Promise(function (resolve) {
                    window.__resolveGoogleMapsReady = resolve;
                });
            </script>
            <script src="https://maps.googleapis.com/maps/api/js?key={{ $googleMapsKey }}&callback=__resolveGoogleMapsReady&loading=async" async defer></script>
        @endpush
    @endonce

    <div class="space-y-2">
        <div class="flex items-center justify-between gap-3">
            <label class="block text-xs font-bold text-gray-700">{{ $label }}</label>
            <button
                type="button"
                id="{{ $mapId }}-locate-btn"
                class="inline-flex items-center gap-1.5 text-[11px] font-bold text-brand-700 hover:text-brand-900 disabled:opacity-50 transition-colors"
            >
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                <span id="{{ $mapId }}-locate-label">Use my current location</span>
            </button>
        </div>

        @if($helpText)
            <p class="text-[11px] text-gray-400">{{ $helpText }}</p>
        @endif

        <div id="{{ $mapId }}" class="w-full h-56 sm:h-64 rounded-2xl liquid-input overflow-hidden"></div>

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
                var startPosition = {
                    lat: hasInitial ? initialLat : defaultLat,
                    lng: hasInitial ? initialLng : defaultLng,
                };

                var map = new google.maps.Map(document.getElementById(mapId), {
                    center: startPosition,
                    zoom: hasInitial ? 15 : 13,
                    mapTypeControl: false,
                    streetViewControl: false,
                    fullscreenControl: false,
                    clickableIcons: false,
                });

                var marker = new google.maps.Marker({
                    position: startPosition,
                    map: map,
                    draggable: true,
                });

                function setCoords(lat, lng) {
                    latInput.value = lat;
                    lngInput.value = lng;
                    coordsEl.textContent = 'Pinned: ' + lat.toFixed(5) + ', ' + lng.toFixed(5);
                }

                if (hasInitial) {
                    setCoords(initialLat, initialLng);
                }

                marker.addListener('dragend', function () {
                    var position = marker.getPosition();
                    setCoords(position.lat(), position.lng());
                });

                map.addListener('click', function (event) {
                    marker.setPosition(event.latLng);
                    setCoords(event.latLng.lat(), event.latLng.lng());
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
                            var here = { lat: lat, lng: lng };
                            marker.setPosition(here);
                            map.setCenter(here);
                            map.setZoom(16);
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
                // initially-hidden container) renders blank until told to
                // resize once that ancestor actually becomes visible.
                var detailsAncestor = document.getElementById(mapId).closest('details');
                if (detailsAncestor) {
                    detailsAncestor.addEventListener('toggle', function () {
                        if (detailsAncestor.open) {
                            setTimeout(function () {
                                google.maps.event.trigger(map, 'resize');
                                map.setCenter(startPosition);
                            }, 50);
                        }
                    });
                }
            }

            if (window.__googleMapsReady) {
                window.__googleMapsReady.then(boot);
            } else {
                window.addEventListener('load', function () {
                    window.__googleMapsReady.then(boot);
                });
            }
        })();
    </script>
@endif
