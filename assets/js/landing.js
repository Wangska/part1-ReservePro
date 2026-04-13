// Landing page interactions

document.addEventListener('DOMContentLoaded', function() {
    
    // Global search term variable
    let searchTerm = '';

    // Mobile filters drawer
    const filtersSidebar = document.getElementById('filtersSidebar');
    const filterOverlay = document.getElementById('filterOverlay');
    const filterToggle = document.getElementById('filterToggle');
    const filterClose = document.getElementById('filterClose');
    const filterBadge = document.getElementById('filterBadge');
    const filterContentToggle = document.getElementById('filterContentToggle');
    const filtersContent = document.getElementById('filtersContent');

    function openFilters() {
        if (!filtersSidebar) return;
        filtersSidebar.classList.add('is-open');
        document.body.classList.add('rp-filters-open');
        if (filterToggle) {
            filterToggle.setAttribute('aria-expanded', 'true');
        }
        if (filterOverlay) {
            filterOverlay.setAttribute('aria-hidden', 'false');
        }
    }

    function closeFilters() {
        if (!filtersSidebar) return;
        filtersSidebar.classList.remove('is-open');
        document.body.classList.remove('rp-filters-open');
        if (filterToggle) {
            filterToggle.setAttribute('aria-expanded', 'false');
        }
        if (filterOverlay) {
            filterOverlay.setAttribute('aria-hidden', 'true');
        }
    }

    if (filterToggle) {
        filterToggle.addEventListener('click', function() {
            const isOpen = filtersSidebar && filtersSidebar.classList.contains('is-open');
            if (isOpen) {
                closeFilters();
            } else {
                openFilters();
            }
        });
    }

    const filterToggleHero = document.getElementById('filterToggleHero');
    if (filterToggleHero) {
        filterToggleHero.addEventListener('click', function() {
            const isOpen = filtersSidebar && filtersSidebar.classList.contains('is-open');
            if (isOpen) { closeFilters(); } else { openFilters(); }
        });
    }

    if (filterClose) filterClose.addEventListener('click', closeFilters);
    if (filterOverlay) filterOverlay.addEventListener('click', closeFilters);

    function setFilterContentState(isOpen) {
        if (!filtersSidebar || !filtersContent) return;
        const contentWrapper = filtersSidebar.closest('.content-wrapper');
        filtersSidebar.classList.toggle('is-collapsed', !isOpen);
        if (contentWrapper) {
            contentWrapper.classList.toggle('rp-filters-collapsed', !isOpen);
        }
        filtersContent.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
        if (filterContentToggle) {
            filterContentToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        }
    }

    if (filterContentToggle && filtersContent) {
        filterContentToggle.addEventListener('click', function() {
            const currentlyOpen = !filtersSidebar.classList.contains('is-collapsed');
            setFilterContentState(!currentlyOpen);
        });
        setFilterContentState(false);
    }
    
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
                this.style.color = '';
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

                    case 'rating-high':
                        const ratingA = parseFloat(a.getAttribute('data-rating') || '0');
                        const ratingB = parseFloat(b.getAttribute('data-rating') || '0');
                        // Higher rating first; if equal, fallback to newer date
                        if (ratingB !== ratingA) {
                            return ratingB - ratingA;
                        }
                        const dateAR = new Date(a.getAttribute('data-date'));
                        const dateBR = new Date(b.getAttribute('data-date'));
                        return dateBR - dateAR;
                        
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
    let isPriceFilterActive = false;
    
    if (priceSlider) {
        priceSlider.addEventListener('input', function() {
            const maxPrice = parseInt(this.value);
            isPriceFilterActive = true;
            if (currentPrice) {
                currentPrice.textContent = '₱' + maxPrice.toLocaleString();
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

            filterProperties();
        });
    });
    
    // Filter properties based on active filters
    function filterProperties() {
        const cards = document.querySelectorAll('.service-card');
        const maxPrice = (priceSlider && isPriceFilterActive) ? parseInt(priceSlider.value) : Infinity;
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

        // Keep type headings visible only when they still have visible cards
        const cardsGrid = document.querySelector('.cards-grid');
        if (cardsGrid) {
            const typeHeadings = cardsGrid.querySelectorAll('.rp-type-heading');
            typeHeadings.forEach(heading => {
                let hasVisibleCard = false;
                let sibling = heading.nextElementSibling;

                while (sibling && !sibling.classList.contains('rp-type-heading')) {
                    if (sibling.classList.contains('service-card') && sibling.style.display !== 'none') {
                        hasVisibleCard = true;
                        break;
                    }
                    sibling = sibling.nextElementSibling;
                }

                heading.style.display = hasVisibleCard ? 'block' : 'none';
            });
        }

        updateAppliedFiltersUI({
            searchTerm,
            maxPrice,
            priceFilterActive: isPriceFilterActive,
            showAll,
            selectedTypes,
            selectedAmenityIds
        });
        
        // Show/hide empty state
        let emptyState = cardsGrid ? cardsGrid.querySelector('.no-results-message') : null;
        
        if (visibleCount === 0 && cardsGrid) {
            // Remove old message if exists
            if (emptyState) {
                emptyState.remove();
            }
            
            // Create new message
            const message = document.createElement('div');
            message.className = 'no-results-message';
            
            const messageText = searchTerm 
                ? `No properties found for "${searchTerm}". Try a different search term or adjust filters.`
                : 'No properties match your filters. Try adjusting your selection.';
            
            message.innerHTML = `
                <div class="rp-empty-icon">🔍</div>
                <h3 class="rp-empty-title">No Properties Found</h3>
                <p class="rp-empty-text">${messageText}</p>
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

    function updateAppliedFiltersUI(state) {
        const el = document.getElementById('appliedFilters');
        if (!el) return;

        const chips = [];

        if (state.searchTerm) {
            chips.push({ key: 'search', label: `Search: "${state.searchTerm}"`, onRemove: () => {
                const searchInput = document.getElementById('searchInput');
                if (searchInput) searchInput.value = '';
                searchTerm = '';
                filterProperties();
            }});
        }

        // Price chip (only when price filter is explicitly used)
        if (priceSlider && state.priceFilterActive) {
            chips.push({ key: 'price', label: `Up to ₱${Number(state.maxPrice).toLocaleString()}`, onRemove: () => {
                isPriceFilterActive = false;
                priceSlider.value = 0;
                const currentPrice = document.getElementById('currentPrice');
                if (currentPrice) currentPrice.textContent = '₱0';
                filterProperties();
            }});
        }

        // Property type chips
        if (!state.showAll && state.selectedTypes.length > 0) {
            state.selectedTypes.forEach(t => {
                chips.push({ key: `type:${t}`, label: `Type: ${t}`, onRemove: () => {
                    categoryFilters.forEach(f => {
                        if (f.value === t) f.checked = false;
                    });
                    // Ensure "all" is checked if no types remain
                    const anyChecked = Array.from(categoryFilters).some(f => f.checked && f.value !== 'all');
                    if (!anyChecked) {
                        categoryFilters.forEach(f => { if (f.value === 'all') f.checked = true; });
                    }
                    filterProperties();
                }});
            });
        }

        // Amenity chips (use label text from DOM)
        if (state.selectedAmenityIds.length > 0) {
            state.selectedAmenityIds.forEach(id => {
                const input = document.querySelector(`.amenity-filter[value="${id}"]`);
                const labelEl = input ? input.closest('label') : null;
                const text = labelEl ? (labelEl.querySelector('span')?.textContent || '').trim() : `Amenity ${id}`;
                chips.push({ key: `amenity:${id}`, label: text, onRemove: () => {
                    const cb = document.querySelector(`.amenity-filter[value="${id}"]`);
                    if (cb) cb.checked = false;
                    filterProperties();
                }});
            });
        }

        if (chips.length === 0) {
            el.innerHTML = '';
            el.style.display = 'none';
            return;
        }

        el.style.display = 'flex';
        el.innerHTML = `
            <div class="rp-applied-label">Applied:</div>
            <div class="rp-chips" role="list"></div>
            <button type="button" class="rp-clear-filters" id="clearFiltersChips">Clear</button>
        `;

        const chipsWrap = el.querySelector('.rp-chips');
        chips.forEach(chip => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'rp-chip';
            btn.setAttribute('role', 'listitem');
            btn.innerHTML = `<span class="rp-chip-text"></span><span class="rp-chip-x" aria-hidden="true">×</span>`;
            btn.querySelector('.rp-chip-text').textContent = chip.label;
            btn.addEventListener('click', chip.onRemove);
            chipsWrap.appendChild(btn);
        });

        const clearBtn = el.querySelector('#clearFiltersChips');
        if (clearBtn) {
            clearBtn.addEventListener('click', function() {
                resetAllFilters();
            }, { once: true });
        }

        // Update badge count (for mobile Filters button)
        if (filterBadge) {
            filterBadge.textContent = String(chips.length);
            filterBadge.style.display = chips.length > 0 ? 'inline-flex' : 'none';
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

    // Quick search chips (hero)
    document.querySelectorAll('.rp-quick-chip').forEach(btn => {
        btn.addEventListener('click', function() {
            const term = (this.getAttribute('data-search') || '').toLowerCase().trim();
            const searchInput = document.getElementById('searchInput');
            if (searchInput) {
                searchInput.value = term;
                searchTerm = term;
                filterProperties();
            }
        });
    });
    
    // Clear all filters button
    const clearFiltersBtn = document.getElementById('clearFilters');
    function resetAllFilters() {
        // Reset search input
        if (searchInput) {
            searchInput.value = '';
            searchTerm = '';
        }
        
        // Reset price slider to max
        if (priceSlider) {
            priceSlider.value = 0;
            isPriceFilterActive = false;
            if (currentPrice) {
                currentPrice.textContent = '₱0';
            }
        }
        
        // Uncheck all categories (including "all")
        categoryFilters.forEach(filter => {
            filter.checked = false;
        });
        
        // Uncheck all amenity filters
        document.querySelectorAll('.amenity-filter').forEach(amenity => {
            amenity.checked = false;
        });
        
        // Re-filter to show all properties
        filterProperties();
        closeFilters();
    }

    if (clearFiltersBtn) {
        clearFiltersBtn.addEventListener('click', resetAllFilters);
    }

    // Initial UI sync
    filterProperties();
    
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
    
    // Navbar center search on scroll
    const navCenter = document.getElementById('navCenter');
    const navSearchInput = document.getElementById('navSearchInput');
    const navSearchBtn = document.getElementById('navSearchBtn');

    function syncSearchToMain(value) {
        if (!searchInput) return;
        searchInput.value = value;
        searchTerm = value.toLowerCase().trim();
        filterProperties();
    }

    if (navSearchInput) {
        navSearchInput.addEventListener('input', function() {
            syncSearchToMain(this.value);
        });
        navSearchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                syncSearchToMain(this.value);
            }
        });
    }

    if (navSearchBtn && navSearchInput) {
        navSearchBtn.addEventListener('click', function() {
            syncSearchToMain(navSearchInput.value);
        });
    }

    if (searchInput && navSearchInput) {
        // Keep navbar search in sync when typing in the hero search
        searchInput.addEventListener('input', function() {
            navSearchInput.value = this.value;
        });
    }

    const navbar = document.querySelector('.navbar');
    function updateNavCenterVisibility() {
        const y = window.pageYOffset || 0;
        const show = y > 220; // after hero area starts to scroll away
        document.body.classList.toggle('rp-nav-search', show);
        if (show && navSearchInput && searchInput) {
            navSearchInput.value = searchInput.value || '';
        }
        if (!show && navCenter) {
            // nothing else; CSS handles hiding
        }
    }

    if (navbar) {
        window.addEventListener('scroll', updateNavCenterVisibility, { passive: true });
        updateNavCenterVisibility();
    }

    // Close filters on Escape (unless a modal is open)
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            if (document.body.classList.contains('modal-open')) return;
            closeFilters();
        }
    });

    // Logged-out burger menu
    (function () {
        const trigger = document.getElementById('navBurgerTrigger');
        const panel = document.getElementById('navBurgerPanel');
        const wrap = trigger && trigger.closest('.rp-burger-menu');
        if (!trigger || !panel) return;

        function open() {
            panel.classList.add('rp-burger-panel-open');
            trigger.classList.add('rp-burger-trigger-open');
            trigger.setAttribute('aria-expanded', 'true');
            panel.setAttribute('aria-hidden', 'false');
        }

        function close() {
            panel.classList.remove('rp-burger-panel-open');
            trigger.classList.remove('rp-burger-trigger-open');
            trigger.setAttribute('aria-expanded', 'false');
            panel.setAttribute('aria-hidden', 'true');
        }

        function toggle() {
            const isOpen = panel.classList.contains('rp-burger-panel-open');
            if (isOpen) close(); else open();
        }

        trigger.addEventListener('click', function (e) {
            e.stopPropagation();
            toggle();
        });

        // Open modal items
        panel.addEventListener('click', function (e) {
            const btn = e.target && e.target.closest ? e.target.closest('[data-open-modal]') : null;
            if (!btn) return;
            e.preventDefault();
            const modalId = btn.getAttribute('data-open-modal');
            if (typeof window.openModal === 'function' && modalId) {
                window.openModal(modalId);
            }
            close();
        });

        document.addEventListener('click', function (e) {
            if (wrap && wrap.contains(e.target)) return;
            close();
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') close();
        });
    })();
});
