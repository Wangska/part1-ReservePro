// Property Details Modal
let currentPropertyId = null;
let bookedDatesSet = new Set();

function openPropertyModal(propertyId) {
    currentPropertyId = propertyId;
    bookedDatesSet = new Set();
    const modal = document.getElementById('propertyModal');
    const modalContent = document.getElementById('propertyModalContent');
    
    // Show modal
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
    
    // Show loading state
    modalContent.innerHTML = `
        <div style="text-align: center; padding: 40px; color: #B8B8B8;">
            <div style="font-size: 48px; margin-bottom: 16px;">⏳</div>
            <p>Loading property details...</p>
        </div>
    `;
    
    // Fetch property details
    fetch('get-property-details.php?id=' + propertyId)
        .then(response => {
            console.log('Response status:', response.status);
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            console.log('Property data:', data);
            if (data.error) {
                modalContent.innerHTML = `
                    <div style="text-align: center; padding: 40px; color: #FF4444;">
                        <div style="font-size: 48px; margin-bottom: 16px;">❌</div>
                        <p>${data.error}</p>
                    </div>
                `;
                return;
            }
            
            currentPropertyId = data.id;
            fetchBookedDatesAndRender(data);
        })
        .catch(error => {
            console.error('Fetch error:', error);
            modalContent.innerHTML = `
                <div style="text-align: center; padding: 40px; color: #FF4444;">
                    <div style="font-size: 48px; margin-bottom: 16px;">❌</div>
                    <p>Failed to load property details</p>
                    <p style="font-size: 12px; color: #999; margin-top: 10px;">Error: ${error.message}</p>
                    <p style="font-size: 12px; color: #999;">Check browser console for details</p>
                </div>
            `;
        });
}

function closePropertyModal() {
    const modal = document.getElementById('propertyModal');
    modal.style.display = 'none';
    document.body.style.overflow = 'auto';
}

function fetchBookedDatesAndRender(property) {
    if (!property.id) {
        renderPropertyDetails(property, []);
        return;
    }
    const timeoutMs = 8000;
    const timeoutPromise = new Promise((_, reject) =>
        setTimeout(() => reject(new Error('timeout')), timeoutMs)
    );
    Promise.race([
        fetch('get-booked-dates.php?property_id=' + property.id).then(r => r.json()),
        timeoutPromise
    ])
        .then(data => {
            const dates = (data && data.dates) ? data.dates : [];
            bookedDatesSet = new Set(dates);
            renderPropertyDetails(property, dates);
        })
        .catch(() => {
            renderPropertyDetails(property, []);
        });
}

