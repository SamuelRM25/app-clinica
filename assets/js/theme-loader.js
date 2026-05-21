/**
 * theme-loader.js
 * Carga y aplica el tema guardado en localStorage ANTES de que el DOM pinte.
 * Debe incluirse en <head> para evitar flash of unstyled content (FOUC).
 *
 * Maneja dos tipos de datos guardados:
 *  1. "dashboard-theme" → "light" | "dark"  (toggle simple)
 *  2. "custom-full-theme" → objeto JSON con colores, radius, shadow
 */
(function () {
    const root = document.documentElement;

    // ── 1. Aplicar modo claro/oscuro ──────────────────────────────────────────
    const mode = localStorage.getItem('dashboard-theme') || 'light';
    root.setAttribute('data-theme', mode);

    // ── 2. Aplicar tema completo (colores + radius + shadow) ──────────────────
    const raw = localStorage.getItem('custom-full-theme');
    if (raw) {
        try {
            const t = JSON.parse(raw);
            if (t.primary) {
                root.style.setProperty('--color-primary', t.primary);

                // Calcular RGB para sombras / rgba()
                const hex = t.primary.replace('#', '');
                const r = parseInt(hex.slice(0, 2), 16);
                const g = parseInt(hex.slice(2, 4), 16);
                const b = parseInt(hex.slice(4, 6), 16);
                root.style.setProperty('--color-primary-rgb', `${r},${g},${b}`);
            }
            if (t.bg)      root.style.setProperty('--color-bg',      t.bg);
            if (t.surface) root.style.setProperty('--color-surface',  t.surface);
            if (t.card)    root.style.setProperty('--color-card',     t.card);
            if (t.text)    root.style.setProperty('--color-text',     t.text);
            if (t.textSec) root.style.setProperty('--color-text-secondary', t.textSec);
            if (t.border)  root.style.setProperty('--color-border',   t.border);
            if (t.radius) {
                root.style.setProperty('--radius-md', t.radius);
                root.style.setProperty('--radius-lg', `calc(${t.radius} + 0.25rem)`);
                root.style.setProperty('--radius-xl', `calc(${t.radius} + 0.5rem)`);
            }
            if (t.shadow)  root.style.setProperty('--shadow-md', t.shadow);
        } catch (e) {
            // JSON corrupto — ignorar silenciosamente
            localStorage.removeItem('custom-full-theme');
        }
    }

    // ── 3. Sincronizar el botón de tema al cargarse ───────────────────────────
    // Esto se ejecuta después del DOMContentLoaded para no bloquear el pintado
    document.addEventListener('DOMContentLoaded', function () {
        const btn = document.getElementById('themeSwitch');
        if (btn) {
            btn.addEventListener('click', function () {
                const current = root.getAttribute('data-theme');
                const next = current === 'dark' ? 'light' : 'dark';
                root.setAttribute('data-theme', next);
                localStorage.setItem('dashboard-theme', next);
            });
        }
    });
})();
