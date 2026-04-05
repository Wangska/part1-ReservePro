/**
 * Draggable map pin for host add/edit property — syncs #latitude / #longitude.
 * Depends on Leaflet (loaded dynamically). Geocode via ../api/geocode-search.php
 */
(function () {
    'use strict';

    var DEFAULT_CENTER = [12.8797, 121.7740];
    var DEFAULT_ZOOM = 6;
    var ZOOM_PIN = 17;

    function geocodeApiUrl(q) {
        var path = window.location.pathname || '';
        var prefix = path.indexOf('/host/') !== -1 ? '../api/' : 'api/';
        return prefix + 'geocode-search.php?q=' + encodeURIComponent(q);
    }

    function loadLeaflet(done) {
        if (window.L) {
            done();
            return;
        }
        var link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
        link.crossOrigin = '';
        document.head.appendChild(link);
        var s = document.createElement('script');
        s.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
        s.crossOrigin = '';
        s.onload = function () {
            done();
        };
        s.onerror = function () {
            done();
        };
        document.head.appendChild(s);
    }

    function parseCoord(el) {
        if (!el || el.value === undefined || el.value === '') {
            return null;
        }
        var v = parseFloat(String(el.value).replace(',', '.').trim());
        return Number.isFinite(v) ? v : null;
    }

    function syncInputs(latlng, latEl, lngEl) {
        latEl.value = latlng.lat.toFixed(6);
        lngEl.value = latlng.lng.toFixed(6);
    }

    /**
     * @param {Object} opts
     * @param {string} [opts.mapId]
     * @param {string} [opts.latSel]
     * @param {string} [opts.lngSel]
     * @param {string} [opts.geocodeBtnId]
     * @param {string} [opts.addressSel]
     * @param {string} [opts.citySel]
     * @param {string} [opts.countrySel]
     */
    window.initHostPropertyPinMap = function (opts) {
        opts = opts || {};
        var mapId = opts.mapId || 'hostPropertyPinMap';
        var mapEl = document.getElementById(mapId);
        var latEl = document.querySelector(opts.latSel || '#latitude');
        var lngEl = document.querySelector(opts.lngSel || '#longitude');
        var geocodeBtn = document.getElementById(opts.geocodeBtnId || 'hostPropertyPinGeocodeBtn');
        if (!mapEl || !latEl || !lngEl) {
            return;
        }

        loadLeaflet(function () {
            if (!window.L) {
                return;
            }
            var L = window.L;

            var lat = parseCoord(latEl);
            var lng = parseCoord(lngEl);
            var hasPin = lat !== null && lng !== null && (Math.abs(lat) > 1e-8 || Math.abs(lng) > 1e-8);
            var center = hasPin ? [lat, lng] : DEFAULT_CENTER.slice();
            var zoom = hasPin ? ZOOM_PIN : DEFAULT_ZOOM;

            var map = L.map(mapEl, { scrollWheelZoom: true }).setView(center, zoom);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
            }).addTo(map);

            var marker = L.marker(center, { draggable: true }).addTo(map);

            function applyLatLng(ll) {
                marker.setLatLng(ll);
                map.panTo(ll);
                syncInputs(ll, latEl, lngEl);
            }

            marker.on('dragend', function () {
                syncInputs(marker.getLatLng(), latEl, lngEl);
            });

            map.on('click', function (e) {
                applyLatLng(e.latlng);
            });

            function tryMoveFromInputs() {
                var la = parseCoord(latEl);
                var ln = parseCoord(lngEl);
                if (la !== null && ln !== null) {
                    applyLatLng(L.latLng(la, ln));
                    map.setView(L.latLng(la, ln), ZOOM_PIN);
                }
            }

            ['change', 'blur'].forEach(function (ev) {
                latEl.addEventListener(ev, tryMoveFromInputs);
                lngEl.addEventListener(ev, tryMoveFromInputs);
            });

            window.__hostPinMap = map;
            window.__hostPinMarker = marker;

            window.hostPropertyPinMapRefresh = function () {
                setTimeout(function () {
                    if (!map || !map.getContainer()) {
                        return;
                    }
                    map.invalidateSize();
                    var ll = marker.getLatLng();
                    if (ll) {
                        map.panTo(ll);
                    }
                }, 200);
            };

            setTimeout(function () {
                map.invalidateSize();
            }, 100);

            if (geocodeBtn) {
                geocodeBtn.addEventListener('click', function () {
                    var addr = document.querySelector(opts.addressSel || '#address');
                    var city = document.querySelector(opts.citySel || '#city');
                    var country = document.querySelector(opts.countrySel || '#country');
                    var parts = [];
                    if (addr && addr.value) {
                        parts.push(addr.value.trim());
                    }
                    if (city && city.value) {
                        parts.push(city.value.trim());
                    }
                    if (country && country.value) {
                        parts.push(country.value.trim());
                    }
                    var q = parts.join(', ');
                    if (!q) {
                        return;
                    }
                    geocodeBtn.disabled = true;
                    fetch(geocodeApiUrl(q))
                        .then(function (r) {
                            return r.json();
                        })
                        .then(function (data) {
                            geocodeBtn.disabled = false;
                            if (data && data[0] && data[0].lat && data[0].lon) {
                                var ll = L.latLng(parseFloat(data[0].lat, 10), parseFloat(data[0].lon, 10));
                                applyLatLng(ll);
                                map.setView(ll, ZOOM_PIN);
                            }
                        })
                        .catch(function () {
                            geocodeBtn.disabled = false;
                        });
                });
            }
        });
    };
})();
