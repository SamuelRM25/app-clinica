/* ==========================================================================
   assets/js/almacen.js — Utilidades del almacén por área (CMRM)
   AL.api()        llamada al API con token CSRF
   AL.search()     buscador de artículos con lista desplegable
   AL.Receta()     editor de insumos (prueba de laboratorio / plantilla QX)
   ========================================================================== */
(function () {
    'use strict';

    var root = document.currentScript ? document.currentScript.src.replace(/assets\/js\/almacen\.js.*$/, '') : '../../';
    var API = root + 'php/almacen/api.php';

    function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; }
    function qty(n) { n = Math.round(parseFloat(n || 0) * 1000) / 1000; return String(n); }
    function money(n) { n = parseFloat(n || 0); return (n < -0.004 ? '-Q' : 'Q') + Math.abs(n).toLocaleString('es-GT', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
    function csrf() { var m = document.querySelector('meta[name="csrf-token"]'); return m ? m.getAttribute('content') : ''; }

    async function api(action, data, method) {
        method = method || (data ? 'POST' : 'GET');
        var url = API + '?action=' + encodeURIComponent(action);
        var opt = { method: method, credentials: 'same-origin', headers: { 'X-CSRF-Token': csrf() } };
        if (method === 'GET' && data) {
            Object.keys(data).forEach(function (k) { url += '&' + encodeURIComponent(k) + '=' + encodeURIComponent(data[k]); });
        } else if (method === 'POST') {
            opt.headers['Content-Type'] = 'application/json';
            opt.body = JSON.stringify(Object.assign({ action: action }, data || {}));
        }
        try {
            var r = await fetch(url, opt);
            return await r.json();
        } catch (e) {
            return { success: false, message: 'No hubo respuesta del servidor. Revise la conexión.' };
        }
    }

    /**
     * Buscador con lista desplegable.
     * opts: { area, farmacia (bool), onPick(item), minChars }
     */
    function search(input, opts) {
        var wrap = document.createElement('div');
        wrap.className = 'al-search';
        input.parentNode.insertBefore(wrap, input);
        wrap.appendChild(input);
        var list = document.createElement('div');
        list.className = 'al-search__list';
        list.hidden = true;
        wrap.appendChild(list);
        input.setAttribute('autocomplete', 'off');
        var timer, items = [], active = -1;

        function close() { list.hidden = true; active = -1; }
        function render() {
            if (!items.length) {
                list.innerHTML = '<div class="al-search__empty">Sin resultados' + (opts.onCreate ? '. Presione Enter para crearlo.' : '') + '</div>';
            } else {
                list.innerHTML = items.map(function (it, i) {
                    var low = parseFloat(it.stock) <= 0;
                    return '<button type="button" class="al-search__opt' + (i === active ? ' is-active' : '') + '" data-i="' + i + '">' +
                        '<span class="al-search__name">' + esc(it.nombre) +
                        (it.presentacion ? ' <small>' + esc(it.presentacion) + '</small>' : '') + '</span>' +
                        (it.fuente === 'farmacia' ? '<span class="al-tag al-tag--farm">Farmacia</span>' : '') +
                        '<span class="al-search__stock' + (low ? ' is-low' : '') + '">' + qty(it.stock) + ' ' + esc(it.unidad || '') + '</span></button>';
                }).join('');
            }
            list.hidden = false;
        }
        function pick(i) {
            var it = items[i];
            if (!it) return;
            close();
            input.value = '';
            opts.onPick(it);
        }
        input.addEventListener('input', function () {
            clearTimeout(timer);
            var q = input.value.trim();
            if (q.length < (opts.minChars == null ? 1 : opts.minChars)) { close(); return; }
            timer = setTimeout(async function () {
                var j = await api('buscar', { area: opts.area, q: q, farmacia: opts.farmacia ? 1 : 0 }, 'GET');
                items = j.success ? j.items : [];
                active = items.length ? 0 : -1;
                render();
            }, 220);
        });
        input.addEventListener('focus', function () { if (input.value.trim() && items.length) render(); });
        input.addEventListener('keydown', function (e) {
            if (e.key === 'ArrowDown' && items.length) { e.preventDefault(); active = (active + 1) % items.length; render(); }
            else if (e.key === 'ArrowUp' && items.length) { e.preventDefault(); active = (active - 1 + items.length) % items.length; render(); }
            else if (e.key === 'Enter') {
                e.preventDefault();
                if (active >= 0 && !list.hidden) pick(active);
                else if (opts.onCreate && input.value.trim()) { var v = input.value.trim(); input.value = ''; close(); opts.onCreate(v); }
            } else if (e.key === 'Escape') close();
        });
        list.addEventListener('mousedown', function (e) {
            var b = e.target.closest('[data-i]');
            if (b) { e.preventDefault(); pick(+b.getAttribute('data-i')); }
        });
        input.addEventListener('blur', function () { setTimeout(close, 150); });
        return { close: close };
    }

    /**
     * Editor de insumos. el: contenedor vacío.
     * opts: { area, farmacia, items: [{fuente,id|id_item|id_inventario,nombre,unidad,cantidad,stock}] }
     */
    function Receta(el, opts) {
        var rows = (opts.items || []).map(function (r) {
            return { fuente: r.fuente || 'area', id: r.id_item || r.id_inventario || r.id, nombre: r.nombre, unidad: r.unidad, cantidad: r.cantidad, stock: r.stock };
        });
        el.classList.add('al-receta');
        el.innerHTML =
            '<div class="al-receta__add"><i class="bi bi-search"></i><input type="text" class="form-control" placeholder="' +
            (opts.farmacia ? 'Buscar insumo del almacén o medicamento de farmacia…' : 'Buscar insumo del almacén…') + '"></div>' +
            '<div class="al-receta__rows"></div>';
        var input = el.querySelector('input');
        var box = el.querySelector('.al-receta__rows');

        function draw() {
            if (!rows.length) {
                box.innerHTML = '<div class="al-receta__empty">Todavía no hay insumos. Búsquelos arriba y escriba cuánto se usa.</div>';
                return;
            }
            box.innerHTML = rows.map(function (r, i) {
                return '<div class="al-receta__row">' +
                    '<div class="al-receta__name">' + esc(r.nombre) +
                    (r.fuente === 'farmacia' ? ' <span class="al-tag al-tag--farm">Farmacia · stock quirófano</span>' : '') +
                    '<small>Existencia: ' + qty(r.stock) + ' ' + esc(r.unidad || '') + '</small></div>' +
                    '<div class="al-receta__qty"><input type="number" class="form-control" min="0" step="any" value="' + esc(r.cantidad) + '" data-i="' + i + '" aria-label="Cantidad de ' + esc(r.nombre) + '">' +
                    '<span>' + esc(r.unidad || '') + '</span></div>' +
                    '<button type="button" class="btn btn-sm btn-outline-danger" data-del="' + i + '" aria-label="Quitar ' + esc(r.nombre) + '"><i class="bi bi-x-lg"></i></button>' +
                    '</div>';
            }).join('');
        }
        box.addEventListener('input', function (e) { var i = e.target.getAttribute('data-i'); if (i != null) rows[i].cantidad = e.target.value; });
        box.addEventListener('click', function (e) { var b = e.target.closest('[data-del]'); if (b) { rows.splice(+b.getAttribute('data-del'), 1); draw(); } });

        search(input, {
            area: opts.area, farmacia: opts.farmacia,
            onPick: function (it) {
                var ex = rows.find(function (r) { return r.fuente === it.fuente && String(r.id) === String(it.id); });
                if (ex) { draw(); focusRow(rows.indexOf(ex)); return; }
                rows.push({ fuente: it.fuente, id: it.id, nombre: it.nombre, unidad: it.unidad, cantidad: 1, stock: it.stock });
                draw();
                focusRow(rows.length - 1);
            }
        });
        function focusRow(i) { var f = box.querySelector('[data-i="' + i + '"]'); if (f) { f.focus(); f.select(); } }
        draw();

        return {
            items: function () { return rows.filter(function (r) { return parseFloat(r.cantidad) > 0; }).map(function (r) { return { fuente: r.fuente, id: r.id, cantidad: r.cantidad }; }); },
            count: function () { return rows.length; }
        };
    }

    window.AL = { api: api, search: search, Receta: Receta, esc: esc, qty: qty, money: money };
})();
