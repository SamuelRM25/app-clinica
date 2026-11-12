/* ClinicApp Farmacia — menú lateral, modo claro/oscuro y ajustes de las páginas existentes */
(function () {
    var body = document.body, root = document.documentElement;

    // Menú en móvil
    function abrir() { body.classList.add('fx-open'); }
    function cerrar() { body.classList.remove('fx-open'); }
    document.addEventListener('click', function (e) {
        if (e.target.closest('[data-fx-open]')) abrir();
        else if (e.target.closest('[data-fx-close]')) cerrar();
    });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') cerrar(); });

    // Modo claro/oscuro: si la página tiene su propio botón (#themeSwitch) lo usamos
    // para que sus gráficas y estados se actualicen igual que antes.
    function etiqueta() {
        var t = document.querySelector('.fx-theme__t');
        if (t) t.textContent = root.getAttribute('data-theme') === 'dark' ? 'Modo claro' : 'Modo oscuro';
    }
    document.querySelectorAll('[data-fx-theme]').forEach(function (b) {
        b.addEventListener('click', function () {
            var propio = document.getElementById('themeSwitch');
            if (propio && !propio.closest('.fx-side')) {
                propio.click();
            } else {
                var next = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
                root.setAttribute('data-theme', next);
                try { localStorage.setItem('dashboard-theme', next); localStorage.setItem('theme', next); } catch (e) {}
            }
            setTimeout(etiqueta, 30);
        });
    });
    etiqueta();

    // En farmacia no hay traslados a encamamiento/quirófano
    document.querySelectorAll('option[value="Traslado"]').forEach(function (o) {
        var s = o.parentElement;
        if (s && s.value === 'Traslado') { s.value = s.options[0] ? s.options[0].value : ''; }
        o.remove();
    });
})();

/* Campos clínicos ocultos (precio hospital/médico, stock hospital/quirófano):
   antes de guardar se llenan para que la validación del formulario no los bloquee.
   Los precios copian el precio de venta; el resto queda en 0. */
(function () {
    function llenar(scope) {
        (scope || document).querySelectorAll('.fx-clinic input[type="number"]').forEach(function (inp) {
            if (inp.value !== '') return;
            var form = inp.form, venta = form && (form.querySelector('[name="precio_venta"]'));
            inp.value = /precio/.test(inp.name || '') && venta && venta.value !== '' ? venta.value : '0';
        });
    }
    document.addEventListener('click', function (e) {
        var b = e.target.closest('button, input[type="submit"]');
        if (b && b.form) llenar(b.form);
        else if (b) llenar(b.closest('.modal, .modal-overlay, form') || document);
    }, true);
    document.addEventListener('submit', function (e) { llenar(e.target); }, true);
})();
