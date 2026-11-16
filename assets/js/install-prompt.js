/**
 * install-prompt.js
 * PWA Install Prompt + Service Worker Registration
 * Centro Médico Herrera Saenz
 */
(function () {
    'use strict';

    // ====================================================
    // Detección de entorno
    // ====================================================
    const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;
    const isAndroid = /Android/i.test(navigator.userAgent);
    const isStandalone = window.matchMedia('(display-mode: standalone)').matches
                      || window.navigator.standalone === true;
    const isSafari = /^((?!chrome|android).)*safari/i.test(navigator.userAgent);

    let deferredPrompt = null;
    let installButtonShown = false;

    // ====================================================
    // Detección dinámica del base path del proyecto
    // (la app puede estar en /, /app/, /GitHub/app-clinica/, etc.)
    // ====================================================
    function getPwaBasePath() {
        // Prioridad 1: meta tag inyectado por PHP
        const meta = document.querySelector('meta[name="pwa-base"]');
        if (meta && meta.content) {
            let p = meta.content;
            if (!p.endsWith('/')) p += '/';
            if (!p.startsWith('/')) p = '/' + p;
            return p;
        }
        // Fallback: detectar desde location.pathname buscando /php/
        const path = window.location.pathname;
        const phpIdx = path.indexOf('/php/');
        if (phpIdx !== -1) {
            return path.substring(0, phpIdx + 1);
        }
        // Último fallback: raíz
        return '/';
    }

    // ====================================================
    // Service Worker Registration
    // ====================================================
    if ('serviceWorker' in navigator) {
        const basePath = getPwaBasePath();
        const swUrl = basePath + 'sw.js';
        const swScope = basePath;

        console.log('[PWA] Registrando Service Worker en:', swUrl, 'con scope:', swScope);

        window.addEventListener('load', () => {
            navigator.serviceWorker.register(swUrl, { scope: swScope })
                .then((reg) => {
                    console.log('[PWA] Service Worker registrado. Scope:', reg.scope);

                    // Detectar actualizaciones
                    reg.addEventListener('updatefound', () => {
                        const newWorker = reg.installing;
                        if (!newWorker) return;
                        console.log('[PWA] Nueva versión detectada, instalando...');

                        newWorker.addEventListener('statechange', () => {
                            if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                                console.log('[PWA] Nueva versión lista, mostrando notificación');
                                showUpdateToast();
                            }
                        });
                    });
                })
                .catch((err) => {
                    console.warn('[PWA] Error registrando Service Worker en', swUrl, ':', err.message);

                    // Fallback: intentar con scope mínimo './' (algunos servidores lo bloquean)
                    if (swScope !== './') {
                        console.log('[PWA] Intentando fallback con scope "./"...');
                        navigator.serviceWorker.register(swUrl, { scope: './' })
                            .then((reg) => console.log('[PWA] Fallback OK. Scope:', reg.scope))
                            .catch((e2) => console.warn('[PWA] Fallback también falló:', e2.message));
                    }
                });
        });

        // Escuchar mensajes del SW
        navigator.serviceWorker.addEventListener('message', (event) => {
            if (event.data && event.data.type === 'SW_UPDATED') {
                console.log('[PWA] Service Worker actualizado a:', event.data.version);
                showUpdateToast();
            }
        });
    }

    // ====================================================
    // Botón de instalación
    // ====================================================
    function getInstallButton() {
        return document.getElementById('installAppBtn');
    }

    function showInstallButton() {
        if (isStandalone) return;  // Ya instalada, no mostrar
        const btn = getInstallButton();
        if (btn && !installButtonShown) {
            btn.style.display = 'inline-flex';
            installButtonShown = true;
            console.log('[PWA] Botón de instalación visible');
        }
    }

    function hideInstallButton() {
        const btn = getInstallButton();
        if (btn) {
            btn.style.display = 'none';
            installButtonShown = false;
        }
    }

    // ====================================================
    // Evento nativo beforeinstallprompt (Android/Chrome/Edge)
    // ====================================================
    window.addEventListener('beforeinstallprompt', (e) => {
        console.log('[PWA] beforeinstallprompt disparado');
        e.preventDefault();
        deferredPrompt = e;
        showInstallButton();
    });

    // App ya instalada
    window.addEventListener('appinstalled', () => {
        console.log('[PWA] App instalada correctamente');
        hideInstallButton();
        deferredPrompt = null;
        if (window.Swal) {
            Swal.fire({
                icon: 'success',
                title: '¡App instalada!',
                text: 'ClinicApp se agregó a tu pantalla de inicio.',
                timer: 2500,
                showConfirmButton: false
            });
        }
    });

    // ====================================================
    // Detección iOS (no soporta beforeinstallprompt)
    // ====================================================
    if (isIOS && !isStandalone) {
        // En iOS, mostrar el botón siempre para que aparezca el instructivo
        setTimeout(() => {
            showInstallButton();
        }, 1500);
    }

    // ====================================================
    // Click en el botón de instalación
    // ====================================================
    document.addEventListener('click', async (e) => {
        const installBtn = e.target.closest('#installAppBtn');
        if (!installBtn) return;

        e.preventDefault();

        // iOS: mostrar instrucciones manuales
        if (isIOS) {
            showIOSInstructions();
            return;
        }

        // Android/Desktop con prompt nativo capturado
        if (deferredPrompt) {
            try {
                installBtn.disabled = true;
                const originalHtml = installBtn.innerHTML;
                installBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> <span class="install-label-full">Instalando...</span>';

                deferredPrompt.prompt();
                const { outcome } = await deferredPrompt.userChoice;

                console.log('[PWA] Resultado de instalación:', outcome);

                if (outcome === 'accepted') {
                    hideInstallButton();
                } else {
                    // Usuario rechazó, rehabilitar botón
                    installBtn.disabled = false;
                    installBtn.innerHTML = originalHtml;
                }
            } catch (err) {
                console.error('[PWA] Error al instalar:', err);
                installBtn.disabled = false;
            } finally {
                deferredPrompt = null;
            }
            return;
        }

        // No hay prompt disponible (ej. usuario en navegador que no soporta PWA)
        showBrowserNotSupported();
    });

    // ====================================================
    // Instrucciones para iOS
    // ====================================================
    function showIOSInstructions() {
        if (!window.Swal) {
            alert('Para instalar en iOS:\n\n1. Toca el botón Compartir (cuadrado con flecha hacia arriba) en la barra inferior de Safari\n\n2. Selecciona "Añadir a pantalla de inicio"\n\n3. Toca "Añadir" en la esquina superior derecha');
            return;
        }

        Swal.fire({
            icon: 'info',
            title: 'Instalar en iOS',
            html: `
                <div class="text-start">
                    <p class="mb-3">Safari en iPhone no permite instalación automática. Sigue estos pasos para añadir la app a tu pantalla de inicio:</p>
                    <ol class="text-start ps-3" style="line-height: 1.8;">
                        <li>Toca el botón <strong>Compartir</strong> <i class="bi bi-box-arrow-up text-primary"></i> en la barra inferior</li>
                        <li>Selecciona <strong>"Añadir a pantalla de inicio"</strong> <i class="bi bi-plus-square text-primary"></i></li>
                        <li>Toca <strong>"Añadir"</strong> en la esquina superior derecha</li>
                    </ol>
                    <div class="alert alert-info mt-3 mb-0 py-2" style="font-size: 0.85rem;">
                        <i class="bi bi-info-circle me-1"></i>
                        El icono <strong>CLINICAPP</strong> aparecerá en tu pantalla de inicio.
                    </div>
                </div>
            `,
            confirmButtonText: 'Entendido',
            confirmButtonColor: '#0d6efd',
            width: 500
        });
    }

    // ====================================================
    // Navegador no soporta PWA
    // ====================================================
    function showBrowserNotSupported() {
        if (!window.Swal) return;
        Swal.fire({
            icon: 'info',
            title: 'Instalación no disponible',
            html: `
                <p>Tu navegador no soporta instalación de apps web.</p>
                <p class="mb-0">Te recomendamos usar <strong>Chrome</strong> o <strong>Edge</strong> en Android/Desktop, o <strong>Safari</strong> en iOS.</p>
            `,
            confirmButtonColor: '#0d6efd'
        });
    }

    // ====================================================
    // Toast de actualización disponible
    // ====================================================
    function showUpdateToast() {
        // No mostrar si la app está en modo standalone y el usuario cerró antes
        if (sessionStorage.getItem('pwa_update_dismissed') === 'true') return;

        // Remover toast anterior si existe
        const existing = document.getElementById('pwaUpdateToast');
        if (existing) existing.remove();

        const toast = document.createElement('div');
        toast.id = 'pwaUpdateToast';
        toast.className = 'pwa-update-toast';
        toast.setAttribute('role', 'alert');
        toast.innerHTML = `
            <div class="pwa-toast-icon"><i class="bi bi-arrow-clockwise"></i></div>
            <div class="pwa-toast-body">
                <p class="pwa-toast-title">Nueva versión disponible</p>
                <p class="pwa-toast-text">Hay mejoras listas para usar</p>
            </div>
            <div class="pwa-toast-actions">
                <button class="pwa-toast-btn primary" id="pwaUpdateBtn">Recargar</button>
                <button class="pwa-toast-btn secondary" id="pwaUpdateDismiss" title="Cerrar">×</button>
            </div>
        `;
        document.body.appendChild(toast);

        document.getElementById('pwaUpdateBtn').addEventListener('click', () => {
            if (navigator.serviceWorker.controller) {
                navigator.serviceWorker.controller.postMessage({ type: 'SKIP_WAITING' });
            }
            // Recargar la página después de activar el nuevo SW
            setTimeout(() => window.location.reload(), 300);
        });

        document.getElementById('pwaUpdateDismiss').addEventListener('click', () => {
            toast.remove();
            sessionStorage.setItem('pwa_update_dismissed', 'true');
        });

        // Auto-dismiss después de 15 segundos
        setTimeout(() => {
            if (document.getElementById('pwaUpdateToast')) {
                toast.style.opacity = '0';
                toast.style.transition = 'opacity 0.3s';
                setTimeout(() => toast.remove(), 300);
            }
        }, 15000);
    }

    // ====================================================
    // Exponer estado para debugging
    // ====================================================
    window.PWA_DEBUG = {
        isIOS,
        isAndroid,
        isStandalone,
        isSafari,
        canInstall: () => !!deferredPrompt,
        version: '1.0.0'
    };

    console.log('[PWA] install-prompt.js cargado. Entorno:', {
        iOS: isIOS,
        Android: isAndroid,
        Standalone: isStandalone,
        Safari: isSafari
    });
})();