function renderPropertyDetails(property, bookedDates) {
    const modalContent = document.getElementById('propertyModalContent');
    bookedDates = bookedDates || [];
    bookedDatesSet = new Set(bookedDates);
    
    // Build photos HTML
    let photosHTML = '';
    if (property.photos && property.photos.length > 0) {
        photosHTML = property.photos.map((photo, index) => `
            <div class="gallery-item ${index === 0 ? 'gallery-main' : ''}" style="${index >= 5 ? 'display: none;' : ''}">
                <img src="${photo.photo_url}" alt="Property photo" onerror="this.src='https://images.unsplash.com/photo-1564013799919-ab600027ffc6?w=800'" style="width: 100%; height: 100%; object-fit: cover;">
            </div>
        `).join('');
    } else {
        photosHTML = `
            <div class="gallery-item gallery-main">
                <img src="https://images.unsplash.com/photo-1564013799919-ab600027ffc6?w=800" alt="Property" style="width: 100%; height: 100%; object-fit: cover;">
            </div>
        `;
    }
    
    // Build amenities HTML
    let amenitiesHTML = '';
    if (property.amenities && property.amenities.length > 0) {
        amenitiesHTML = `
            <div class="info-section" style="padding: 24px 0; border-bottom: 1px solid #3A3A3A;">
                <h2 style="font-size: 20px; font-weight: 700; color: #FFFFFF; margin-bottom: 16px;">Amenities</h2>
                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px;">
                    ${property.amenities.map(amenity => `
                        <div style="display: flex; align-items: center; gap: 12px; padding: 10px; background: #2C2C2C; border-radius: 8px; color: #E0E0E0;">
                            <span style="font-size: 18px;">${amenity.icon || '✓'}</span>
                            <span>${amenity.name}</span>
                        </div>
                    `).join('')}
                </div>
            </div>
        `;
    }
    
    const html = `
        <div style="padding: 24px;">
            <!-- Header -->
            <div style="margin-bottom: 24px;">
                <h1 style="font-size: 28px; font-weight: 700; color: #FFFFFF; margin-bottom: 12px;">${property.title}</h1>
                <div class="property-location-click" style="font-size: 16px; color: #B8B8B8; display: flex; align-items: center; gap: 8px; cursor: pointer; text-decoration: underline; text-underline-offset: 4px;" title="Click to show on map">
                    📍 ${property.city}, ${property.country}
                </div>
            </div>

            <!-- Photo Gallery -->
            <div style="display: grid; grid-template-columns: 2fr 1fr 1fr; grid-template-rows: 200px 200px; gap: 8px; border-radius: 12px; overflow: hidden; margin-bottom: 32px;">
                ${photosHTML}
            </div>

            <!-- Content Grid -->
            <div style="display: grid; grid-template-columns: 1.5fr 1fr; gap: 32px;">
                <!-- Left Column -->
                <div>
                    <!-- Features -->
                    <div style="padding: 24px 0; border-bottom: 1px solid #3A3A3A;">
                        <h2 style="font-size: 20px; font-weight: 700; color: #FFFFFF; margin-bottom: 16px;">Property Features</h2>
                        <div style="display: flex; gap: 24px; flex-wrap: wrap;">
                            <div style="display: flex; align-items: center; gap: 10px; color: #E0E0E0; font-size: 16px;">
                                <span style="font-size: 22px;">🛏️</span>
                                <span>${property.bedrooms} Bedroom${property.bedrooms > 1 ? 's' : ''}</span>
                            </div>
                            <div style="display: flex; align-items: center; gap: 10px; color: #E0E0E0; font-size: 16px;">
                                <span style="font-size: 22px;">🚿</span>
                                <span>${property.bathrooms} Bathroom${property.bathrooms > 1 ? 's' : ''}</span>
                            </div>
                            <div style="display: flex; align-items: center; gap: 10px; color: #E0E0E0; font-size: 16px;">
                                <span style="font-size: 22px;">👥</span>
                                <span>${property.max_guests} Guest${property.max_guests > 1 ? 's' : ''}</span>
                            </div>
                            <div style="display: flex; align-items: center; gap: 10px; color: #E0E0E0; font-size: 16px;">
                                <span style="font-size: 22px;">🏠</span>
                                <span>${property.property_type.charAt(0).toUpperCase() + property.property_type.slice(1)}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Description -->
                    <div style="padding: 24px 0; border-bottom: 1px solid #3A3A3A;">
                        <h2 style="font-size: 20px; font-weight: 700; color: #FFFFFF; margin-bottom: 16px;">About this place</h2>
                        <p style="color: #E0E0E0; line-height: 1.7; font-size: 15px;">${property.description.replace(/\n/g, '<br>')}</p>
                    </div>

                    <!-- Amenities -->
                    ${amenitiesHTML}

                    <!-- Host Info -->
                    <div style="padding: 24px 0;">
                        <h2 style="font-size: 20px; font-weight: 700; color: #FFFFFF; margin-bottom: 16px;">Hosted by</h2>
                        <div style="background: linear-gradient(135deg, #2C1810 0%, #3E2723 50%, #0F0F0F 100%); padding: 20px; border-radius: 12px; border: 2px solid #D4A574;">
                            <div style="display: flex; align-items: center; gap: 16px; margin-bottom: 16px;">
                                <div style="width: 50px; height: 50px; border-radius: 50%; background: linear-gradient(135deg, #D4A574, #B8935E); display: flex; align-items: center; justify-content: center; font-size: 20px; font-weight: 700; color: #FFFFFF;">
                                    ${property.first_name.charAt(0)}${property.last_name.charAt(0)}
                                </div>
                                <div>
                                    <h3 style="font-size: 16px; font-weight: 600; color: #FFFFFF; margin-bottom: 4px;">${property.first_name} ${property.last_name}</h3>
                                    <p style="color: #B8B8B8; font-size: 13px;">Property Host</p>
                                </div>
                            </div>
                            <button onclick="alert('Contact feature coming soon!')" style="width: 100%; padding: 10px; background: transparent; color: #D4A574; border: 2px solid #D4A574; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; transition: all 0.3s ease;">Contact Host</button>
                        </div>
                    </div>
                </div>

                <!-- Right Column - Booking Card -->
                <div>
                    <div style="background: #1F1F1F; padding: 20px; border-radius: 12px; border: 2px solid #3A3A3A; position: sticky; top: 20px;">
                        <div style="font-size: 28px; font-weight: 700; color: #D4A574; margin-bottom: 4px;">₱${parseFloat(property.price_per_night).toLocaleString('en-PH', {minimumFractionDigits: 2})}</div>
                        <div style="color: #B8B8B8; font-size: 13px; margin-bottom: 20px;">per night</div>
                        
                        <form id="bookingForm" style="display: flex; flex-direction: column; gap: 14px;">
                            <div id="bookingCalendarSection" style="margin-bottom: 8px;">
                                <label style="display: block; color: #E0E0E0; font-size: 13px; font-weight: 600; margin-bottom: 8px;">Availability — booked dates in red are not available</label>
                                <div id="bookingCalendar" style="display: grid; grid-template-columns: repeat(7, 1fr); gap: 4px; font-size: 12px;"></div>
                            </div>
                            <div>
                                <label style="display: block; color: #E0E0E0; font-size: 13px; font-weight: 600; margin-bottom: 6px;">Check-in</label>
                                <input type="date" id="modal_check_in" required min="${new Date().toISOString().split('T')[0]}" style="width: 100%; padding: 10px; background: #2C2C2C; border: 2px solid #3A3A3A; border-radius: 8px; color: #FFFFFF; font-size: 13px;">
                            </div>
                            <div>
                                <label style="display: block; color: #E0E0E0; font-size: 13px; font-weight: 600; margin-bottom: 6px;">Check-out</label>
                                <input type="date" id="modal_check_out" required style="width: 100%; padding: 10px; background: #2C2C2C; border: 2px solid #3A3A3A; border-radius: 8px; color: #FFFFFF; font-size: 13px;">
                            </div>
                            <div>
                                <label style="display: block; color: #E0E0E0; font-size: 13px; font-weight: 600; margin-bottom: 6px;">Guests</label>
                                <input type="number" id="modal_guests" value="1" min="1" max="${property.max_guests}" required style="width: 100%; padding: 10px; background: #2C2C2C; border: 2px solid #3A3A3A; border-radius: 8px; color: #FFFFFF; font-size: 13px;">
                            </div>
                            
                            <div id="bookingSummary" style="padding: 14px; background: #2C2C2C; border-radius: 8px; margin: 10px 0; display: none;">
                                <div style="display: flex; justify-content: space-between; margin-bottom: 10px; color: #E0E0E0; font-size: 14px;">
                                    <span>₱${parseFloat(property.price_per_night).toLocaleString('en-PH', {minimumFractionDigits: 2})} × <span id="modal_nights">0</span> night<span id="modal_nightsPlural"></span></span>
                                    <span id="modal_subtotal">₱0.00</span>
                                </div>
                                <div style="display: flex; justify-content: space-between; margin-bottom: 10px; color: #E0E0E0; font-size: 14px;">
                                    <span>Service fee (10%)</span>
                                    <span id="modal_serviceFee">₱0.00</span>
                                </div>
                                <div style="display: flex; justify-content: space-between; padding-top: 10px; border-top: 1px solid #3A3A3A; font-size: 16px; font-weight: 700; color: #D4A574;">
                                    <span>Total</span>
                                    <span id="modal_total">₱0.00</span>
                                </div>
                            </div>
                            
                            <button type="submit" class="modal-btn" style="width: 100%; padding: 14px; background: linear-gradient(135deg, #D4A574, #B8935E); color: #FFFFFF; border: none; border-radius: 8px; font-size: 15px; font-weight: 700; cursor: pointer;">Reserve Now</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    modalContent.innerHTML = html;
    
    // Build availability calendar (booked dates in different color, non-clickable)
    renderBookingCalendar(bookedDates);
    
    // Make location clickable to show map
    const locationEl = modalContent.querySelector('.property-location-click');
    if (locationEl) {
        locationEl.addEventListener('click', function(e) {
            e.preventDefault();
            showPropertyMap(property);
        });
    }
    
    // Setup booking calculator after DOM is updated
    setTimeout(() => {
        setupBookingCalculator(property.price_per_night, bookedDates);
    }, 100);
}

function renderBookingCalendar(bookedDates) {
    const container = document.getElementById('bookingCalendar');
    if (!container) return;
    const bookedSet = new Set(bookedDates || []);
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const monthsToShow = 2;
    const dayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    
    let html = '';
    for (let m = 0; m < monthsToShow; m++) {
        const d = new Date(today.getFullYear(), today.getMonth() + m, 1);
        const monthTitle = d.toLocaleString('default', { month: 'long', year: 'numeric' });
        html += `<div style="grid-column: 1 / -1; font-weight: 600; color: #E0E0E0; margin-top: ${m > 0 ? '12px' : '0'}; margin-bottom: 4px;">${monthTitle}</div>`;
        dayNames.forEach(day => {
            html += `<div style="color: #888; text-align: center;">${day}</div>`;
        });
        const firstDay = d.getDay();
        const daysInMonth = new Date(d.getFullYear(), d.getMonth() + 1, 0).getDate();
        for (let i = 0; i < firstDay; i++) {
            html += '<div></div>';
        }
        for (let day = 1; day <= daysInMonth; day++) {
            const dateStr = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
            const dateObj = new Date(d.getFullYear(), d.getMonth(), day);
            const isPast = dateObj < today;
            const isBooked = bookedSet.has(dateStr);
            const disabled = isPast || isBooked;
            const bg = isBooked ? '#6b2d2d' : (isPast ? '#252525' : '#2C2C2C');
            const cursor = disabled ? 'not-allowed' : 'pointer';
            const opacity = disabled ? '0.7' : '1';
            html += `<div data-date="${dateStr}" class="calendar-day ${isBooked ? 'calendar-day-booked' : ''}" style="padding: 6px; text-align: center; background: ${bg}; border-radius: 6px; color: #E0E0E0; cursor: ${cursor}; opacity: ${opacity}; pointer-events: ${disabled ? 'none' : 'auto'};" title="${isBooked ? 'Booked' : (isPast ? 'Past' : 'Available')}">${day}</div>`;
        }
        const totalCells = firstDay + daysInMonth;
        const pad = (7 - totalCells % 7) % 7;
        for (let i = 0; i < pad; i++) html += '<div></div>';
    }
    container.innerHTML = html;
    
    // Click on available day to set check-in or check-out
    container.querySelectorAll('.calendar-day:not(.calendar-day-booked)').forEach(cell => {
        if (cell.style.pointerEvents === 'none') return;
        cell.addEventListener('click', function() {
            const dateStr = this.getAttribute('data-date');
            const checkIn = document.getElementById('modal_check_in');
            const checkOut = document.getElementById('modal_check_out');
            if (!checkIn || !checkOut) return;
            if (!checkIn.value || (checkIn.value && checkOut.value)) {
                checkIn.value = dateStr;
                const next = new Date(dateStr);
                next.setDate(next.getDate() + 1);
                checkOut.value = next.toISOString().split('T')[0];
                checkOut.min = checkOut.value;
            } else {
                checkOut.value = dateStr;
            }
            checkIn.dispatchEvent(new Event('change', { bubbles: true }));
            checkOut.dispatchEvent(new Event('change', { bubbles: true }));
        });
    });
}

function showPropertyMap(property) {
    const modalContent = document.getElementById('propertyModalContent');
    let mapContainer = document.getElementById('propertyMapContainer');
    if (mapContainer) {
        mapContainer.style.display = mapContainer.style.display === 'none' ? 'block' : 'none';
        if (mapContainer.style.display === 'block' && window.propertyMap && window.propertyMapMarker) {
            window.propertyMap.invalidateSize();
            window.propertyMap.setView(window.propertyMapMarker.getLatLng(), 16);
            return;
        }
    } else {
        mapContainer = document.createElement('div');
        mapContainer.id = 'propertyMapContainer';
        mapContainer.style.cssText = 'display: block; height: 280px; margin-top: 16px; border-radius: 12px; overflow: hidden; background: #2C2C2C;';
        const locationEl = modalContent.querySelector('.property-location-click');
        const insertAfter = locationEl && locationEl.closest('div');
        if (insertAfter) {
            insertAfter.parentNode.insertBefore(mapContainer, insertAfter.nextElementSibling);
        } else {
            const paddingDiv = modalContent.querySelector('div[style*="padding: 24px"]');
            if (paddingDiv) paddingDiv.appendChild(mapContainer);
        }
    }
    mapContainer.innerHTML = '<div id="propertyMapDiv" style="width: 100%; height: 100%;"></div>';

    // Prefer exact coordinates from the database if available
    const hasCoords = property.latitude && property.longitude;
    if (hasCoords) {
        const lat = parseFloat(property.latitude);
        const lng = parseFloat(property.longitude);
        if (!window.L) {
            const script = document.createElement('script');
            script.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
            script.onload = () => initMap(mapContainer, lat, lng, property);
            document.head.appendChild(script);
            const link = document.createElement('link');
            link.rel = 'stylesheet';
            link.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
            document.head.appendChild(link);
        } else {
            initMap(mapContainer, lat, lng, property);
        }
        return;
    }

    // Otherwise, fall back to geocoding the address (approximate)
    // Build query with region hint so e.g. "Lapu-Lapu City" maps to Cebu, not Manila
    const city = (property.city || '').trim();
    const country = (property.country || 'Philippines').trim();
    let searchQuery = [property.address, city, country].filter(Boolean).join(', ');
    if (!searchQuery) searchQuery = city + ', ' + country;
    // Philippine cities that need a region hint so Nominatim returns the right island
    const cityLower = city.toLowerCase();
    if (country.toLowerCase().includes('philippines')) {
        if (cityLower.includes('lapu-lapu') || cityLower.includes('lapu lapu')) searchQuery = (property.address ? property.address + ', ' : '') + (city + ', Cebu, ' + country);
        else if (cityLower.includes('cebu city') || cityLower === 'cebu') searchQuery = (property.address ? property.address + ', ' : '') + (city + ', Cebu, ' + country);
        else if (cityLower.includes('davao')) searchQuery = (property.address ? property.address + ', ' : '') + (city + ', Davao del Sur, ' + country);
        else if (cityLower.includes('iloilo')) searchQuery = (property.address ? property.address + ', ' : '') + (city + ', Iloilo, ' + country);
    }
    const geocodeUrl = 'https://nominatim.openstreetmap.org/search?format=json&q=' + encodeURIComponent(searchQuery) + '&limit=5';
    fetch(geocodeUrl, { headers: { 'Accept': 'application/json', 'User-Agent': 'ServeProPropertyMap/1.0' } })
        .then(r => r.json())
        .then(results => {
            // Prefer result that matches the intended region (avoid Manila when searching for Cebu, etc.)
            let lat = 14.5995, lng = 120.9842;
            if (results && results.length > 0) {
                const cebuBounds = { latMin: 10.0, latMax: 11.0, lngMin: 123.5, lngMax: 124.2 };   // Cebu island
                const manilaBounds = { latMin: 14.4, latMax: 14.8, lngMin: 120.9, lngMax: 121.1 }; // Manila
                const pick = results.find(r => {
                    const la = parseFloat(r.lat), ln = parseFloat(r.lon);
                    if (cityLower.includes('lapu-lapu') || cityLower.includes('cebu')) return la >= cebuBounds.latMin && la <= cebuBounds.latMax && ln >= cebuBounds.lngMin && ln <= cebuBounds.lngMax;
                    return true;
                }) || results[0];
                lat = parseFloat(pick.lat);
                lng = parseFloat(pick.lon);
            }
            if (!window.L) {
                const script = document.createElement('script');
                script.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
                script.onload = () => initMap(mapContainer, lat, lng, property);
                document.head.appendChild(script);
                const link = document.createElement('link');
                link.rel = 'stylesheet';
                link.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
                document.head.appendChild(link);
            } else {
                initMap(mapContainer, lat, lng, property);
            }
        })
        .catch(() => {
            const lat = 14.5995;
            const lng = 120.9842;
            if (window.L) initMap(mapContainer, lat, lng, property);
            else mapContainer.innerHTML = '<p style="padding: 20px; color: #B8B8B8;">Map could not be loaded. Location: ' + (property.city + ', ' + property.country) + '</p>';
        });
}

function initMap(container, lat, lng, property) {
    const L = window.L;
    const div = document.getElementById('propertyMapDiv');
    if (!div) return;
    const map = L.map(div).setView([lat, lng], 16);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '© OpenStreetMap' }).addTo(map);
    const marker = L.marker([lat, lng]).addTo(map);
    marker.bindPopup('<strong>' + (property.title || '') + '</strong><br>' + (property.city + ', ' + property.country)).openPopup();
    window.propertyMap = map;
    window.propertyMapMarker = marker;
}

function setupBookingCalculator(pricePerNight, bookedDates) {
    const checkIn = document.getElementById('modal_check_in');
    const checkOut = document.getElementById('modal_check_out');
    const bookingSummary = document.getElementById('bookingSummary');
    const bookedSet = new Set(bookedDates || []);
    
    // Check if elements exist
    if (!checkIn || !checkOut || !bookingSummary) {
        console.error('Booking form elements not found');
        return;
    }
    
    function isRangeBlocked(startStr, endStr) {
        const start = new Date(startStr);
        const end = new Date(endStr);
        for (let d = new Date(start); d < end; d.setDate(d.getDate() + 1)) {
            const dateStr = d.toISOString().split('T')[0];
            if (bookedSet.has(dateStr)) return true;
        }
        return false;
    }
    
    function showBookedError() {
        const summary = document.getElementById('bookingSummary');
        if (summary) {
            const msg = summary.querySelector('.booking-blocked-msg');
            if (msg) msg.remove();
            const p = document.createElement('p');
            p.className = 'booking-blocked-msg';
            p.style.cssText = 'color: #e57373; font-size: 13px; margin-top: 8px;';
            p.textContent = 'Some selected dates are already booked. Please choose different dates.';
            summary.appendChild(p);
        }
    }
    
    function removeBookedError() {
        const summary = document.getElementById('bookingSummary');
        if (summary) {
            const msg = summary.querySelector('.booking-blocked-msg');
            if (msg) msg.remove();
        }
    }
    
    function calculatePrice() {
        removeBookedError();
        if (checkIn.value && checkOut.value) {
            const start = new Date(checkIn.value);
            const end = new Date(checkOut.value);
            const nights = Math.ceil((end - start) / (1000 * 60 * 60 * 24));
            
            if (nights > 0) {
                if (isRangeBlocked(checkIn.value, checkOut.value)) {
                    showBookedError();
                }
                const subtotal = nights * pricePerNight;
                const serviceFee = subtotal * 0.10;
                const total = subtotal + serviceFee;
                
                document.getElementById('modal_nights').textContent = nights;
                document.getElementById('modal_nightsPlural').textContent = nights > 1 ? 's' : '';
                document.getElementById('modal_subtotal').textContent = '₱' + subtotal.toLocaleString('en-PH', {minimumFractionDigits: 2});
                document.getElementById('modal_serviceFee').textContent = '₱' + serviceFee.toLocaleString('en-PH', {minimumFractionDigits: 2});
                document.getElementById('modal_total').textContent = '₱' + total.toLocaleString('en-PH', {minimumFractionDigits: 2});
                
                bookingSummary.style.display = 'block';
            } else {
                bookingSummary.style.display = 'none';
            }
        }
    }
    
    checkIn.addEventListener('change', function() {
        const nextDay = new Date(this.value);
        nextDay.setDate(nextDay.getDate() + 1);
        checkOut.min = nextDay.toISOString().split('T')[0];
        calculatePrice();
    });
    
    checkOut.addEventListener('change', calculatePrice);
    
    // Handle booking form
    const bookingForm = document.getElementById('bookingForm');
    bookingForm.addEventListener('submit', function(e) {
        e.preventDefault();
        if (checkIn.value && checkOut.value && isRangeBlocked(checkIn.value, checkOut.value)) {
            alert('Some selected dates are already booked. Please choose different dates.');
            return;
        }
        alert('Booking feature coming soon! Total: ' + document.getElementById('modal_total').textContent);
    });
}

// Close modal on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closePropertyModal();
    }
});
