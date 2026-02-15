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
    localStorage.setItem('theme', theme);
}

// Update toggle button appearance
function updateToggleButton(theme) {
    const icon = document.querySelector('.theme-toggle-icon');
    const text = document.querySelector('.theme-toggle-text');
    
    if (icon && text) {
        if (theme === 'light') {
            icon.textContent = '🌙';
            text.textContent = 'Dark';
        } else {
            icon.textContent = '☀️';
            text.textContent = 'Light';
        }
    }
}

// Toggle theme
function toggleTheme() {
    const currentTheme = getTheme();
    const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
    setTheme(newTheme);
}

// Initialize theme on page load
document.addEventListener('DOMContentLoaded', function() {
    const theme = getTheme();
    setTheme(theme);
    
    // Add click event to toggle button
    const toggleButton = document.querySelector('.theme-toggle');
    if (toggleButton) {
        toggleButton.addEventListener('click', toggleTheme);
    }
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
