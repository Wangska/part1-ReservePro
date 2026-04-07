// Theme Toggle Functionality

// Get theme from localStorage or default to dark
function getTheme() {
    const savedTheme = localStorage.getItem('theme');
    return savedTheme || 'dark';
}

// Set theme
function setTheme(theme) {
    if (theme === 'light') {
        document.body.classList.add('light-mode');
        updateToggleButton('light');
    } else {
        document.body.classList.remove('light-mode');
        updateToggleButton('dark');
    }
    document.querySelectorAll('.theme-toggle').forEach(toggle => {
        toggle.setAttribute('aria-pressed', theme === 'light' ? 'true' : 'false');
    });
    localStorage.setItem('theme', theme);
}

// Update every theme toggle on the page (sidebar + header, etc.)
function updateToggleButton(theme) {
<<<<<<< HEAD
    const showMoon = theme === 'light';
    const toggles = document.querySelectorAll('.theme-toggle');
    toggles.forEach(toggle => {
        const sun = toggle.querySelector('.theme-icon-sun');
        const moon = toggle.querySelector('.theme-icon-moon');
        if (!sun || !moon) return;
        // When theme is light, show moon (action = switch to dark)
        moon.style.display = showMoon ? 'block' : 'none';
        sun.style.display = showMoon ? 'none' : 'block';
    });
}

// Ensure all toggle buttons use the same SVG icons
function ensureToggleIcons() {
    const toggles = document.querySelectorAll('.theme-toggle');
    toggles.forEach(toggle => {
        if (!toggle.hasAttribute('aria-label')) {
            toggle.setAttribute('aria-label', 'Toggle theme');
        }
        if (toggle.tagName !== 'BUTTON') {
            toggle.setAttribute('role', 'button');
            if (!toggle.hasAttribute('tabindex')) {
                toggle.setAttribute('tabindex', '0');
            }
        }

        let wrapper = toggle.querySelector('.theme-toggle-icon');
        if (!wrapper) {
            wrapper = document.createElement('span');
            wrapper.className = 'theme-toggle-icon';
            wrapper.setAttribute('aria-hidden', 'true');
            toggle.prepend(wrapper);
        }

        if (wrapper.querySelector('.theme-icon-sun') && wrapper.querySelector('.theme-icon-moon')) {
            const staleText = toggle.querySelector('.theme-toggle-text');
            if (staleText) staleText.remove();
            return;
        }
        wrapper.innerHTML = `
            <svg class="theme-icon theme-icon-sun" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="4"></circle>
                <path d="M12 2v2"></path>
                <path d="M12 20v2"></path>
                <path d="M4.93 4.93l1.41 1.41"></path>
                <path d="M17.66 17.66l1.41 1.41"></path>
                <path d="M2 12h2"></path>
                <path d="M20 12h2"></path>
                <path d="M6.34 17.66l-1.41 1.41"></path>
                <path d="M19.07 4.93l-1.41 1.41"></path>
            </svg>
            <svg class="theme-icon theme-icon-moon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
            </svg>
        `;
        const staleText = toggle.querySelector('.theme-toggle-text');
        if (staleText) staleText.remove();
=======
    document.querySelectorAll('.theme-toggle').forEach(function (toggle) {
        var icon = toggle.querySelector('.theme-toggle-icon');
        var text = toggle.querySelector('.theme-toggle-text');
        if (!icon || !text) return;
        if (theme === 'light') {
            icon.textContent = '🌙';
            text.textContent = 'Dark';
        } else {
            icon.textContent = '☀️';
            text.textContent = 'Light';
        }
>>>>>>> 0373a6bf3ef99e5d13df91f810144365a730d6bf
    });
}

// Toggle theme
function toggleTheme() {
    const currentTheme = getTheme();
    const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
    setTheme(newTheme);
}

// Initialize theme on page load
document.addEventListener('DOMContentLoaded', function() {
    ensureToggleIcons();

    const theme = getTheme();
    setTheme(theme);
    
<<<<<<< HEAD
    // Add click event to all toggle buttons
    document.querySelectorAll('.theme-toggle').forEach(toggleButton => {
        toggleButton.addEventListener('click', toggleTheme);
        toggleButton.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                toggleTheme();
            }
        });
=======
    document.querySelectorAll('.theme-toggle').forEach(function (toggleButton) {
        toggleButton.addEventListener('click', toggleTheme);
>>>>>>> 0373a6bf3ef99e5d13df91f810144365a730d6bf
    });
});

// Apply theme immediately (before DOM loads) to prevent flash
(function() {
    const theme = localStorage.getItem('theme') || 'dark';
    if (theme === 'light') {
        document.documentElement.classList.add('light-mode');
        if (document.body) {
            document.body.classList.add('light-mode');
        }
    }
})();
