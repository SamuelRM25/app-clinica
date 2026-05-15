/**
 * Security script to protect source code inspection
 * RS SOLUTIONS - Derechos Reservados
 */

(function() {
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

    // DevTools Detection
    const protectContent = () => {
        document.body.innerHTML = `
            <div style="
                display: flex; 
                flex-direction: column;
                justify-content: center; 
                align-items: center; 
                height: 100vh; 
                width: 100vw; 
                background: #f8f9fa; 
                color: #333; 
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                text-align: center;
                position: fixed;
                top: 0;
                left: 0;
                z-index: 999999;
            ">
                <h1 style="font-size: 2rem; margin-bottom: 1rem; color: #dc3545;">Contenido Protegido</h1>
                <p style="font-size: 1.2rem; font-weight: 500;">Derechos reservados RS SOLUTIONS</p>
                <div style="margin-top: 2rem; padding: 1rem; border-top: 1px solid #dee2e6; color: #6c757d;">
                    El acceso a las herramientas de desarrollador está restringido en esta plataforma.
                </div>
            </div>
        `;
    };

    // Detection using debugger
    setInterval(function() {
        const startTime = performance.now();
        debugger;
        const endTime = performance.now();
        if (endTime - startTime > 100) {
            protectContent();
        }
    }, 1000);

    // Detection using window size (if DevTools is docked)
    const threshold = 160;
    const checkSize = () => {
        const widthThreshold = window.outerWidth - window.innerWidth > threshold;
        const heightThreshold = window.outerHeight - window.innerHeight > threshold;
        if (widthThreshold || heightThreshold) {
            protectContent();
        }
    };

    window.addEventListener('resize', checkSize);
    checkSize();

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
