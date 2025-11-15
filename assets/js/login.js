// VPN Remote - Login Page JavaScript

document.addEventListener('DOMContentLoaded', function() {
    // Password visibility toggle
    const togglePassword = document.getElementById('togglePassword');
    const passwordInput = document.getElementById('password');
    const toggleIcon = document.getElementById('toggleIcon');
    
    if (togglePassword && passwordInput && toggleIcon) {
        togglePassword.addEventListener('click', function() {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            
            // Toggle icon
            if (type === 'text') {
                toggleIcon.className = 'bi bi-eye-slash';
            } else {
                toggleIcon.className = 'bi bi-eye';
            }
        });
    }
    
    // Form validation and loading state
    const loginForm = document.querySelector('.login-form');
    const loginBtn = document.querySelector('.login-btn');
    
    if (loginForm && loginBtn) {
        loginForm.addEventListener('submit', function(e) {
            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value.trim();
            
            // Basic validation
            if (!username || !password) {
                e.preventDefault();
                showNotification('Please fill in all fields', 'warning');
                return false;
            }
            
            // Add loading state using the new button structure
            loginBtn.disabled = true;
            loginBtn.classList.add('loading');
            
            // Add loading class to form
            loginForm.classList.add('loading');
        });
    }
    
    // Auto-hide alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(function(alert) {
        setTimeout(function() {
            alert.style.transition = 'opacity 0.5s ease';
            alert.style.opacity = '0';
            setTimeout(function() {
                alert.remove();
            }, 500);
        }, 5000);
    });
    
    // Add entrance animation
    const loginContainer = document.querySelector('.login-container');
    if (loginContainer) {
        loginContainer.classList.add('fade-in');
    }
    
    // Focus management
    const usernameInput = document.getElementById('username');
    if (usernameInput && !usernameInput.value) {
        usernameInput.focus();
    } else if (passwordInput) {
        passwordInput.focus();
    }
    
    // Prevent form resubmission on page refresh
    if (window.history.replaceState) {
        window.history.replaceState(null, null, window.location.href);
    }
    
    // Credential suggestion functionality
    const defaultCredentials = document.querySelector('.default-credentials');
    if (defaultCredentials) {
        const credentialSpans = defaultCredentials.querySelectorAll('.credential');
        credentialSpans.forEach(function(span, index) {
            span.style.cursor = 'pointer';
            span.title = 'Click to use this credential';
            
            span.addEventListener('click', function() {
                const value = span.textContent;
                if (index === 0) {
                    // Username
                    const usernameInput = document.getElementById('username');
                    if (usernameInput) {
                        usernameInput.value = value;
                        usernameInput.focus();
                        usernameInput.blur();
                    }
                } else {
                    // Password
                    const passwordInput = document.getElementById('password');
                    if (passwordInput) {
                        passwordInput.value = value;
                        passwordInput.focus();
                        passwordInput.blur();
                    }
                }
                
                // Add visual feedback
                span.style.transform = 'scale(0.95)';
                setTimeout(() => {
                    span.style.transform = 'scale(1)';
                }, 150);
            });
        });
    }
    
    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        // Ctrl/Cmd + Enter to submit form
        if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
            const submitBtn = document.querySelector('.login-btn');
            if (submitBtn && !submitBtn.disabled) {
                loginForm.submit();
            }
        }
    });
});

// Notification system
function showNotification(message, type = 'info', duration = 4000) {
    // Remove existing notifications
    const existingNotifications = document.querySelectorAll('.custom-notification');
    existingNotifications.forEach(n => n.remove());
    
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `alert alert-${type} custom-notification`;
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 9999;
        min-width: 300px;
        animation: slideInRight 0.3s ease-out;
        border: none;
        box-shadow: 0 0.5rem 2rem rgba(0, 0, 0, 0.3);
        backdrop-filter: blur(10px);
    `;
    
    let icon = 'bi-info-circle';
    if (type === 'warning') icon = 'bi-exclamation-triangle';
    if (type === 'danger') icon = 'bi-x-circle';
    if (type === 'success') icon = 'bi-check-circle';
    
    notification.innerHTML = `
        <i class="bi ${icon} me-2"></i>
        ${message}
        <button type="button" class="btn-close ms-auto" style="filter: invert(1) grayscale(100%) brightness(200%);"></button>
    `;
    
    // Add close functionality
    const closeBtn = notification.querySelector('.btn-close');
    closeBtn.addEventListener('click', () => {
        notification.style.animation = 'slideOutRight 0.3s ease-out';
        setTimeout(() => notification.remove(), 300);
    });
    
    // Add to document
    document.body.appendChild(notification);
    
    // Auto remove
    setTimeout(() => {
        if (notification.parentNode) {
            notification.style.animation = 'slideOutRight 0.3s ease-out';
            setTimeout(() => notification.remove(), 300);
        }
    }, duration);
}

// Add notification animations to CSS dynamically
const style = document.createElement('style');
style.textContent = `
    @keyframes slideInRight {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    
    @keyframes slideOutRight {
        from { transform: translateX(0); opacity: 1; }
        to { transform: translateX(100%); opacity: 0; }
    }
    
    .credential {
        transition: all 0.2s ease;
    }
    
    .credential:hover {
        transform: scale(1.05);
        box-shadow: 0 2px 8px rgba(13, 110, 253, 0.3);
    }
`;
document.head.appendChild(style);