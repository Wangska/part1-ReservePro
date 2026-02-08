// Landing page interactions

document.addEventListener('DOMContentLoaded', function() {
    
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
    
    // Filter checkboxes
    const checkboxes = document.querySelectorAll('.filter-checkbox input[type="checkbox"]');
    checkboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            console.log('Filter changed:', this.parentElement.textContent.trim());
            // Add your filter logic here
        });
    });
    
    // Clear all filters
    const resetButton = document.querySelector('.filter-reset');
    if (resetButton) {
        resetButton.addEventListener('click', function() {
            checkboxes.forEach(checkbox => {
                if (!checkbox.parentElement.textContent.includes('All Services')) {
                    checkbox.checked = false;
                }
            });
            console.log('Filters cleared');
        });
    }
    
    // Search functionality
    const searchInput = document.querySelector('.search-input');
    const searchBtn = document.querySelector('.search-btn');
    
    if (searchBtn && searchInput) {
        searchBtn.addEventListener('click', function() {
            const query = searchInput.value.trim();
            if (query) {
                console.log('Searching for:', query);
                // Add your search logic here
            }
        });
        
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                searchBtn.click();
            }
        });
    }
    
    // Card click handlers
    const cards = document.querySelectorAll('.service-card');
    cards.forEach(card => {
        card.addEventListener('click', function() {
            const title = this.querySelector('.card-title').textContent;
            console.log('Card clicked:', title);
            // Add navigation or modal logic here
        });
    });
    
    // Sort select
    const sortSelect = document.querySelector('.sort-select');
    if (sortSelect) {
        sortSelect.addEventListener('change', function() {
            console.log('Sort changed to:', this.value);
            // Add your sorting logic here
        });
    }
    
    // Price range slider
    const priceSlider = document.querySelector('.price-slider');
    if (priceSlider) {
        priceSlider.addEventListener('input', function() {
            console.log('Price range:', this.value);
            // Update price display or filter
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
    
    // Pagination
    const pageButtons = document.querySelectorAll('.page-btn');
    pageButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            if (!this.classList.contains('active') && this.textContent !== '...' && this.textContent !== 'Next') {
                pageButtons.forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
        });
    });
    
    // Add loading animation
    const loadingOverlay = document.createElement('div');
    loadingOverlay.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: white;
        z-index: 9999;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: opacity 0.5s ease;
    `;
    loadingOverlay.innerHTML = '<div style="font-size: 24px; font-weight: 700; color: #6366F1;">ServePro</div>';
    document.body.appendChild(loadingOverlay);
    
    setTimeout(() => {
        loadingOverlay.style.opacity = '0';
        setTimeout(() => {
            loadingOverlay.remove();
        }, 500);
    }, 500);
});
