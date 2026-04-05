// Property Details Modal
let currentPropertyId = null;
let bookedDatesSet = new Set();

function openPropertyModal(propertyId) {
    currentPropertyId = propertyId;
    bookedDatesSet = new Set();
    const modal = document.getElementById('propertyModal');
    const modalContent = document.getElementById('propertyModalContent');

    const prevSlideshow = document.getElementById('propertyModalSlideshow');
    if (prevSlideshow && typeof prevSlideshow._slideshowStop === 'function') {
        prevSlideshow._slideshowStop();
    }
    
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
    const slideshow = document.getElementById('propertyModalSlideshow');
    if (slideshow && typeof slideshow._slideshowStop === 'function') {
        slideshow._slideshowStop();
    }
    const modal = document.getElementById('propertyModal');
    modal.style.display = 'none';
    document.body.style.overflow = 'auto';
}

function setupReviewFormHandler() {
    const form = document.getElementById('reviewForm');
    const ratingInput = document.getElementById('reviewRating');
    const starsContainer = document.getElementById('reviewStars');
    const errorEl = document.getElementById('reviewError');

    if (!form || !ratingInput) return;

    if (starsContainer && !starsContainer.dataset.bound) {
        const applyRatingColor = (rating) => {
            Array.from(starsContainer.querySelectorAll('span')).forEach(span => {
                const v = parseInt(span.getAttribute('data-value') || '0', 10);
                if (v <= rating) {
                    span.classList.add('review-star-active');
                } else {
                    span.classList.remove('review-star-active');
                }
            });
        };

        // Click = set rating (red stars)
        starsContainer.addEventListener('click', (e) => {
            const target = e.target;
            const value = target && target.getAttribute && target.getAttribute('data-value');
            if (!value) return;
            const rating = parseInt(value, 10);
            ratingInput.value = String(rating);
            applyRatingColor(rating);
        });

        // Hover preview = temporarily show red up to hovered star
        starsContainer.addEventListener('mouseover', (e) => {
            const target = e.target;
            const value = target && target.getAttribute && target.getAttribute('data-value');
            if (!value) return;
            const rating = parseInt(value, 10);
            applyRatingColor(rating);
        });

        // When leaving the stars row, restore to actual selected rating
        starsContainer.addEventListener('mouseleave', () => {
            const current = parseInt(ratingInput.value || '0', 10);
            applyRatingColor(current);
        });

        starsContainer.dataset.bound = '1';
    }

    if (!form.dataset.bound) {
        form.addEventListener('submit', (e) => {
            e.preventDefault();
            if (!currentPropertyId) return;

            const rating = parseInt(ratingInput.value || '0', 10);
            const commentEl = document.getElementById('reviewComment');
            const comment = commentEl ? commentEl.value.trim() : '';

            if (rating < 1 || rating > 5) {
                if (errorEl) {
                    errorEl.textContent = 'Please select a rating between 1 and 5 stars.';
                    errorEl.style.display = 'block';
                }
                return;
            }
            if (!comment || comment.length < 10) {
                if (errorEl) {
                    errorEl.textContent = 'Please enter at least 10 characters in your review.';
                    errorEl.style.display = 'block';
                }
                return;
            }

            if (errorEl) {
                errorEl.textContent = '';
                errorEl.style.display = 'none';
            }

            const formData = new FormData();
            formData.append('property_id', String(currentPropertyId));
            formData.append('rating', String(rating));
            formData.append('comment', comment);

            fetch('submit-review.php', {
                method: 'POST',
                body: formData,
            })
                .then(r => r.json())
                .then(res => {
                    if (!res || res.error) {
                        if (errorEl) {
                            errorEl.textContent = res && res.error ? res.error : 'Failed to submit review.';
                            errorEl.style.display = 'block';
                        }
                        return;
                    }
                    // Simple behavior: close and reopen modal to refresh reviews/summary
                    closePropertyModal();
                    openPropertyModal(currentPropertyId);
                })
                .catch(() => {
                    if (errorEl) {
                        errorEl.textContent = 'Something went wrong while submitting your review.';
                        errorEl.style.display = 'block';
                    }
                });
        });
        form.dataset.bound = '1';
    }
}

