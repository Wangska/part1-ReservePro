// Modal functionality

function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('active');
        document.body.classList.add('modal-open');
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('active');
        document.body.classList.remove('modal-open');
    }
}

function switchModal(closeModalId, openModalId) {
    closeModal(closeModalId);
    setTimeout(() => {
        openModal(openModalId);
    }, 300);
}

// Close modal when clicking escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const activeModal = document.querySelector('.modal.active');
        if (activeModal) {
            closeModal(activeModal.id);
        }
    }
});

// Prevent modal content from closing when clicking inside
document.querySelectorAll('.modal-content').forEach(content => {
    content.addEventListener('click', function(e) {
        e.stopPropagation();
    });
});

// Show role description
function showRoleInfo(role) {
    // Check for both modal and standalone page role info elements
    const roleDesc = document.getElementById('role-description') || document.getElementById('modal_role_info');
    
    if (roleDesc) {
        roleDesc.style.display = 'block';
        
        if (role === 'guest') {
            roleDesc.className = 'role-info guest';
            roleDesc.innerHTML = '<strong>As a Guest:</strong> Browse and book amazing properties worldwide. Save favorites and manage your bookings easily.';
        } else if (role === 'host') {
            roleDesc.className = 'role-info host';
            roleDesc.innerHTML = '<strong>As a Host:</strong> List your properties, manage bookings, and earn income. Access powerful tools to grow your business.';
        }
    }
}

// Show role info on page load if role is selected
document.addEventListener('DOMContentLoaded', function() {
    const roleSelect = document.getElementById('register-role');
    if (roleSelect && roleSelect.value) {
        showRoleInfo(roleSelect.value);
    }
});
