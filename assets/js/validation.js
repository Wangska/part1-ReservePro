// Form validation and UX enhancements

document.addEventListener('DOMContentLoaded', function() {
    // Get forms
    const registerForm = document.getElementById('registerForm');
    const loginForm = document.getElementById('loginForm');
    
    // Register form validation
    if (registerForm) {
        const password = document.getElementById('password');
        const confirmPassword = document.getElementById('confirm_password');
        const submitBtn = document.getElementById('submitBtn');
        
        // Real-time password match validation
        confirmPassword.addEventListener('input', function() {
            if (password.value !== confirmPassword.value) {
                confirmPassword.setCustomValidity("Passwords don't match");
                confirmPassword.style.borderColor = '#FF385C';
            } else {
                confirmPassword.setCustomValidity('');
                confirmPassword.style.borderColor = '#DDDDDD';
            }
        });
        
        password.addEventListener('input', function() {
            if (confirmPassword.value && password.value !== confirmPassword.value) {
                confirmPassword.setCustomValidity("Passwords don't match");
                confirmPassword.style.borderColor = '#FF385C';
            } else {
                confirmPassword.setCustomValidity('');
                confirmPassword.style.borderColor = '#DDDDDD';
            }
        });
        
        // Password strength indicator
        password.addEventListener('input', function() {
            const value = password.value;
            if (value.length > 0 && value.length < 8) {
                password.style.borderColor = '#FF385C';
            } else if (value.length >= 8) {
                password.style.borderColor = '#00A699';
            } else {
                password.style.borderColor = '#DDDDDD';
            }
        });
        
        // Form submit with loading state
        registerForm.addEventListener('submit', function(e) {
            if (password.value !== confirmPassword.value) {
                e.preventDefault();
                alert("Passwords don't match!");
                return;
            }
            
            submitBtn.classList.add('loading');
            submitBtn.disabled = true;
        });
    }
    
    // Login form submit with loading state
    if (loginForm) {
        const submitBtn = document.getElementById('submitBtn');
        
        loginForm.addEventListener('submit', function() {
            submitBtn.classList.add('loading');
            submitBtn.disabled = true;
        });
    }
    
    // Add focus effects to all inputs
    const inputs = document.querySelectorAll('input');
    inputs.forEach(input => {
        input.addEventListener('focus', function() {
            this.parentElement.style.transform = 'translateY(-2px)';
            this.parentElement.style.transition = 'transform 0.2s ease';
        });
        
        input.addEventListener('blur', function() {
            this.parentElement.style.transform = 'translateY(0)';
        });
    });
    
    // Auto-hide error messages after 5 seconds
    const errorMessages = document.querySelector('.error-messages');
    if (errorMessages) {
        setTimeout(() => {
            errorMessages.style.transition = 'opacity 0.5s ease';
            errorMessages.style.opacity = '0';
            setTimeout(() => {
                errorMessages.style.display = 'none';
            }, 500);
        }, 5000);
    }
    
    // Email validation
    const emailInputs = document.querySelectorAll('input[type="email"]');
    emailInputs.forEach(input => {
        input.addEventListener('blur', function() {
            const email = this.value;
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            
            if (email && !emailRegex.test(email)) {
                this.style.borderColor = '#FF385C';
            } else if (email) {
                this.style.borderColor = '#00A699';
            } else {
                this.style.borderColor = '#DDDDDD';
            }
        });
    });
    
    // Add smooth animations
    const authBox = document.querySelector('.auth-box');
    if (authBox) {
        authBox.style.opacity = '0';
        authBox.style.transform = 'translateY(20px)';
        
        setTimeout(() => {
            authBox.style.transition = 'all 0.5s ease';
            authBox.style.opacity = '1';
            authBox.style.transform = 'translateY(0)';
        }, 100);
    }
});
