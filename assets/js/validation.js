// Form validation and UX enhancements

document.addEventListener('DOMContentLoaded', function() {
    // Get forms
    const registerForm = document.getElementById('registerForm');
    const loginForm = document.getElementById('loginForm');
    
    // Register form validation
    if (registerForm) {
        const password = document.getElementById('password');
        const confirmPassword = document.getElementById('confirm_password');
        const firstName = document.getElementById('first_name');
        const lastName = document.getElementById('last_name');
        const emailInput = document.getElementById('email');
        const submitBtn = document.getElementById('submitBtn');
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

        function setFieldError(el, hasError) {
            if (!el) return;
            if (hasError) el.classList.add('field-error');
            else el.classList.remove('field-error');
        }

        function clearFieldErrors() {
            const marked = registerForm.querySelectorAll('input.field-error');
            marked.forEach(el => el.classList.remove('field-error'));
        }
        
        // Real-time password match validation
        confirmPassword.addEventListener('input', function() {
            if (password.value !== confirmPassword.value) {
                confirmPassword.setCustomValidity("Passwords don't match");
                confirmPassword.style.borderColor = '#FF385C';
                setFieldError(confirmPassword, true);
            } else {
                confirmPassword.setCustomValidity('');
                confirmPassword.style.borderColor = '#DDDDDD';
                setFieldError(confirmPassword, false);
            }
        });
        
        password.addEventListener('input', function() {
            if (confirmPassword.value && password.value !== confirmPassword.value) {
                confirmPassword.setCustomValidity("Passwords don't match");
                confirmPassword.style.borderColor = '#FF385C';
                setFieldError(confirmPassword, true);
            } else {
                confirmPassword.setCustomValidity('');
                confirmPassword.style.borderColor = '#DDDDDD';
                setFieldError(confirmPassword, false);
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
            clearFieldErrors();

            const email = (emailInput?.value || '').trim();
            const first = (firstName?.value || '').trim();
            const last = (lastName?.value || '').trim();
            const pass = (password?.value || '');
            const confirm = (confirmPassword?.value || '');

            let hasError = false;

            if (!first) { setFieldError(firstName, true); hasError = true; }
            if (!last) { setFieldError(lastName, true); hasError = true; }
            if (!email || !emailRegex.test(email)) { setFieldError(emailInput, true); hasError = true; }

            if (!pass || pass.length < 8) { setFieldError(password, true); hasError = true; }
            if (!confirm || confirm !== pass) { setFieldError(confirmPassword, true); hasError = true; }

            if (hasError) {
                e.preventDefault();
                alert("Please fix the highlighted fields.");
                return;
            }
            
            submitBtn.classList.add('loading');
            submitBtn.disabled = true;
        });

        // Highlight invalid email after user interacts
        emailInput.addEventListener('blur', function() {
            const email = (this.value || '').trim();
            if (email && !emailRegex.test(email)) setFieldError(emailInput, true);
            else setFieldError(emailInput, false);
        });

        emailInput.addEventListener('input', function() {
            const email = (this.value || '').trim();
            if (!email || emailRegex.test(email)) setFieldError(emailInput, false);
        });

        // If the browser triggers constraint validation first, we still mark the field
        registerForm.addEventListener('invalid', function(e) {
            if (e.target !== emailInput) return;
            const email = (emailInput.value || '').trim();
            if (email && !emailRegex.test(email)) setFieldError(emailInput, true);
        }, true);
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
