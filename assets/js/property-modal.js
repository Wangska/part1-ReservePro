// Property Details Modal

function openPropertyModal(propertyId) {
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
            
            renderPropertyDetails(data);
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

function renderPropertyDetails(property) {
    const modalContent = document.getElementById('propertyModalContent');
    
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
                <div style="font-size: 16px; color: #B8B8B8; display: flex; align-items: center; gap: 8px;">
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
    
    // Setup booking calculator after DOM is updated
    setTimeout(() => {
        setupBookingCalculator(property.price_per_night);
    }, 100);
}

function setupBookingCalculator(pricePerNight) {
    const checkIn = document.getElementById('modal_check_in');
    const checkOut = document.getElementById('modal_check_out');
    const bookingSummary = document.getElementById('bookingSummary');
    
    // Check if elements exist
    if (!checkIn || !checkOut || !bookingSummary) {
        console.error('Booking form elements not found');
        return;
    }
    
    function calculatePrice() {
        if (checkIn.value && checkOut.value) {
            const start = new Date(checkIn.value);
            const end = new Date(checkOut.value);
            const nights = Math.ceil((end - start) / (1000 * 60 * 60 * 24));
            
            if (nights > 0) {
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
        alert('Booking feature coming soon! Total: ' + document.getElementById('modal_total').textContent);
    });
}

// Close modal on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closePropertyModal();
    }
});
