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
        <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;padding:64px 40px;gap:18px;">
            <div style="width:36px;height:36px;border:3px solid #252525;border-top-color:#D4A574;border-radius:50%;animation:modal-spin 0.8s linear infinite;"></div>
            <p style="margin:0;color:#6B7280;font-size:14px;letter-spacing:0.03em;">Loading property details...</p>
        </div>
        <style>@keyframes modal-spin{to{transform:rotate(360deg);}}</style>
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
                    <div style="text-align:center;padding:56px 40px;">
                        <div style="width:52px;height:52px;margin:0 auto 18px;background:rgba(239,68,68,0.08);border-radius:50%;display:flex;align-items:center;justify-content:center;border:1px solid rgba(239,68,68,0.18);">
                            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#EF4444" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                        </div>
                        <p style="color:#EF4444;font-size:15px;margin:0;">${data.error}</p>
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
                <div style="text-align:center;padding:56px 40px;">
                    <div style="width:52px;height:52px;margin:0 auto 18px;background:rgba(239,68,68,0.08);border-radius:50%;display:flex;align-items:center;justify-content:center;border:1px solid rgba(239,68,68,0.18);">
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#EF4444" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                    </div>
                    <p style="color:#EF4444;font-size:15px;margin:0 0 8px;">Failed to load property details</p>
                    <p style="font-size:12px;color:#6B7280;margin:0;">Error: ${error.message}</p>
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
                                <img src="${thumbUrl}" alt="" data-lightbox="property" data-lightbox-title="${safeTitle}" style="width:110px; height:75px; object-fit:cover;" onerror="this.src='${fallbackPhoto}'">
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
                <img id="propertyModalMainPhoto" src="${mainPhotoUrl}" alt="${safeTitle}" data-lightbox="property" data-lightbox-title="${safeTitle}" onerror="this.src='${fallbackPhoto}'">
                ${slideshowControls}
            </div>
            ${thumbnailsHTML}
        </div>
    `;
    
    // Amenity icon lookup — maps amenity name to a clean Lucide-style SVG path
    const amenityIcon = (name) => {
        const n = (name || '').toLowerCase();
        const icons = {
            wifi:        '<path d="M5 12.5C7.5 10 10.5 8.5 12 8.5s4.5 1.5 7 4"/><path d="M2 9c3.5-3 7.5-4.5 10-4.5s6.5 1.5 10 4.5"/><circle cx="12" cy="17" r="1"/>',
            'air conditioning':'<path d="M8 2v6"/><path d="M16 2v6"/><path d="M12 2v6"/><path d="M3 11h18"/><path d="M5 15l-2 4"/><path d="M19 15l2 4"/><path d="M12 15v6"/>',
            heating:     '<path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10"/><path d="M12 8v4l3 3"/>',
            kitchen:     '<path d="M3 2v7c0 1.1.9 2 2 2h4a2 2 0 0 0 2-2V2"/><path d="M7 2v20"/><path d="M21 15V2a5 5 0 0 0-5 5v6c0 .55.45 1 1 1h3c.55 0 1-.45 1-1Z"/><path d="M21 15v7"/>',
            tv:          '<rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/>',
            'washing machine': '<rect x="2" y="2" width="20" height="20" rx="3"/><circle cx="12" cy="13" r="5"/><path d="M7 7h0M11 7h2"/>',
            'free parking':    '<rect x="3" y="3" width="18" height="18" rx="2"/><path d="M9 17V7h4a3 3 0 0 1 0 6H9"/>',
            'swimming pool':   '<path d="M2 20c2-2 4-2 6 0s4 2 6 0 4-2 6 0"/><path d="M2 15c2-2 4-2 6 0s4 2 6 0 4-2 6 0"/><path d="M8 8a4 4 0 1 0 8 0"/><path d="M12 4v4"/>',
            'hot tub':         '<path d="M9 6 6.5 3.5a1.5 1.5 0 0 1 0-2.1"/><path d="M14 6 11.5 3.5a1.5 1.5 0 0 1 0-2.1"/><path d="M5 14v2a7 7 0 0 0 14 0v-2"/><path d="M5 14H2"/><path d="M22 14h-3"/>',
            gym:         '<path d="M6 7v10"/><path d="M18 7v10"/><path d="M8 7H4"/><path d="M20 7h-4"/><path d="M8 17H4"/><path d="M20 17h-4"/><path d="M9 11h6"/>',
            'bbq grill':       '<path d="M8 22H5a1 1 0 0 1-.978-1.208l1.255-6.278A2 2 0 0 1 7.243 13h9.514a2 2 0 0 1 1.966 1.514L19.978 20.792A1 1 0 0 1 19 22h-3"/><path d="M10 22v-2a2 2 0 1 1 4 0v2"/><path d="M6 13V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v8"/><circle cx="12" cy="7" r="1"/>',
            'pet friendly':    '<path d="M11 20A7 7 0 0 1 9.8 6.1C15.5 5 17 4.48 19 2c1 2 2 4.18 2 8 0 5.5-4.78 10-10 10Z"/><path d="M2 21c0-3 1.85-5.36 5.08-6C9.5 14.52 12 13 13 12"/>',
            'smoke detector':  '<circle cx="12" cy="11" r="7"/><path d="M12 4v1M12 18v1M4 11H3M21 11h-1M6.34 5.34l.71.71M16.95 16.95l.71.71M6.34 16.66l.71-.71M16.95 6.05l.71-.71"/><circle cx="12" cy="11" r="3"/>',
            'first aid kit':   '<rect x="3" y="7" width="18" height="14" rx="2"/><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M12 12v4"/><path d="M10 14h4"/>',
            'fire extinguisher':'<path d="M15 6.5A3.5 3.5 0 0 1 8 6.5C8 5 9 3 10 2h4c1 1 2 3 1 4.5Z"/><path d="M8 6.5C6 7 5 9 5 11v8a2 2 0 0 0 4 0v-5"/><path d="M14 10h3"/><path d="M17 8v4"/>',
            cctv:        '<path d="m22 8-6 4 6 4V8Z"/><rect x="2" y="6" width="14" height="12" rx="2"/>',
            balcony:     '<path d="M3 21h18"/><path d="M3 10h18"/><path d="M5 10v11"/><path d="M19 10v11"/><path d="M9 10V7"/><path d="M15 10V7"/><rect x="9" y="4" width="6" height="3" rx="1"/>',
            garden:      '<path d="M12 22V11"/><path d="M5 11a7 7 0 0 1 14 0"/><path d="M5 11a7 7 0 0 0 3.5 6.06"/><path d="M19 11a7 7 0 0 1-3.5 6.06"/>',
            workspace:   '<rect x="3" y="4" width="18" height="12" rx="2"/><path d="M8 20h8"/><path d="M12 16v4"/>',
            'coffee maker':    '<path d="M10 2v2"/><path d="M14 2v2"/><path d="M16 8a1 1 0 0 1 1 1v8a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4V9a1 1 0 0 1 1-1h14"/><path d="M6 2v2"/>',
        };
        // Try exact match, then partial match
        if (icons[n]) return icons[n];
        for (const [key, val] of Object.entries(icons)) {
            if (n.includes(key) || key.includes(n)) return val;
        }
        // fallback — small circle checkmark
        return '<circle cx="12" cy="12" r="9"/><path d="m9 12 2 2 4-4"/>';
    };

    // Build amenities HTML
    let amenitiesHTML = '';
    if (property.amenities && property.amenities.length > 0) {
        amenitiesHTML = `
            <div class="pm-section">
                <h2 class="pm-section-title">Amenities</h2>
                <div class="amenities-pill-grid">
                    ${property.amenities.map(amenity => `
                        <div class="amenity-pill">
                            <div class="amenity-pill-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">${amenityIcon(amenity.name)}</svg>
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
                <div style="display:flex;align-items:center;gap:6px;margin-top:8px;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="#D4A574" stroke="none"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                    <span style="color:#D4A574;font-weight:700;font-size:14px;">${rounded}</span>
                    <span style="color:#4B5563;font-size:13px;">&bull; ${reviewCount} ${label}</span>
                </div>
            `;
        }
        return '';
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

    // Refund / cancellation policy
    const policyKey = String(property.cancellation_policy || 'moderate').toLowerCase();
    const policyLabel = policyKey === 'flexible' ? 'Flexible'
        : policyKey === 'strict' ? 'Strict'
        : '';
    const policyText = '99% refund within 6 hours, 50% within 12 hours, none after 12 hours.';
    const refundPolicyHTML = `
        <div style="margin-top:10px; padding:12px 12px; border-radius:14px; border:1px solid rgba(255,255,255,0.10); background: rgba(255,255,255,0.05);">
            <div style="display:flex; align-items:center; justify-content:space-between; gap:10px;">
                <div style="font-size:12px; color:#94A3B8; font-weight:900; letter-spacing:0.08em; text-transform:uppercase;">Refund policy</div>
                ${policyLabel ? `<div style="font-size:12px; font-weight:900; color:#FDE68A;">${policyLabel}</div>` : ''}
            </div>
            <div style="margin-top:6px; font-size:12px; color:#CBD5E1; line-height:1.45;">${policyText}</div>
        </div>
    `;

    const html = `
        <div class="property-modal-inner">

            <!-- ═══ HERO GALLERY ═══ -->
            <div class="pm-gallery">
                ${photosHTML}
            </div>

            <!-- ═══ TITLE / META HEADER ═══ -->
            <div class="pm-header">
                <span class="pm-type-badge">${property.property_type.charAt(0).toUpperCase() + property.property_type.slice(1)}</span>
                <h1 class="pm-title">${property.title}</h1>
                <div class="pm-meta-row">
                    ${ratingSummaryHTML}
                    ${ratingSummaryHTML ? '<span class="pm-meta-sep">&middot;</span>' : ''}
                    <div class="pm-location-tag" id="pmLocationTag" title="View on map below">
                        <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg>
                        <span>${property.city}, ${property.country}</span>
                    </div>
                </div>
            </div>

            <!-- ═══ TWO-COLUMN LAYOUT ═══ -->
            <div class="pm-layout">

                <!-- LEFT: MAIN CONTENT -->
                <div class="pm-main">

                    <!-- THE SPACE -->
                    <div class="pm-section">
                        <h2 class="pm-section-title">The Space</h2>
                        <div class="pm-stats-row">
                            <div class="pm-stat-card">
                                <div class="pm-stat-icon">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#D4A574" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M2 4v16"/><path d="M22 4v16"/><path d="M2 18h20"/><path d="M2 10h20"/><rect x="6" y="4" width="12" height="6" rx="1"/></svg>
                                </div>
                                <span class="pm-stat-num">${property.bedrooms}</span>
                                <span class="pm-stat-label">Bedroom${property.bedrooms > 1 ? 's' : ''}</span>
                            </div>
                            <div class="pm-stat-card">
                                <div class="pm-stat-icon">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#D4A574" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12h20"/><path d="M2 14v-2a10 10 0 0 1 20 0v2"/><path d="M22 17v1a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2v-1"/></svg>
                                </div>
                                <span class="pm-stat-num">${property.bathrooms}</span>
                                <span class="pm-stat-label">Bathroom${property.bathrooms > 1 ? 's' : ''}</span>
                            </div>
                            <div class="pm-stat-card">
                                <div class="pm-stat-icon">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#D4A574" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                                </div>
                                <span class="pm-stat-num">${property.max_guests}</span>
                                <span class="pm-stat-label">Guest${property.max_guests > 1 ? 's' : ''}</span>
                            </div>
                            <div class="pm-stat-card">
                                <div class="pm-stat-icon">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#D4A574" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                                </div>
                                <span class="pm-stat-num pm-stat-type">${property.property_type.charAt(0).toUpperCase() + property.property_type.slice(1)}</span>
                                <span class="pm-stat-label">Type</span>
                            </div>
                        </div>
                    </div>

                    <!-- ABOUT THIS PLACE -->
                    <div class="pm-section">
                        <h2 class="pm-section-title">About this place</h2>
                        <p class="pm-desc">${property.description.replace(/\n/g, '<br>')}</p>
                    </div>

                    <!-- WHERE YOU'LL BE (map auto-loaded) -->
                    <div class="pm-section" id="pmLocationSection">
                        <h2 class="pm-section-title">Where you'll be</h2>
                        <p class="pm-address-line">
                            <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#D4A574" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg>
                            ${[property.address, property.city, property.country].filter(Boolean).join(', ')}
                        </p>
                        <div id="propertyMapContainer" style="display:none;"></div>
                    </div>

                    <!-- AMENITIES -->
                    ${amenitiesHTML}

                    <!-- HOST -->
                    <div class="pm-section pm-section-last">
                        <h2 class="pm-section-title">Your host</h2>
                        <div class="pm-host-card">
                            <div class="pm-host-card-top">
                                <div class="pm-host-avatar">${property.first_name.charAt(0)}${property.last_name.charAt(0)}</div>
                                <div class="pm-host-info">
                                    <div class="pm-host-name">${property.first_name} ${property.last_name}</div>
                                    <div class="pm-host-role">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="#22c55e" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                        Verified Host
                                    </div>
                                </div>
                                <button type="button" class="pm-host-msg-toggle" id="pmHostMsgToggle">Message</button>
                            </div>
                            <div class="pm-host-msg-panel" id="pmHostMsgPanel">
                                <form id="contactHostForm" class="contact-host-form" data-property-id="${property.id}">
                                    <textarea name="message" id="contactHostMessage" placeholder="Ask ${property.first_name} a question about this place…" rows="3" required class="pm-message-area"></textarea>
                                    <div id="contactHostStatus" style="font-size:12px;min-height:16px;margin:4px 0;"></div>
                                    <button type="submit" id="contactHostSubmit" class="pm-send-btn">Send</button>
                                </form>
                            </div>
                        </div>
                    </div>

                </div><!-- /pm-main -->

                <!-- RIGHT: STICKY BOOKING CARD -->
                <div class="pm-sidebar">
                    <div class="pm-booking-card">

                        <!-- Price row -->
                        <div class="pm-price-header">
                            <div class="pm-price-row">
                                <span class="pm-price">&#8369;${parseFloat(property.price_per_night).toLocaleString('en-PH', {minimumFractionDigits: 2})}</span>
                                <span class="pm-price-unit">/ night</span>
                            </div>
                            <div class="pm-card-rating">
                                ${ratingSummaryHTML || '<span class="pm-no-reviews">No reviews yet</span>'}
                            </div>
                        </div>

                        <form id="bookingForm">

                            <!-- Availability calendar -->
                            <div id="bookingCalendarSection" class="pm-calendar-wrap">
                                <div class="pm-field-label">Availability <span class="pm-calendar-hint">— red = booked</span></div>
                                <div id="bookingCalendar" class="pm-calendar-grid"></div>
                            </div>

                            <!-- Date group -->
                            <div class="pm-dates-group">
                                <div class="pm-date-cell">
                                    <label class="pm-date-label">CHECK-IN</label>
                                    <input type="date" id="modal_check_in" required min="${new Date().toISOString().split('T')[0]}" class="pm-date-input">
                                </div>
                                <div class="pm-date-divider"></div>
                                <div class="pm-date-cell">
                                    <label class="pm-date-label">CHECK-OUT</label>
                                    <input type="date" id="modal_check_out" required class="pm-date-input">
                                </div>
                            </div>

                            <!-- Guests -->
                            <div class="pm-guests-box">
                                <label class="pm-field-label">Guests</label>
                                <input type="number" id="modal_guests" value="1" min="1" max="${property.max_guests}" required class="pm-guests-input">
                            </div>

                            <!-- Booking summary -->
                            <div id="bookingSummary" class="pm-summary" style="display:none;">
                                <div class="pm-summary-row">
                                    <span>&#8369;${parseFloat(property.price_per_night).toLocaleString('en-PH', {minimumFractionDigits: 2})} &times; <span id="modal_nights">0</span> night<span id="modal_nightsPlural"></span></span>
                                    <span id="modal_subtotal">&#8369;0.00</span>
                                </div>
                                <div class="pm-summary-row">
                                    <span>Service fee (10%)</span>
                                    <span id="modal_serviceFee">&#8369;0.00</span>
                                </div>
                                <div class="pm-summary-total">
                                    <span>Total</span>
                                    <span id="modal_total">&#8369;0.00</span>
                                </div>
                            </div>

                            ${paymentMethodsHTML}

                            <button type="submit" class="pm-cta-btn modal-btn modal-reserve-submit" ${paymongoOn ? '' : 'disabled'} style="opacity:${paymongoOn ? '1' : '0.45'};cursor:${paymongoOn ? 'pointer' : 'not-allowed'};">${paymongoOn ? 'Reserve' : 'Booking unavailable'}</button>
                            ${refundPolicyHTML}

                        </form>
                    </div>
                </div><!-- /pm-sidebar -->

            </div><!-- /pm-layout -->

        </div><!-- /property-modal-inner -->
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

    // Reset Leaflet map instances so re-opening always re-initializes cleanly
    window.propertyMap = null;
    window.propertyMapMarker = null;

    // Auto-initialize the location map (container is pre-created with display:none)
    showPropertyMap(property);

    // Location tag scrolls to the map section
    const locationTag = modalContent.querySelector('#pmLocationTag');
    if (locationTag) {
        locationTag.addEventListener('click', function () {
            const sec = document.getElementById('pmLocationSection');
            if (sec) sec.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    }
    
    // Setup booking calculator after DOM is updated
    setTimeout(() => {
        setupBookingCalculator(property, bookedDates);
    }, 100);

    // Host message panel toggle
    const msgToggle = document.getElementById('pmHostMsgToggle');
    const msgPanel  = document.getElementById('pmHostMsgPanel');
    if (msgToggle && msgPanel) {
        msgToggle.addEventListener('click', function () {
            const open = msgPanel.classList.toggle('is-open');
            msgToggle.textContent = open ? 'Cancel' : 'Message';
        });
    }
}

function renderReviewsSection(property) {
    const reviews = Array.isArray(property.reviews) ? property.reviews : [];
    const user = window.currentUser || null;
    const isGuest = user && user.role === 'guest';

    const starsHTML = (rating) => {
        const filled = Math.max(0, Math.min(5, rating));
        return Array.from({length: 5}, (_, i) =>
            `<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="${i < filled ? '#D4A574' : 'none'}" stroke="#D4A574" stroke-width="1.5"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>`
        ).join('');
    };

    const reviewsListHTML = reviews.length > 0
        ? reviews.map(r => {
            const name = ((r.first_name || '') + ' ' + (r.last_name || '')).trim() || 'Guest';
            const initial = name.charAt(0).toUpperCase();
            const createdAt = r.created_at ? new Date(r.created_at).toLocaleDateString('en-US', {month:'short', year:'numeric'}) : '';
            const rating = parseInt(r.rating, 10) || 0;
            const safeComment = (r.comment || '').replace(/</g, '&lt;').replace(/>/g, '&gt;');
            return `
                <div class="pm-review-card">
                    <div class="pm-review-header">
                        <div class="pm-review-avatar">${initial}</div>
                        <div>
                            <div class="pm-review-name">${name}</div>
                            <div class="pm-review-date">${createdAt}</div>
                        </div>
                        <div class="pm-review-stars" style="margin-left:auto;">${starsHTML(rating)}</div>
                    </div>
                    <p class="pm-review-text">${safeComment}</p>
                </div>
            `;
        }).join('')
        : `<p class="pm-no-reviews-msg">${isGuest ? 'Be the first to share your experience.' : 'Sign in as a guest to leave a review.'}</p>`;

    const formHTML = isGuest ? `
        <form id="reviewForm" class="pm-review-form">
            <div class="pm-review-rating-row">
                <label class="pm-field-label">Your rating</label>
                <div id="reviewStars" style="display:flex;gap:6px;cursor:pointer;">
                    ${[1,2,3,4,5].map(i => `<span class="review-star" data-value="${i}" style="font-size:20px;">★</span>`).join('')}
                </div>
                <input type="hidden" id="reviewRating" name="rating" value="0">
            </div>
            <div>
                <label for="reviewComment" class="pm-field-label">Your review</label>
                <textarea id="reviewComment" name="comment" rows="3" class="pm-message-area" style="margin-top:8px;"></textarea>
            </div>
            <div id="reviewError" style="font-size:13px;color:#FCA5A5;display:none;"></div>
            <button type="submit" class="pm-send-btn" style="align-self:flex-start;">Submit review</button>
        </form>
    ` : `<p class="pm-no-reviews-msg" style="margin-top:14px;">Sign in as a guest to leave a review.</p>`;

    return `
        <div class="pm-section">
            <h2 class="pm-section-title">Guest reviews</h2>
            <div class="pm-reviews-list">${reviewsListHTML}</div>
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

    // --- Selection highlight helpers ---
    function parseDate(d) {
        const parts = String(d || '').split('-').map(n => parseInt(n, 10));
        if (parts.length !== 3) return null;
        const dt = new Date(parts[0], parts[1] - 1, parts[2]);
        dt.setHours(0, 0, 0, 0);
        return isNaN(dt.getTime()) ? null : dt;
    }

    function applySelectionUI() {
        const checkInEl = document.getElementById('modal_check_in');
        const checkOutEl = document.getElementById('modal_check_out');
        const inStr = checkInEl ? checkInEl.value : '';
        const outStr = checkOutEl ? checkOutEl.value : '';
        const inD = parseDate(inStr);
        const outD = parseDate(outStr);

        container.querySelectorAll('.calendar-day').forEach(cell => {
            cell.classList.remove('is-selected', 'is-selected-start', 'is-selected-end', 'is-in-range');
            const ds = cell.getAttribute('data-date');
            if (!ds) return;
            if (inStr && ds === inStr) {
                cell.classList.add('is-selected', 'is-selected-start');
            }
            if (outStr && ds === outStr) {
                cell.classList.add('is-selected', 'is-selected-end');
            }
            if (inD && outD) {
                const cd = parseDate(ds);
                if (!cd) return;
                // Mark strictly between check-in and check-out
                if (cd > inD && cd < outD) {
                    cell.classList.add('is-in-range');
                }
            }
        });
    }

    // Inject once: minimal CSS for selected/range styles
    if (!document.getElementById('rpCalendarSelectStyles')) {
        const style = document.createElement('style');
        style.id = 'rpCalendarSelectStyles';
        style.textContent = `
            #bookingCalendar .calendar-day.is-in-range{
                outline: 2px solid rgba(212,165,116,0.18);
                background: rgba(212,165,116,0.12) !important;
            }
            #bookingCalendar .calendar-day.is-selected{
                background: rgba(212,165,116,0.26) !important;
                outline: 2px solid rgba(212,165,116,0.55);
                font-weight: 800;
            }
            #bookingCalendar .calendar-day.is-selected-start,
            #bookingCalendar .calendar-day.is-selected-end{
                background: rgba(212,165,116,0.38) !important;
                outline: 2px solid rgba(212,165,116,0.75);
            }
        `;
        document.head.appendChild(style);
    }
    applySelectionUI();
    
    // Click on available day to set check-in or check-out
    container.querySelectorAll('.calendar-day:not(.calendar-day-booked)').forEach(cell => {
        if (cell.style.pointerEvents === 'none') return;
        cell.addEventListener('click', function() {
            const dateStr = this.getAttribute('data-date');
            const checkIn = document.getElementById('modal_check_in');
            const checkOut = document.getElementById('modal_check_out');
            if (!checkIn || !checkOut) return;

            // Separate selection:
            // 1st click = set check-in only
            // 2nd click = set check-out
            // if both already set, start over
            if (!checkIn.value || (checkIn.value && checkOut.value)) {
                checkIn.value = dateStr;
                checkOut.value = '';
                const nextDay = new Date(dateStr);
                nextDay.setDate(nextDay.getDate() + 1);
                checkOut.min = nextDay.toISOString().split('T')[0];
            } else {
                // Setting check-out: must be after check-in
                const inD = parseDate(checkIn.value);
                const outD = parseDate(dateStr);
                if (inD && outD && outD <= inD) {
                    // If user clicked an earlier/same date, treat it as a new check-in
                    checkIn.value = dateStr;
                    checkOut.value = '';
                    const nextDay = new Date(dateStr);
                    nextDay.setDate(nextDay.getDate() + 1);
                    checkOut.min = nextDay.toISOString().split('T')[0];
                } else {
                    checkOut.value = dateStr;
                }
            }
            checkIn.dispatchEvent(new Event('change', { bubbles: true }));
            checkOut.dispatchEvent(new Event('change', { bubbles: true }));
            applySelectionUI();
        });
    });

    // Keep UI in sync when user edits date inputs manually
    const checkInEl = document.getElementById('modal_check_in');
    const checkOutEl = document.getElementById('modal_check_out');
    if (checkInEl && !checkInEl.dataset.rpSelBound) {
        checkInEl.addEventListener('change', applySelectionUI);
        checkInEl.dataset.rpSelBound = '1';
    }
    if (checkOutEl && !checkOutEl.dataset.rpSelBound) {
        checkOutEl.addEventListener('change', applySelectionUI);
        checkOutEl.dataset.rpSelBound = '1';
    }
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
