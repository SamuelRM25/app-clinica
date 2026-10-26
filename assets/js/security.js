/**
 * Security script to protect source code inspection
 * RS SOLUTIONS - Derechos Reservados
 */

(function() {
    if (window.ES_CREADOR) return;
    // Disable right click
    document.addEventListener('contextmenu', function(e) {
        e.preventDefault();
    }, false);

    // Disable keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        // F12
        if (e.keyCode === 123) {
            e.preventDefault();
            return false;
        }
        // Ctrl+Shift+I, Ctrl+Shift+J, Ctrl+Shift+C
        if (e.ctrlKey && e.shiftKey && (e.keyCode === 73 || e.keyCode === 74 || e.keyCode === 67)) {
            e.preventDefault();
            return false;
        }
        // Ctrl+U (View Source)
        if (e.ctrlKey && e.keyCode === 85) {
            e.preventDefault();
            return false;
        }
        // Command+Option+I (Mac)
        if (e.metaKey && e.altKey && e.keyCode === 73) {
            e.preventDefault();
            return false;
        }
    }, false);

    // Prevent text selection and image dragging
    const style = document.createElement('style');
    style.innerHTML = `
        * {
            -webkit-user-select: none !important;
            -moz-user-select: none !important;
            -ms-user-select: none !important;
            user-select: none !important;
        }
        input, textarea {
            -webkit-user-select: text !important;
            -moz-user-select: text !important;
            -ms-user-select: text !important;
            user-select: text !important;
        }
        img {
            -webkit-user-drag: none !important;
            -khtml-user-drag: none !important;
            -moz-user-drag: none !important;
            -o-user-drag: none !important;
            user-drag: none !important;
        }
    `;
    document.head.appendChild(style);

    // Additional message in console
    console.log('%cContenido Protegido', 'color: red; font-size: 30px; font-weight: bold;');
    console.log('%cDerechos reservados RS SOLUTIONS', 'color: black; font-size: 18px;');

})();

// Theme and Global Settings Manager
(function() {
    // 1. Theme Configuration
    // Migración de marca (una sola vez por navegador): descartar temas guardados
    // antes de la paleta CMRM para que se vean los colores del logo.
    try {
        if (localStorage.getItem('brand-version') !== 'cmrm-1') {
            localStorage.removeItem('custom-full-theme');
            localStorage.removeItem('custom-primary-color');
            localStorage.setItem('brand-version', 'cmrm-1');
        }
    } catch (e) { }

    const applyTheme = () => {
        const theme = localStorage.getItem('dashboard-theme') || 'light';
        document.documentElement.setAttribute('data-theme', theme);
        
        // Optional custom colors from settings
        const customPrimary = localStorage.getItem('custom-primary-color');
        if (customPrimary) {
            document.documentElement.style.setProperty('--color-primary-day', customPrimary);
            document.documentElement.style.setProperty('--color-primary-night', customPrimary);
            document.documentElement.style.setProperty('--color-primary', customPrimary);
        }
    };

    // Apply immediately to prevent FOUC (Flash of Unstyled Content)
    applyTheme();

    // Listen for storage changes to sync across tabs
    window.addEventListener('storage', (e) => {
        if (e.key === 'dashboard-theme' || e.key === 'custom-primary-color') {
            applyTheme();
        }
    });
})();
