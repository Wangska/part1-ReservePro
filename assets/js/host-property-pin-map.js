/**
 * Draggable map pin for host add/edit property — syncs #latitude / #longitude.
 * Depends on Leaflet (loaded dynamically). Geocode via ../api/geocode-search.php
 */
(function () {
    'use strict';

    var DEFAULT_CENTER = [12.8797, 121.7740];
    var DEFAULT_ZOOM = 6;
    var ZOOM_PIN = 17;
    var REVERSE_DEBOUNCE_MS = 900;
    var reverseTimer = null;
    var lastReverseKey = '';
    var reverseInFlight = false;
    var GEOCODE_DEBOUNCE_MS = 650;
    var geocodeTimer = null;
    var geocodeInFlight = false;
    var suppressGeocodeUntil = 0;
    var lastGeocodeQuery = '';

    function geocodeApiUrl(q) {
        var path = window.location.pathname || '';
        var prefix = path.indexOf('/host/') !== -1 ? '../api/' : 'api/';
        return prefix + 'geocode-search.php?q=' + encodeURIComponent(q);
    }

    function reverseApiUrl(lat, lng) {
        var path = window.location.pathname || '';
        var prefix = path.indexOf('/host/') !== -1 ? '../api/' : 'api/';
        return prefix + 'reverse-geocode.php?lat=' + encodeURIComponent(lat) + '&lng=' + encodeURIComponent(lng);
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

    function setStatus(text) {
        var el = document.getElementById('hostPropertyPinReverseStatus');
        if (!el) return;
        el.textContent = text || '';
        el.style.opacity = text ? '1' : '0';
    }

    function pickCity(addr) {
        if (!addr) return '';
        return addr.city || addr.town || addr.village || addr.municipality || addr.county || addr.state || '';
    }

    function buildAddressLine(addr) {
        if (!addr) return '';
        var parts = [];
        // Prefer house_number + road if present
        var hn = addr.house_number || '';
        var road = addr.road || addr.footway || addr.path || '';
        var suburb = addr.suburb || addr.neighbourhood || addr.quarter || addr.hamlet || '';
        if (hn && road) parts.push(hn + ' ' + road);
        else if (road) parts.push(road);
        if (suburb) parts.push(suburb);
        // fallback: amenity/building
        var place = addr.building || addr.amenity || '';
        if (!parts.length && place) parts.push(place);
        return parts.join(', ');
    }

    function maybeReverseFill(latlng, addrEl, cityEl, countryEl) {
        if (!addrEl && !cityEl && !countryEl) return;
        if (!latlng) return;

        var lat = Number(latlng.lat);
        var lng = Number(latlng.lng);
        if (!Number.isFinite(lat) || !Number.isFinite(lng)) return;

        // Avoid spamming same point (rounded)
        var key = lat.toFixed(5) + ',' + lng.toFixed(5);
        if (key === lastReverseKey) return;

        if (reverseTimer) window.clearTimeout(reverseTimer);
        reverseTimer = window.setTimeout(function () {
            if (reverseInFlight) return;
            reverseInFlight = true;
            lastReverseKey = key;
            setStatus('Updating address…');
            fetch(reverseApiUrl(lat.toFixed(6), lng.toFixed(6)))
                .then(function (r) { return r.json(); })
                .then(function (payload) {
                    reverseInFlight = false;
                    if (!payload || payload.ok !== true || !payload.data) {
                        setStatus('');
                        return;
                    }
                    var data = payload.data;
                    var addr = data.address || {};
                    var line = buildAddressLine(addr) || (data.display_name ? String(data.display_name).split(',').slice(0, 2).join(',').trim() : '');
                    var city = pickCity(addr);
                    var country = addr.country || '';

                    // Fill fields (don’t wipe user-entered values if reverse returns empty)
                    // Prevent the "typing -> geocode" watcher from immediately re-geocoding what we just filled.
                    suppressGeocodeUntil = Date.now() + 1500;
                    if (addrEl && line) addrEl.value = line;
                    if (cityEl && city) cityEl.value = city;
                    if (countryEl && country) countryEl.value = country;
                    setStatus('Address updated from pin.');
                    window.setTimeout(function () { setStatus(''); }, 1800);
                })
                .catch(function () {
                    reverseInFlight = false;
                    setStatus('');
                });
        }, REVERSE_DEBOUNCE_MS);
    }

    function buildForwardQuery(addrEl, cityEl, countryEl) {
        var parts = [];
        var a = addrEl && addrEl.value ? addrEl.value.trim() : '';
        var c = cityEl && cityEl.value ? cityEl.value.trim() : '';
        var co = countryEl && countryEl.value ? countryEl.value.trim() : '';
        if (a) parts.push(a);
        if (c) parts.push(c);
        if (co) parts.push(co);
        return parts.join(', ');
    }

    function maybeForwardGeocode(map, L, applyLatLng, addrEl, cityEl, countryEl) {
        if (!addrEl && !cityEl && !countryEl) return;
        if (Date.now() < suppressGeocodeUntil) return;

        var q = buildForwardQuery(addrEl, cityEl, countryEl);
        // Require at least city+country or address+city to avoid useless queries
        var hasCity = cityEl && cityEl.value && cityEl.value.trim().length >= 2;
        var hasCountry = countryEl && countryEl.value && countryEl.value.trim().length >= 2;
        var hasAddr = addrEl && addrEl.value && addrEl.value.trim().length >= 4;
        if (!((hasCity && hasCountry) || (hasAddr && hasCity))) return;

        if (q === lastGeocodeQuery) return;
        lastGeocodeQuery = q;

        if (geocodeTimer) window.clearTimeout(geocodeTimer);
        geocodeTimer = window.setTimeout(function () {
            if (geocodeInFlight) return;
            geocodeInFlight = true;
            setStatus('Locating pin from address…');
            fetch(geocodeApiUrl(q))
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    geocodeInFlight = false;
                    if (data && data[0] && data[0].lat && data[0].lon) {
                        var ll = L.latLng(parseFloat(data[0].lat), parseFloat(data[0].lon));
                        // Don’t trigger reverse-fill immediately; user can fine-tune.
                        suppressGeocodeUntil = Date.now() + 800;
                        applyLatLng(ll);
                        map.setView(ll, ZOOM_PIN);
                        setStatus('');
                    } else {
                        setStatus('');
                    }
                })
                .catch(function () {
                    geocodeInFlight = false;
                    setStatus('');
                });
        }, GEOCODE_DEBOUNCE_MS);
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
        var addrEl = document.querySelector(opts.addressSel || '#address');
        var cityEl = document.querySelector(opts.citySel || '#city');
        var countryEl = document.querySelector(opts.countrySel || '#country');
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
                maybeReverseFill(ll, addrEl, cityEl, countryEl);
            }

            marker.on('dragend', function () {
                var ll = marker.getLatLng();
                syncInputs(ll, latEl, lngEl);
                maybeReverseFill(ll, addrEl, cityEl, countryEl);
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

            // Typing in address fields should move the pin (auto-follow).
            [addrEl, cityEl, countryEl].forEach(function (el) {
                if (!el) return;
                el.addEventListener('input', function () {
                    maybeForwardGeocode(map, L, applyLatLng, addrEl, cityEl, countryEl);
                });
                el.addEventListener('change', function () {
                    maybeForwardGeocode(map, L, applyLatLng, addrEl, cityEl, countryEl);
                });
            });
        });
    };
})();
