document.addEventListener('DOMContentLoaded', () => {
    // Password Toggle Visibility
    const toggleBtn = document.getElementById('togglePassword');
    const passwordInput = document.getElementById('password');
    
    if (toggleBtn && passwordInput) {
        toggleBtn.addEventListener('click', function() {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            
            const icon = this.querySelector('i');
            if (icon) {
                icon.className = type === 'password' ? 'bi bi-eye' : 'bi bi-eye-slash';
            }
            
            // Add a subtle bounce animation to the icon
            icon.classList.add('animate__animated', 'animate__bounceIn');
            setTimeout(() => {
                icon.classList.remove('animate__animated', 'animate__bounceIn');
            }, 500);
        });
    }
    
    // Form Submission Animation
    const loginForm = document.getElementById('loginForm');
    if (loginForm) {
        loginForm.addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('button[type="submit"]');
            
            // Prevent double submission
            if (submitBtn.disabled) {
                e.preventDefault();
                return;
            }
            
            // Add loading state
            const originalText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = `
                <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                Autenticando...
            `;
            
            // Form continues to submit natively
        });
    }
    
    // Input Floating Effect Enhancements
    const inputs = document.querySelectorAll('.form-control-styled');
    inputs.forEach(input => {
        // Check initial state
        if (input.value) {
            input.classList.add('has-value');
        }
        
        input.addEventListener('input', function() {
            if (this.value) {
                this.classList.add('has-value');
            } else {
                this.classList.remove('has-value');
            }
        });
    });
});
