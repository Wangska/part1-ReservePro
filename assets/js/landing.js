// Landing page interactions

document.addEventListener('DOMContentLoaded', function() {
    
    // Global search term variable
    let searchTerm = '';
    
    // Favorite buttons
    const favoriteButtons = document.querySelectorAll('.card-favorite');
    favoriteButtons.forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            if (this.textContent === '♡') {
                this.textContent = '♥';
                this.style.color = '#FF385C';
            } else {
                this.textContent = '♡';
                this.style.color = '#000';
            }
        });
    });
    
    // Card click handlers (now handled by links, but keep favorite button working)
    const cards = document.querySelectorAll('.service-card');
    cards.forEach(card => {
        // Prevent card link from triggering when clicking favorite button
        const favoriteBtn = card.querySelector('.card-favorite');
        if (favoriteBtn) {
            favoriteBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
            });
        }
    });
    
    // Sort select functionality
    const sortSelect = document.querySelector('.sort-select');
    const cardsGrid = document.querySelector('.cards-grid');
    
    if (sortSelect && cardsGrid) {
        sortSelect.addEventListener('change', function() {
            const sortValue = this.value;
            const cards = Array.from(cardsGrid.querySelectorAll('.service-card:not([style*="display: none"])'));
            
            // Sort the cards based on selected option
            cards.sort((a, b) => {
                switch(sortValue) {
                    case 'price-low':
                        const priceA = parseFloat(a.getAttribute('data-price'));
                        const priceB = parseFloat(b.getAttribute('data-price'));
                        return priceA - priceB;
                        
                    case 'price-high':
                        const priceA2 = parseFloat(a.getAttribute('data-price'));
                        const priceB2 = parseFloat(b.getAttribute('data-price'));
                        return priceB2 - priceA2;
                        
                    case 'newest':
                        const dateA = new Date(a.getAttribute('data-date'));
                        const dateB = new Date(b.getAttribute('data-date'));
                        return dateB - dateA;
                        
                    case 'popular':
                    default:
                        return 0;
                }
            });
            
            // Re-append visible cards in new order with animation
            cards.forEach(card => {
                card.style.animation = 'fadeIn 0.3s ease-in-out';
                cardsGrid.appendChild(card);
            });
        });
    }
    
    // Price range slider
    const priceSlider = document.querySelector('.price-slider');
    const currentPrice = document.getElementById('currentPrice');
    
    if (priceSlider) {
        priceSlider.addEventListener('input', function() {
            const maxPrice = parseInt(this.value);
            if (currentPrice) {
                currentPrice.textContent = '₱' + maxPrice.toLocaleString() + '+';
            }
            filterProperties();
        });
    }
    
    // Amenity filter checkboxes
    document.querySelectorAll('.amenity-filter').forEach(amenityFilter => {
        amenityFilter.addEventListener('change', function() {
            filterProperties();
        });
    });
    
    // Category filter checkboxes
    const categoryFilters = document.querySelectorAll('.category-filter');
    categoryFilters.forEach(filter => {
        filter.addEventListener('change', function() {
            // If "All Properties" is checked, uncheck others
            if (this.value === 'all' && this.checked) {
                categoryFilters.forEach(f => {
                    if (f.value !== 'all') f.checked = false;
                });
            } else if (this.value !== 'all' && this.checked) {
                // Uncheck "All Properties" if specific type is selected
                categoryFilters.forEach(f => {
                    if (f.value === 'all') f.checked = false;
                });
            }
            
            // If no specific categories are checked, check "All Properties"
            const anyChecked = Array.from(categoryFilters).some(f => f.checked && f.value !== 'all');
            if (!anyChecked) {
                categoryFilters.forEach(f => {
                    if (f.value === 'all') f.checked = true;
                });
            }
            
            filterProperties();
        });
    });
    
    // Filter properties based on active filters
    function filterProperties() {
        const cards = document.querySelectorAll('.service-card');
        const maxPrice = priceSlider ? parseInt(priceSlider.value) : Infinity;
        const selectedTypes = Array.from(categoryFilters)
            .filter(f => f.checked && f.value !== 'all')
            .map(f => f.value);
        
        const showAll = categoryFilters.length > 0 && categoryFilters[0].checked && categoryFilters[0].value === 'all';
        
        const amenityFilters = document.querySelectorAll('.amenity-filter:checked');
        const selectedAmenityIds = Array.from(amenityFilters).map(f => parseInt(f.value, 10));
        
        let visibleCount = 0;
        
        cards.forEach(card => {
            const cardPrice = parseFloat(card.getAttribute('data-price'));
            const cardType = card.getAttribute('data-type');
            const cardTitle = card.getAttribute('data-title') || '';
            const cardCity = card.getAttribute('data-city') || '';
            const cardCountry = card.getAttribute('data-country') || '';
            const cardDescription = card.getAttribute('data-description') || '';
            // Parse amenity IDs: only show card if it has ALL selected amenities (e.g. Kitchen = only units with Kitchen)
            const cardAmenityIdsRaw = (card.getAttribute('data-amenity-ids') || '').trim();
            const cardAmenityIds = cardAmenityIdsRaw
                ? cardAmenityIdsRaw.split(',').map(s => parseInt(s.trim(), 10)).filter(n => !isNaN(n))
                : [];
            
            // Check filters
            const priceMatch = cardPrice <= maxPrice;
            const typeMatch = showAll || selectedTypes.length === 0 || selectedTypes.includes(cardType);
            
            // Amenity: when any amenity is checked, show only cards that have ALL checked amenities
            const amenityMatch = selectedAmenityIds.length === 0 ||
                selectedAmenityIds.every(aid => cardAmenityIds.indexOf(aid) !== -1);
            
            // Check search term
            let searchMatch = true;
            if (searchTerm) {
                searchMatch = cardTitle.includes(searchTerm) || 
                             cardCity.includes(searchTerm) || 
                             cardCountry.includes(searchTerm) ||
                             cardDescription.includes(searchTerm);
            }
            
            if (priceMatch && typeMatch && amenityMatch && searchMatch) {
                card.style.display = 'block';
                visibleCount++;
            } else {
                card.style.display = 'none';
            }
        });
        
        // Show/hide empty state
        const cardsGrid = document.querySelector('.cards-grid');
        let emptyState = cardsGrid ? cardsGrid.querySelector('.no-results-message') : null;
        
        if (visibleCount === 0 && cardsGrid) {
            // Remove old message if exists
            if (emptyState) {
                emptyState.remove();
            }
            
            // Create new message
            const message = document.createElement('div');
            message.className = 'no-results-message';
            message.style.cssText = 'grid-column: 1 / -1; text-align: center; padding: 60px 20px;';
            
            const messageText = searchTerm 
                ? `No properties found for "${searchTerm}". Try a different search term or adjust filters.`
                : 'No properties match your filters. Try adjusting your selection.';
            
            message.innerHTML = `
                <div style="font-size: 64px; margin-bottom: 20px;">🔍</div>
                <h3 style="font-size: 24px; color: #FFFFFF !important; margin-bottom: 12px;">No Properties Found</h3>
                <p style="color: #E0E0E0 !important; font-size: 16px;">${messageText}</p>
            `;
            cardsGrid.appendChild(message);
        } else if (emptyState) {
            emptyState.remove();
        }
        
        // Update results count
        const resultsCount = document.getElementById('resultsCount');
        if (resultsCount) {
            if (searchTerm) {
                resultsCount.textContent = `Found ${visibleCount} ${visibleCount === 1 ? 'property' : 'properties'} for "${searchTerm}"`;
            } else {
                resultsCount.textContent = `Showing ${visibleCount} ${visibleCount === 1 ? 'property' : 'properties'}`;
            }
        }
    }
    
    // Search functionality
    const searchInput = document.getElementById('searchInput');
    const searchBtn = document.getElementById('searchBtn');
    
    if (searchInput) {
        // Search on button click
        if (searchBtn) {
            searchBtn.addEventListener('click', function() {
                searchTerm = searchInput.value.toLowerCase().trim();
                filterProperties();
            });
        }
        
        // Search on Enter key
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                searchTerm = this.value.toLowerCase().trim();
                filterProperties();
            }
        });
        
        // Real-time search as user types
        searchInput.addEventListener('input', function() {
            searchTerm = this.value.toLowerCase().trim();
            filterProperties();
        });
    }
    
    // Clear all filters button
    const clearFiltersBtn = document.getElementById('clearFilters');
    if (clearFiltersBtn) {
        clearFiltersBtn.addEventListener('click', function() {
            // Reset search input
            if (searchInput) {
                searchInput.value = '';
                searchTerm = '';
            }
            
            // Reset price slider to max
            if (priceSlider) {
                priceSlider.value = priceSlider.max;
                if (currentPrice) {
                    currentPrice.textContent = '₱' + parseInt(priceSlider.max).toLocaleString() + '+';
                }
            }
            
            // Check only "All Properties" category
            categoryFilters.forEach(filter => {
                if (filter.value === 'all') {
                    filter.checked = true;
                } else {
                    filter.checked = false;
                }
            });
            
            // Uncheck all amenity filters
            document.querySelectorAll('.amenity-filter').forEach(amenity => {
                amenity.checked = false;
            });
            
            // Re-filter to show all properties
            filterProperties();
        });
    }
    
    // Smooth scroll for nav links
    const navLinks = document.querySelectorAll('.nav-links a');
    navLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            const href = this.getAttribute('href');
            if (href.startsWith('#')) {
                e.preventDefault();
                const target = document.querySelector(href);
                if (target) {
                    target.scrollIntoView({ behavior: 'smooth' });
                }
            }
        });
    });
    
    // Navbar scroll effect
    let lastScroll = 0;
    const navbar = document.querySelector('.navbar');
    
    if (navbar) {
        window.addEventListener('scroll', function() {
            const currentScroll = window.pageYOffset;
            
            if (currentScroll > lastScroll && currentScroll > 100) {
                navbar.style.transform = 'translateY(-100%)';
            } else {
                navbar.style.transform = 'translateY(0)';
            }
            
            lastScroll = currentScroll;
        });
        
        // Add transition to navbar
        navbar.style.transition = 'transform 0.3s ease';
    }
});