/**
 * Guest property modal: prev/next, dots, thumbnails, autoplay (pauses on hover).
 */
function setupPropertyGallerySlideshow(urls) {
    if (!urls || urls.length === 0) return;
    const root = document.getElementById('propertyModalSlideshow');
    const img = document.getElementById('propertyModalMainPhoto');
    if (!root || !img) return;

    const n = urls.length;
    if (n <= 1) return;

    let index = 0;
    let timer = null;

    function stopAuto() {
        if (timer) {
            clearInterval(timer);
            timer = null;
        }
    }

    function startAuto() {
        stopAuto();
        timer = setInterval(function () {
            show(index + 1);
        }, 5000);
    }

    function updateChrome() {
        const curEl = root.querySelector('.property-slideshow-cur');
        const totalEl = root.querySelector('.property-slideshow-total');
        if (curEl) curEl.textContent = String(index + 1);
        if (totalEl) totalEl.textContent = String(n);
        root.querySelectorAll('[data-slideshow-dot]').forEach(function (d, i) {
            d.classList.toggle('is-active', i === index);
            d.setAttribute('aria-current', i === index ? 'true' : 'false');
        });
        root.querySelectorAll('.property-slideshow-thumb').forEach(function (t, i) {
            t.style.borderColor = i === index ? '#D4A574' : 'transparent';
            t.classList.toggle('is-active', i === index);
        });
    }

    function show(i) {
        index = ((i % n) + n) % n;
        img.src = urls[index];
        updateChrome();
    }

    root._slideshowStop = function () {
        stopAuto();
    };

    root.querySelector('[data-slideshow-prev]') &&
        root.querySelector('[data-slideshow-prev]').addEventListener('click', function () {
            show(index - 1);
            startAuto();
        });
    root.querySelector('[data-slideshow-next]') &&
        root.querySelector('[data-slideshow-next]').addEventListener('click', function () {
            show(index + 1);
            startAuto();
        });

    root.querySelectorAll('[data-slideshow-dot]').forEach(function (dot) {
        dot.addEventListener('click', function () {
            const i = parseInt(dot.getAttribute('data-index') || '0', 10);
            show(i);
            startAuto();
        });
    });

    root.querySelectorAll('.property-slideshow-thumb').forEach(function (thumb) {
        thumb.addEventListener('click', function () {
            const i = parseInt(thumb.getAttribute('data-thumb-index') || '0', 10);
            show(i);
            startAuto();
        });
    });

    root.addEventListener('mouseenter', stopAuto);
    root.addEventListener('mouseleave', startAuto);

    updateChrome();
    startAuto();
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
    
    const averageRating = typeof property.average_rating !== 'undefined' && property.average_rating !== null
        ? parseFloat(property.average_rating)
        : null;
    const reviewCount = typeof property.review_count !== 'undefined' && property.review_count !== null
        ? parseInt(property.review_count, 10)
        : 0;

    // Photos: slideshow (guest) — arrows, dots, thumbnails, autoplay
    const photosArray = Array.isArray(property.photos) ? property.photos : [];
    const fallbackPhoto = 'https://images.unsplash.com/photo-1564013799919-ab600027ffc6?w=1600&auto=format&fit=crop&q=80';
    const normalizePhotoUrl = (url) => {
        if (!url) return fallbackPhoto;
        if (typeof url === 'string' && url.startsWith('http')) return url;
        return String(url || '').replace(/^\/+/, '');
    };
    const galleryUrls = photosArray.length > 0
        ? photosArray.map(function (p) { return normalizePhotoUrl(p.photo_url); })
        : [normalizePhotoUrl(property.primary_photo || '')];
    const mainPhotoUrl = galleryUrls[0] || fallbackPhoto;
    const multiPhoto = galleryUrls.length > 1;
    const safeTitle = String(property.title || 'Property').replace(/"/g, '&quot;');
    const slideshowControls = multiPhoto
        ? `
            <button type="button" class="property-slideshow-btn property-slideshow-prev" data-slideshow-prev aria-label="Previous photo">‹</button>
            <button type="button" class="property-slideshow-btn property-slideshow-next" data-slideshow-next aria-label="Next photo">›</button>
            <div class="property-slideshow-meta" aria-live="polite">
                <div class="property-slideshow-dots" role="tablist" aria-label="Photos">
                    ${galleryUrls.map(function (_, idx) {
                        return '<button type="button" class="property-slideshow-dot' + (idx === 0 ? ' is-active' : '') + '" data-slideshow-dot data-index="' + idx + '" aria-label="Photo ' + (idx + 1) + '" aria-current="' + (idx === 0 ? 'true' : 'false') + '"></button>';
                    }).join('')}
                </div>
                <div class="property-slideshow-counter"><span class="property-slideshow-cur">1</span> / <span class="property-slideshow-total">${galleryUrls.length}</span></div>
            </div>`
        : '';
    const thumbnailsHTML = multiPhoto
        ? `
            <div class="property-modal-thumbnails-wrap" style="margin-top:12px; background:#1F1F1F; border-radius:12px; border:1px solid #3A3A3A; padding:14px;">
                <div style="font-size:14px; font-weight:600; color:#D4A574; margin-bottom:10px;">Photo gallery</div>
                <div class="property-modal-thumbnails" style="display:flex; gap:8px; overflow-x:auto; padding-bottom:4px;">
                    ${galleryUrls.map(function (thumbUrl, idx) {
                        const border = idx === 0 ? '#D4A574' : 'transparent';
                        return `
                            <div class="property-slideshow-thumb${idx === 0 ? ' is-active' : ''}" data-thumb-index="${idx}" style="flex:0 0 auto; border-radius:8px; overflow:hidden; border:2px solid ${border}; cursor:pointer;">
                                <img src="${thumbUrl}" alt="" style="width:110px; height:75px; object-fit:cover;" onerror="this.src='${fallbackPhoto}'">
                            </div>
                        `;
                    }).join('')}
                </div>
            </div>
        `
        : '';
    const photosHTML = `
        <div id="propertyModalSlideshow" class="property-modal-slideshow">
            <div class="property-modal-hero-image">
                <img id="propertyModalMainPhoto" src="${mainPhotoUrl}" alt="${safeTitle}" onerror="this.src='${fallbackPhoto}'">
                ${slideshowControls}
            </div>
            ${thumbnailsHTML}
        </div>
    `;
    
    // Build amenities HTML
    let amenitiesHTML = '';
    if (property.amenities && property.amenities.length > 0) {
        amenitiesHTML = `
            <div class="info-section" style="padding: 24px 0; border-bottom: 1px solid #3A3A3A;">
                <h2 style="font-size: 20px; font-weight: 700; color: #FFFFFF; margin-bottom: 16px;">Amenities</h2>
                <div class="amenities-pill-grid">
                    ${property.amenities.map(amenity => `
                        <div class="amenity-pill">
                            <div class="amenity-pill-icon">
                                <span>${(amenity.icon || '✓').trim().charAt(0) || '✓'}</span>
                            </div>
                            <div class="amenity-pill-label">${amenity.name}</div>
                        </div>
                    `).join('')}
                </div>
            </div>
        `;
    }
    
    const ratingSummaryHTML = (() => {
        if (averageRating && reviewCount > 0) {
            const rounded = averageRating.toFixed(1);
            const label = reviewCount === 1 ? 'review' : 'reviews';
            return `
                <div style="display:flex; align-items:center; gap:8px; margin-top:4px; font-size:14px;">
                    <span style="color:#FBBF24;">★</span>
                    <span style="color:#FBBF24; font-weight:600;">${rounded}</span>
                    <span style="color:#9CA3AF;">(${reviewCount} ${label})</span>
                </div>
            `;
        }
        return `
            <div style="margin-top:4px; font-size:14px; color:#6B7280;">
                No reviews yet
            </div>
        `;
    })();

    const paymongoOn = !!property.paymongo_available;
    const paymentMethodsHTML = paymongoOn
        ? `
                            <div id="modalPaymentSection" style="margin-top: 2px;">
                                <div style="display: block; color: #E0E0E0; font-size: 13px; font-weight: 600; margin-bottom: 8px;">Payment</div>
                                <div style="padding: 14px; background: #2C2C2C; border: 2px solid #D4A574; border-radius: 10px;">
                                    <div style="font-weight: 700; color: #FFFFFF; font-size: 14px; margin-bottom: 6px;">PayMongo checkout</div>
                                    <div style="font-size: 12px; color: #9CA3AF; line-height: 1.45;">After you confirm, you will be sent to PayMongo to pay securely (Card, GCash, Maya, GrabPay, and other methods enabled on your account).</div>
                                </div>
                            </div>`
        : `
                            <div id="modalPaymentSection" style="margin-top: 2px;">
                                <p style="margin: 0; font-size: 12px; color: #e57373; line-height: 1.5;">Online payment is not configured. Add your PayMongo keys in <code style="font-size:11px;color:#fca5a5;">config/paymongo.local.php</code> to enable bookings.</p>
                            </div>`;

    const html = `
        <div class="property-modal-inner" style="padding: 24px;">
            <!-- Header -->
            <div style="margin-bottom: 24px;">
                <h1 style="font-size: 28px; font-weight: 700; color: #FFFFFF; margin-bottom: 12px;">${property.title}</h1>
                ${ratingSummaryHTML}
                <div class="property-location-click" style="font-size: 16px; color: #B8B8B8; display: flex; align-items: center; gap: 8px; cursor: pointer; text-decoration: underline; text-underline-offset: 4px;" title="Click to show on map">
                    📍 ${property.city}, ${property.country}
                </div>
            </div>

            <!-- Top row: Gallery + Price/Booking (same level) -->
            <div class="property-modal-top-row" style="display: grid; grid-template-columns: 1.5fr 1fr; gap: 24px; align-items: start; margin-bottom: 32px;">
                <!-- Single hero image - fills entire left column, no grid -->
                <div class="property-modal-gallery">
                    ${photosHTML}
                </div>
                <!-- Price / Booking Card -->
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

                            ${paymentMethodsHTML}
                            
                            <button type="submit" class="modal-btn modal-reserve-submit" ${paymongoOn ? '' : 'disabled'} style="width: 100%; padding: 14px; background: linear-gradient(135deg, #D4A574, #B8935E); color: #FFFFFF; border: none; border-radius: 8px; font-size: 15px; font-weight: 700; cursor: ${paymongoOn ? 'pointer' : 'not-allowed'}; opacity: ${paymongoOn ? '1' : '0.5'};">${paymongoOn ? 'Continue to PayMongo checkout' : 'Booking unavailable'}</button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Content below: Features, Description, Amenities, Host -->
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

                    <!-- Reviews -->
                    ${renderReviewsSection(property)}

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
                            <form id="contactHostForm" class="contact-host-form" data-property-id="${property.id}" style="margin-top: 12px;">
                                <textarea name="message" id="contactHostMessage" placeholder="Ask ${property.first_name} about this property..." rows="3" required style="width: 100%; padding: 10px; background: #1a1a1a; border: 2px solid #3A3A3A; border-radius: 8px; color: #E0E0E0; font-size: 14px; resize: vertical; margin-bottom: 10px;"></textarea>
                                <div id="contactHostStatus" style="font-size: 13px; margin-bottom: 8px; min-height: 18px;"></div>
                                <button type="submit" id="contactHostSubmit" style="width: 100%; padding: 10px; background: #D4A574; color: #0F0F0F; border: none; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer;">Send message</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    modalContent.innerHTML = html;

    setupPropertyGallerySlideshow(galleryUrls);
    
    // Contact Host form: submit to API with real property id and message
    const contactForm = document.getElementById('contactHostForm');
    if (contactForm) {
        contactForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const propertyId = this.getAttribute('data-property-id');
            const messageEl = document.getElementById('contactHostMessage');
            const statusEl = document.getElementById('contactHostStatus');
            const submitBtn = document.getElementById('contactHostSubmit');
            const message = messageEl && messageEl.value ? messageEl.value.trim() : '';
            if (!message) return;
            if (!propertyId) {
                if (statusEl) statusEl.textContent = 'Error: property not found.';
                return;
            }
            if (statusEl) statusEl.textContent = '';
            if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = 'Sending...'; }
            const formData = new FormData();
            formData.append('property_id', propertyId);
            formData.append('message', message);
            var contactUrl = 'contact-host.php';
            if (window.location.pathname.indexOf('/') !== -1) {
                var pathParts = window.location.pathname.split('/');
                pathParts.pop();
                var base = pathParts.length ? pathParts.join('/') + '/' : '/';
                contactUrl = base + 'contact-host.php';
            }
            fetch(contactUrl, { method: 'POST', body: formData })
                .then(function(r) {
                    return r.text().then(function(text) {
                        try {
                            return { ok: r.ok, data: JSON.parse(text) };
                        } catch (e) {
                            return { ok: false, data: { success: false, error: r.ok ? 'Invalid response.' : (r.status === 302 || r.status === 301 ? 'Please sign in to contact the host.' : 'Something went wrong. Please try again.') } };
                        }
                    });
                })
                .then(function(result) {
                    if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Send message'; }
                    var data = result.data;
                    if (data.success) {
                        if (statusEl) { statusEl.style.color = '#22c55e'; statusEl.textContent = data.message || 'Message sent!'; }
                        if (messageEl) messageEl.value = '';
                    } else {
                        if (statusEl) { statusEl.style.color = '#e57373'; statusEl.textContent = data.error || 'Failed to send.'; }
                    }
                })
                .catch(function(err) {
                    if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Send message'; }
                    if (statusEl) { statusEl.style.color = '#e57373'; statusEl.textContent = 'Network error. Please check your connection and try again.'; }
                });
        });
    }
    
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
        setupBookingCalculator(property, bookedDates);
    }, 100);

    // Setup review stars & form after DOM is in place
    setupReviewFormHandler();
}

function renderReviewsSection(property) {
    const reviews = Array.isArray(property.reviews) ? property.reviews : [];
    const user = window.currentUser || null;
    const isGuest = user && user.role === 'guest';

    const reviewsListHTML = reviews.length > 0
        ? reviews.map(r => {
            const name = ((r.first_name || '') + ' ' + (r.last_name || '')).trim() || 'Guest';
            const createdAt = r.created_at ? new Date(r.created_at).toLocaleDateString() : '';
            const rating = parseInt(r.rating, 10) || 0;
            const stars = '★'.repeat(Math.max(0, Math.min(5, rating))) + '☆'.repeat(Math.max(0, 5 - rating));
            const safeComment = (r.comment || '').replace(/</g, '&lt;').replace(/>/g, '&gt;');
            return `
                <div style="padding:14px 0; border-bottom:1px solid #3A3A3A;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:4px;">
                        <div style="font-weight:600; color:#FFFFFF;">${name}</div>
                        <div style="font-size:12px; color:#9CA3AF;">${createdAt}</div>
                    </div>
                    <div style="font-size:14px; color:#FBBF24; margin-bottom:6px;">${stars}</div>
                    <div style="font-size:14px; color:#E5E7EB; line-height:1.5;">${safeComment}</div>
                </div>
            `;
        }).join('')
        : `
            <p style="color:#9CA3AF; font-size:14px; margin-bottom:0;">
                No reviews yet. ${isGuest ? 'Be the first to share your experience!' : 'Sign in as a guest to leave a review.'}
            </p>
        `;

    const formHTML = isGuest ? `
        <form id="reviewForm" style="margin-top:18px; display:flex; flex-direction:column; gap:10px;">
            <div>
                <label style="display:block; font-size:14px; color:#E5E7EB; font-weight:600; margin-bottom:6px;">Your rating</label>
                <div id="reviewStars" style="display:flex; gap:4px; font-size:22px; cursor:pointer;">
                    ${[1,2,3,4,5].map(i => `<span class="review-star" data-value="${i}">★</span>`).join('')}
                </div>
                <input type="hidden" id="reviewRating" name="rating" value="0">
            </div>
            <div>
                <label for="reviewComment" style="display:block; font-size:14px; color:#E5E7EB; font-weight:600; margin-bottom:6px;">Your review</label>
                <textarea id="reviewComment" name="comment" rows="3" style="width:100%; padding:10px; background:#111827; border:1px solid #374151; border-radius:8px; color:#F9FAFB; font-size:14px; resize:vertical;"></textarea>
            </div>
            <div id="reviewError" style="font-size:13px; color:#FCA5A5; display:none;"></div>
            <button type="submit" style="align-self:flex-start; padding:10px 18px; border-radius:999px; border:none; background:linear-gradient(135deg,#D4A574,#B8935E); color:#FFFFFF; font-weight:600; cursor:pointer; font-size:14px;">
                Submit review
            </button>
        </form>
    ` : `
        <p style="color:#9CA3AF; font-size:14px; margin-top:14px;">
            Sign in as a guest to leave a review.
        </p>
    `;

    return `
        <div style="padding:24px 0; border-top:1px solid #3A3A3A; border-bottom:1px solid #3A3A3A; margin-top:8px;">
            <h2 style="font-size:20px; font-weight:700; color:#FFFFFF; margin-bottom:12px;">Guest reviews</h2>
            <div>
                ${reviewsListHTML}
            </div>
            ${formHTML}
        </div>
    `;
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
        mapContainer.style.cssText = 'display: block; height: 420px; min-height: 380px; margin-top: 16px; border-radius: 12px; overflow: hidden; background: #2C2C2C;';
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

    const cityLower = ((property.city || '').trim()).toLowerCase();
    const addressLower = ((property.address || '').trim()).toLowerCase();
    const combined = (cityLower + ' ' + addressLower);
    const cebuBounds = { latMin: 10.0, latMax: 11.0, lngMin: 123.5, lngMax: 124.2 };
    const phBounds = { latMin: 4.5, latMax: 21, lngMin: 116, lngMax: 127 };
    const manilaBounds = { latMin: 14.4, latMax: 14.8, lngMin: 120.9, lngMax: 121.1 };
    const isManilaArea = /manila|quezon city|makati|caloocan|pasig|mandaluyong|marikina|las piñas|taguig|parañaque|paranaque|valenzuela|malabon|navotas|san juan|pateros|muntinlupa|metro manila|ncr/i.test(combined);
    const isLapuLapuOrCebu = cityLower.includes('lapu-lapu') || cityLower.includes('lapu lapu') || cityLower.includes('cebu') || cityLower.includes('talamban') || addressLower.includes('cebu city') || addressLower.includes('cebu') || addressLower.includes('talamban');

    // Use stored coordinates only if they're in the correct region (reject Manila coords when address is not Manila/NCR)
    const hasCoords = property.latitude && property.longitude;
    if (hasCoords) {
        const lat = parseFloat(property.latitude);
        const lng = parseFloat(property.longitude);
        const inManila = lat >= manilaBounds.latMin && lat <= manilaBounds.latMax && lng >= manilaBounds.lngMin && lng <= manilaBounds.lngMax;
        const wrongRegion = inManila && !isManilaArea;
        if (!wrongRegion) {
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
    }

    // Otherwise, fall back to geocoding the address (approximate)
    const city = (property.city || '').trim();
    const country = (property.country || 'Philippines').trim();
    let searchQuery = [property.address, city, country].filter(Boolean).join(', ');
    if (!searchQuery) searchQuery = city + ', ' + country;
    if (country.toLowerCase().includes('philippines')) {
        if (cityLower.includes('lapu-lapu') || cityLower.includes('lapu lapu')) searchQuery = (property.address ? property.address + ', ' : '') + (city + ', Cebu, ' + country);
        else if (cityLower.includes('cebu city') || cityLower === 'cebu' || cityLower.includes('talamban') || addressLower.includes('cebu city') || addressLower.includes('talamban')) searchQuery = (property.address ? property.address + ', ' : '') + (city + ', Cebu, ' + country);
        else if (cityLower.includes('davao') || addressLower.includes('davao')) searchQuery = (property.address ? property.address + ', ' : '') + (city + ', Davao del Sur, ' + country);
        else if (cityLower.includes('iloilo') || addressLower.includes('iloilo')) searchQuery = (property.address ? property.address + ', ' : '') + (city + ', Iloilo, ' + country);
        else if (cityLower.includes('baguio') || addressLower.includes('baguio')) searchQuery = (property.address ? property.address + ', ' : '') + (city + ', Benguet, ' + country);
        else if (cityLower.includes('bacolod') || addressLower.includes('bacolod')) searchQuery = (property.address ? property.address + ', ' : '') + (city + ', Negros Occidental, ' + country);
        else if (cityLower.includes('cagayan de oro') || cityLower.includes('cdo') || addressLower.includes('cagayan de oro')) searchQuery = (property.address ? property.address + ', ' : '') + (city + ', Misamis Oriental, ' + country);
        else if (cityLower.includes('zamboanga') || addressLower.includes('zamboanga')) searchQuery = (property.address ? property.address + ', ' : '') + (city + ', Zamboanga del Sur, ' + country);
        else if (cityLower.includes('general santos') || cityLower.includes('gensan') || addressLower.includes('general santos')) searchQuery = (property.address ? property.address + ', ' : '') + (city + ', South Cotabato, ' + country);
    }
    const runMap = (lat, lng) => {
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
    };
    const defaultLapuLapu = { lat: 10.3119, lng: 123.9494 };
    const defaultManila = { lat: 14.5995, lng: 120.9842 };
    const geocodeUrl = 'https://nominatim.openstreetmap.org/search?format=json&q=' + encodeURIComponent(searchQuery) + '&limit=5';
    fetch(geocodeUrl, { headers: { 'Accept': 'application/json', 'User-Agent': 'ReserveProPropertyMap/1.0' } })
        .then(r => r.json())
        .then(results => {
            let lat = isLapuLapuOrCebu ? defaultLapuLapu.lat : defaultManila.lat;
            let lng = isLapuLapuOrCebu ? defaultLapuLapu.lng : defaultManila.lng;
            if (results && results.length > 0) {
                const pick = results.find(r => {
                    const la = parseFloat(r.lat), ln = parseFloat(r.lon);
                    if (la < phBounds.latMin || la > phBounds.latMax || ln < phBounds.lngMin || ln > phBounds.lngMax) return false;
                    if (isLapuLapuOrCebu) return la >= cebuBounds.latMin && la <= cebuBounds.latMax && ln >= cebuBounds.lngMin && ln <= cebuBounds.lngMax;
                    return true;
                });
                if (pick) {
                    lat = parseFloat(pick.lat);
                    lng = parseFloat(pick.lon);
                } else if (isLapuLapuOrCebu) {
                    const addr = (property.address || '').toLowerCase();
                    let fallbackQuery = 'Cebu City, Cebu, Philippines';
                    if (addr.includes('maribago')) fallbackQuery = 'Maribago, Lapu-Lapu City, Cebu, Philippines';
                    else if (addr.includes('talamban')) fallbackQuery = 'Talamban, Cebu City, Cebu, Philippines';
                    else if (addr.includes('lapu-lapu')) fallbackQuery = 'Lapu-Lapu City, Cebu, Philippines';
                    return fetch('https://nominatim.openstreetmap.org/search?format=json&q=' + encodeURIComponent(fallbackQuery) + '&limit=1', { headers: { 'Accept': 'application/json', 'User-Agent': 'ReserveProPropertyMap/1.0' } })
                        .then(r2 => r2.json())
                        .then(r2 => {
                            if (r2 && r2[0]) {
                                runMap(parseFloat(r2[0].lat), parseFloat(r2[0].lon));
                            } else {
                                runMap(defaultLapuLapu.lat, defaultLapuLapu.lng);
                            }
                        })
                        .catch(() => runMap(defaultLapuLapu.lat, defaultLapuLapu.lng));
                } else {
                    const first = results.find(r => {
                        const la = parseFloat(r.lat), ln = parseFloat(r.lon);
                        return la >= phBounds.latMin && la <= phBounds.latMax && ln >= phBounds.lngMin && ln <= phBounds.lngMax;
                    }) || results[0];
                    lat = parseFloat(first.lat);
                    lng = parseFloat(first.lon);
                }
            }
            runMap(lat, lng);
        })
        .catch(() => {
            const lat = isLapuLapuOrCebu ? defaultLapuLapu.lat : defaultManila.lat;
            const lng = isLapuLapuOrCebu ? defaultLapuLapu.lng : defaultManila.lng;
            if (window.L) runMap(lat, lng);
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
    const addressLine = [property.address, property.city, property.country].filter(Boolean).join(', ') || (property.city + ', ' + property.country);
    marker.bindPopup('<strong>' + (property.title || '') + '</strong><br>' + addressLine).openPopup();
    window.propertyMap = map;
    window.propertyMapMarker = marker;
}

function setupBookingCalculator(property, bookedDates) {
    const pricePerNight = parseFloat(property && property.price_per_night) || 0;
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

    const bookingForm = document.getElementById('bookingForm');
    if (!bookingForm) {
        console.error('Booking form #bookingForm not found');
        return;
    }

    const paymongoAvailable = !!(property && property.paymongo_available);

    bookingForm.addEventListener('submit', function(e) {
        e.preventDefault();

        const user = window.currentUser || null;
        if (!user || user.role !== 'guest') {
            alert('Please sign in as a guest to make a booking.');
            window.location.href = 'login.php';
            return;
        }

        if (checkIn.value && checkOut.value && isRangeBlocked(checkIn.value, checkOut.value)) {
            alert('Some selected dates are already booked. Please choose different dates.');
            return;
        }

        if (!checkIn.value || !checkOut.value) {
            alert('Please select both check-in and check-out dates.');
            return;
        }

        const guestsInput = document.getElementById('modal_guests');
        const guestsVal = guestsInput ? parseInt(guestsInput.value || '0', 10) : 0;
        if (!guestsVal || guestsVal < 1) {
            alert('Please enter the number of guests.');
            return;
        }

        if (!paymongoAvailable) {
            alert('Online payment is not configured. Bookings are unavailable until PayMongo is set up.');
            return;
        }

        const formData = new FormData();
        formData.append('property_id', String(currentPropertyId));
        formData.append('check_in', checkIn.value);
        formData.append('check_out', checkOut.value);
        formData.append('guests', String(guestsVal));

        const reserveBtn = bookingForm.querySelector('.modal-reserve-submit');
        let willRedirectToCheckout = false;
        if (reserveBtn) {
            reserveBtn.disabled = true;
            reserveBtn.textContent = 'Creating checkout…';
        }

        fetch('create-booking.php', {
            method: 'POST',
            body: formData
        })
            .then(r => r.json())
            .then(res => {
                if (!res || res.error) {
                    alert(res && res.error ? res.error : 'Failed to create booking. Please try again.');
                    return;
                }

                const totalText = (res.total !== undefined)
                    ? '₱' + Number(res.total).toLocaleString('en-PH', { minimumFractionDigits: 2 })
                    : document.getElementById('modal_total').textContent;

                if (res.payment_url) {
                    willRedirectToCheckout = true;
                    if (reserveBtn) {
                        reserveBtn.textContent = 'Opening PayMongo…';
                    }
                    window.location.href = res.payment_url;
                    return;
                }

                if (res.payment_checkout_failed) {
                    alert(
                        'Your booking was saved, but PayMongo checkout could not be started (check API keys and PayMongo dashboard). '
                        + 'Please contact the host about payment.\n\nTotal: ' + totalText
                    );
                    closePropertyModal();
                    window.location.href = 'home.php';
                    return;
                }

                alert((res.message || 'Booking created successfully.') + '\n\nTotal: ' + totalText);
                closePropertyModal();
                window.location.href = 'home.php';
            })
            .catch(() => {
                alert('Network error while creating booking. Please try again.');
            })
            .finally(() => {
                if (reserveBtn && !willRedirectToCheckout) {
                    reserveBtn.disabled = !paymongoAvailable;
                    reserveBtn.textContent = paymongoAvailable ? 'Continue to PayMongo checkout' : 'Booking unavailable';
                }
            });
    });
}

// Close modal on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closePropertyModal();
    }
});
