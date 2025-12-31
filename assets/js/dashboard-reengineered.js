document.addEventListener('DOMContentLoaded', function () {

    // Greeting Logic
    updateGreeting();

    // Mobile Sidebar Toggle
    const toggleBtn = document.getElementById('sidebarToggle');
    const sidebar = document.querySelector('.sidebar-glass');
    const overlay = document.querySelector('.dashboard-mobile-overlay');

    if (toggleBtn) {
        toggleBtn.addEventListener('click', function () {
            sidebar.classList.toggle('show');
            overlay.classList.toggle('show');

            const icon = this.querySelector('i');
            if (sidebar.classList.contains('show')) {
                icon.classList.remove('bi-list');
                icon.classList.add('bi-x-lg');
            } else {
                icon.classList.remove('bi-x-lg');
                icon.classList.add('bi-list');
            }
        });
    }

    if (overlay) {
        overlay.addEventListener('click', function () {
            sidebar.classList.remove('show');
            overlay.classList.remove('show');
            if (toggleBtn) {
                const icon = toggleBtn.querySelector('i');
                icon.classList.remove('bi-x-lg');
                icon.classList.add('bi-list');
            }
        });
    }

    // Desktop Sidebar Toggle
    const desktopToggle = document.getElementById('desktopSidebarToggle');
    const closeBtn = document.getElementById('sidebarCloseBtn');
    const wrapper = document.querySelector('.dashboard-wrapper');

    if (desktopToggle && wrapper) {
        desktopToggle.addEventListener('click', function () {
            wrapper.classList.remove('sidebar-collapsed');
        });
    }

    if (closeBtn && wrapper) {
        closeBtn.addEventListener('click', function () {
            wrapper.classList.add('sidebar-collapsed');
        });
    }

    // Add staggered animation classes to cards
    const cards = document.querySelectorAll('.card-glass');
    cards.forEach((card, index) => {
        card.classList.add('animate-fade-in-up');
        // Add increasing delay
        const delay = Math.min((index + 1) * 100, 500); // Cap at 500ms
        card.style.animationDelay = `${delay}ms`;
    });
});

function updateGreeting() {
    const greetingElement = document.getElementById('greeting-text');
    if (!greetingElement) return;

    const hour = new Date().getHours();
    let greeting = 'Bienvenido';

    if (hour >= 5 && hour < 12) {
        greeting = 'Buenos días';
    } else if (hour >= 12 && hour < 19) {
        greeting = 'Buenas tardes';
    } else {
        greeting = 'Buenas noches';
    }

    const originalText = greetingElement.innerText;
    // Assuming the text format is "Bienvenido/a, [Name]"
    // We want to replace "Bienvenido/a" with the time-based greeting
    if (originalText.includes(',')) {
        const namePart = originalText.split(',')[1];
        greetingElement.innerText = `${greeting},${namePart}`;
    }
}
