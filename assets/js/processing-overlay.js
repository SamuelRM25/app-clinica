/* ==========================================================================
   assets/js/processing-overlay.js
   Overlay global anti-multiclick. Muestra un spinner profesional mientras se
   procesan fetch POST/PUT/DELETE y bloquea toda interacción del usuario.
   ========================================================================== */
(function () {
    'use strict';

    const OVERLAY_ID = 'globalProcessingOverlay';
    const SAFETY_TIMEOUT_MS = 30000;
    let safetyTimer = null;
    let fetchWrapperInstalled = false;
    const originalFetch = window.fetch ? window.fetch.bind(window) : null;

    function ensureOverlay() {
        let el = document.getElementById(OVERLAY_ID);
        if (el) return el;

        el = document.createElement('div');
        el.id = OVERLAY_ID;
        el.className = 'processing-overlay';
        el.setAttribute('aria-hidden', 'true');
        el.setAttribute('role', 'status');
        el.innerHTML = `
            <div class="processing-overlay__card">
                <div class="processing-overlay__spinner" aria-hidden="true"></div>
                <h4 class="processing-overlay__title">Procesando...</h4>
                <p class="processing-overlay__subtitle" id="globalProcessingSubtitle"></p>
            </div>
        `;
        document.body.appendChild(el);
        return el;
    }

    function setVisible(visible) {
        const el = ensureOverlay();
        const subtitle = el.querySelector('.processing-overlay__subtitle');
        const title = el.querySelector('.processing-overlay__title');

        if (visible) {
            if (subtitle && subtitle.dataset.empty === '1') {
                subtitle.style.display = 'none';
                el.querySelector('.processing-overlay__card').classList.add('processing-overlay__card--compact');
            }
            el.classList.add('processing-overlay--visible');
            el.setAttribute('aria-hidden', 'false');
            document.body.classList.add('processing-overlay-active');
        } else {
            el.classList.remove('processing-overlay--visible');
            el.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('processing-overlay-active');
            if (subtitle) {
                subtitle.style.display = '';
                subtitle.dataset.empty = '0';
            }
            el.querySelector('.processing-overlay__card').classList.remove('processing-overlay__card--compact');
        }
    }

    window.showProcessing = function (title, subtitle) {
        const el = ensureOverlay();
        const titleEl = el.querySelector('.processing-overlay__title');
        const subtitleEl = el.querySelector('.processing-overlay__subtitle');
        const card = el.querySelector('.processing-overlay__card');

        if (titleEl && typeof title === 'string' && title.length > 0) {
            titleEl.textContent = title;
        }
        if (subtitleEl) {
            if (typeof subtitle === 'string' && subtitle.length > 0) {
                subtitleEl.textContent = subtitle;
                subtitleEl.style.display = '';
                subtitleEl.dataset.empty = '0';
                if (card) card.classList.remove('processing-overlay__card--compact');
            } else {
                subtitleEl.textContent = '';
                subtitleEl.style.display = 'none';
                subtitleEl.dataset.empty = '1';
                if (card) card.classList.add('processing-overlay__card--compact');
            }
        }

        setVisible(true);

        if (safetyTimer) clearTimeout(safetyTimer);
        safetyTimer = setTimeout(() => {
            console.warn('[processing-overlay] safety timeout — ocultando overlay tras', SAFETY_TIMEOUT_MS, 'ms');
            window.hideProcessing();
        }, SAFETY_TIMEOUT_MS);
    };

    window.hideProcessing = function () {
        if (safetyTimer) {
            clearTimeout(safetyTimer);
            safetyTimer = null;
        }
        setVisible(false);
    };

    /**
     * Envuelve window.fetch para mostrar/ocultar el overlay automáticamente
     * en requests POST/PUT/PATCH/DELETE (modificaciones). Las requests GET y HEAD
     * no activan el overlay.
     */
    function installFetchWrapper() {
        if (fetchWrapperInstalled || !originalFetch) return;
        fetchWrapperInstalled = true;

        window.fetch = function (input, init) {
            try {
                const method = ((init && init.method) || (typeof input === 'object' && input && input.method) || 'GET').toUpperCase();
                const shouldShow = (method === 'POST' || method === 'PUT' || method === 'PATCH' || method === 'DELETE');

                if (shouldShow) {
                    window.showProcessing();
                }

                const promise = originalFetch(input, init);

                if (shouldShow && promise && typeof promise.then === 'function') {
                    promise.then(
                        function () {
                            window.hideProcessing();
                        },
                        function () {
                            window.hideProcessing();
                        }
                    );
                }
                return promise;
            } catch (err) {
                window.hideProcessing();
                throw err;
            }
        };
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', installFetchWrapper);
    } else {
        installFetchWrapper();
    }
})();
