/**
 * ecg-strip.js — Trazo de electrocardiograma en SVG.
 *
 * Uso:  <div class="ecg-trace" data-ecg="loop"></div>   (barrido continuo, como un monitor)
 *       <div class="ecg-trace" data-ecg="once"></div>   (se dibuja una vez y queda fijo)
 * Opcional: data-beat="170" (px por latido), data-speed="140" (px por segundo).
 *
 * El color del trazo se toma de la variable CSS --ecg-ink del contenedor.
 * Respeta "reducir movimiento": en ese caso el trazo aparece completo, sin animación.
 */
(function () {
    'use strict';

    var NS = 'http://www.w3.org/2000/svg';
    var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    // Un latido (PQRST) en coordenadas relativas: x = fracción del latido, y = amplitud (negativo = arriba)
    var BEAT = [
        [0.00, 0], [0.10, 0],
        [0.13, -0.06], [0.16, -0.11], [0.19, -0.06], [0.22, 0],          // onda P
        [0.30, 0], [0.33, 0.10],                                          // Q
        [0.36, -1.00],                                                    // R
        [0.39, 0.30], [0.42, 0],                                          // S
        [0.54, 0], [0.58, -0.10], [0.63, -0.21], [0.68, -0.19], [0.72, -0.08], [0.76, 0], // onda T
        [1.00, 0]
    ];

    function buildPoints(width, height, beatPx) {
        // Amplitud realista: el complejo R mide unos 2 cuadros grandes del papel (máx. ~96 px)
        var amp = Math.min(height * 0.62, 96);
        var mid = height / 2 + amp * 0.38;
        var pts = [];
        // Desfase inicial para que el primer complejo no quede pegado al borde
        var x0 = -beatPx * 0.25;
        for (var start = x0; start < width + beatPx; start += beatPx) {
            // Pequeña variación natural entre latidos
            var k = 0.92 + ((Math.sin(start * 12.9898) + 1) / 2) * 0.12;
            for (var i = 0; i < BEAT.length; i++) {
                pts.push([start + BEAT[i][0] * beatPx, mid + BEAT[i][1] * amp * k]);
            }
        }
        return pts;
    }

    function toPath(pts) {
        var d = '';
        for (var i = 0; i < pts.length; i++) {
            d += (i ? 'L' : 'M') + pts[i][0].toFixed(1) + ' ' + pts[i][1].toFixed(1);
        }
        return d;
    }

    function yAt(pts, x) {
        for (var i = 1; i < pts.length; i++) {
            if (pts[i][0] >= x) {
                var a = pts[i - 1], b = pts[i];
                var t = (x - a[0]) / ((b[0] - a[0]) || 1);
                return a[1] + (b[1] - a[1]) * t;
            }
        }
        return pts[pts.length - 1][1];
    }

    function el(name, attrs) {
        var n = document.createElementNS(NS, name);
        for (var k in attrs) n.setAttribute(k, attrs[k]);
        return n;
    }

    var uid = 0;

    function mount(host) {
        var mode = host.getAttribute('data-ecg') || 'once';
        var beatPx = parseFloat(host.getAttribute('data-beat')) || 170;
        var speed = parseFloat(host.getAttribute('data-speed')) || 150;
        var raf = null;

        function render() {
            if (raf) cancelAnimationFrame(raf);
            host.innerHTML = '';
            var w = Math.max(host.clientWidth, 10);
            var h = Math.max(host.clientHeight, 10);
            var pts = buildPoints(w, h, beatPx);
            var d = toPath(pts);
            var id = 'ecg' + (++uid);

            var svg = el('svg', { width: w, height: h, viewBox: '0 0 ' + w + ' ' + h, 'aria-hidden': 'true', focusable: 'false' });
            var defs = el('defs', {});
            var clipNew = el('clipPath', { id: id + 'n' });
            var rNew = el('rect', { x: 0, y: 0, width: 0, height: h });
            clipNew.appendChild(rNew);
            var clipOld = el('clipPath', { id: id + 'o' });
            var rOld = el('rect', { x: 0, y: 0, width: 0, height: h });
            clipOld.appendChild(rOld);
            defs.appendChild(clipNew); defs.appendChild(clipOld);
            svg.appendChild(defs);

            var common = { d: d, fill: 'none', 'stroke-linejoin': 'round', 'stroke-linecap': 'round', class: 'ecg-line' };
            var oldPath = el('path', common); oldPath.setAttribute('clip-path', 'url(#' + id + 'o)'); oldPath.setAttribute('class', 'ecg-line ecg-line--old');
            var newPath = el('path', common); newPath.setAttribute('clip-path', 'url(#' + id + 'n)');
            var head = el('circle', { r: 3.2, cx: 0, cy: h / 2, class: 'ecg-head' });
            svg.appendChild(oldPath); svg.appendChild(newPath); svg.appendChild(head);
            host.appendChild(svg);

            if (reduceMotion) {
                rNew.setAttribute('width', w);
                head.style.display = 'none';
                return;
            }

            var gap = 28;               // hueco entre el trazo nuevo y el anterior (como en un monitor)
            var duration = (w / speed) * 1000;
            var startT = null;
            var firstPass = true;

            function frame(t) {
                if (startT === null) startT = t;
                var p = ((t - startT) % duration) / duration;
                var x = p * w;

                if (mode === 'once' && t - startT >= duration) {
                    rNew.setAttribute('width', w);
                    rOld.setAttribute('width', 0);
                    head.style.display = 'none';
                    return;
                }
                if (firstPass && t - startT >= duration) firstPass = false;

                rNew.setAttribute('width', x);
                if (!firstPass) {
                    rOld.setAttribute('x', Math.min(x + gap, w));
                    rOld.setAttribute('width', Math.max(w - x - gap, 0));
                }
                head.setAttribute('cx', x);
                head.setAttribute('cy', yAt(pts, x));
                raf = requestAnimationFrame(frame);
            }
            raf = requestAnimationFrame(frame);
        }

        render();
        var lastW = host.clientWidth;
        var timer = null;
        window.addEventListener('resize', function () {
            clearTimeout(timer);
            timer = setTimeout(function () {
                if (Math.abs(host.clientWidth - lastW) > 4) { lastW = host.clientWidth; render(); }
            }, 150);
        });
    }

    function init() {
        var hosts = document.querySelectorAll('[data-ecg]');
        for (var i = 0; i < hosts.length; i++) mount(hosts[i]);
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();
