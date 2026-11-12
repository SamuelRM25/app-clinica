<?php
/**
 * includes/lab_resultados.php
 * Plantillas de resultados de laboratorio (editables) y resultados por paciente.
 *
 * Una plantilla describe la hoja de resultados de una prueba:
 * {
 *   "hoja": "BIOQUÍMICA",          Pruebas con la misma hoja se imprimen juntas en una página
 *   "columnas": 1,                 Cuántas columnas usan las secciones al imprimir (1-3)
 *   "ocultar_vacios": true,        Al imprimir, omite filas sin resultado (hojas compartidas)
 *   "secciones": [
 *     {"tipo": "tabla", "titulo": "", "filas": [
 *         {"id": "f1", "nombre": "Glucosa", "unidad": "mg/dl", "ref": "65-110 mg/dl",
 *          "opciones": ["NEGATIVO", "POSITIVO"], "min": 65, "max": 110}]},
 *     {"tipo": "matriz", "titulo": "PARÁSITOS", "columnas": ["Huevos", "Larvas"],
 *      "filas": [{"id": "m1", "nombre": "Ascaris"}], "opciones": ["+", "++"]},
 *     {"tipo": "referencia", "titulo": "...", "columnas": ["Semanas", "Rango"], "filas": [["3", "5 - 50"]]},
 *     {"tipo": "nota", "texto": "..."}
 *   ]
 * }
 * Los valores de un resultado se guardan como {"f1": "98", "m1|0": "+"}.
 * Cada resultado guarda una copia de la plantilla: editar la plantilla no cambia resultados viejos.
 */

if (!function_exists('lab_install_sql')) {

    if (!defined('LAB_TITULO_IMPRESION')) define('LAB_TITULO_IMPRESION', 'LABORATORIO CLÍNICO MORALES');


    function lab_install_sql(): array
    {
        $add_col = function (string $table, string $col, string $def): array {
            return [
                "SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$table' AND COLUMN_NAME = '$col')",
                "SET @s := IF(@c = 0, 'ALTER TABLE $table ADD COLUMN $col " . str_replace("'", "''", $def) . "', 'SELECT 1')",
                "PREPARE st FROM @s",
                "EXECUTE st",
                "DEALLOCATE PREPARE st",
            ];
        };
        return array_merge([
            "CREATE TABLE IF NOT EXISTS lab_plantillas (
                id_plantilla INT AUTO_INCREMENT PRIMARY KEY,
                id_hospital INT NOT NULL,
                nombre VARCHAR(150) NOT NULL,
                hoja VARCHAR(120) NOT NULL DEFAULT '',
                diseno LONGTEXT NOT NULL,
                activo TINYINT(1) NOT NULL DEFAULT 1,
                updated_by INT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY idx_h (id_hospital, activo)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            "CREATE TABLE IF NOT EXISTS lab_resultados (
                id_resultado INT AUTO_INCREMENT PRIMARY KEY,
                id_hospital INT NOT NULL,
                id_orden INT NOT NULL,
                id_orden_prueba INT NOT NULL,
                id_plantilla INT NULL,
                plantilla LONGTEXT NOT NULL,
                valores LONGTEXT NOT NULL,
                observaciones TEXT NULL,
                guardado_por INT NULL,
                fecha_guardado DATETIME DEFAULT CURRENT_TIMESTAMP,
                finalizado TINYINT(1) NOT NULL DEFAULT 0,
                finalizado_por INT NULL,
                fecha_finalizado DATETIME NULL,
                UNIQUE KEY uq_op (id_orden_prueba),
                KEY idx_orden (id_hospital, id_orden)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        ], $add_col('catalogo_pruebas', 'id_plantilla', 'INT NULL'));
    }

    function lab_tables_ready($conn): bool
    {
        static $ok = null;
        if ($ok !== null) return $ok;
        try {
            $conn->query("SELECT 1 FROM lab_plantillas LIMIT 1");
            $conn->query("SELECT 1 FROM lab_resultados LIMIT 1");
            $ok = (bool) $conn->query("SHOW COLUMNS FROM catalogo_pruebas LIKE 'id_plantilla'")->fetch();
        } catch (Throwable $e) {
            $ok = false;
        }
        return $ok;
    }

    function lab_e($v): string { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }

    /** Deja una plantilla con la forma esperada (y limpia datos raros del editor). */
    function lab_tpl_normalize($tpl): array
    {
        if (is_string($tpl)) $tpl = json_decode($tpl, true);
        if (!is_array($tpl)) $tpl = [];
        $out = [
            'hoja' => mb_substr(trim((string) ($tpl['hoja'] ?? '')), 0, 120),
            'columnas' => max(1, min(3, (int) ($tpl['columnas'] ?? 1))),
            'ocultar_vacios' => !empty($tpl['ocultar_vacios']),
            'secciones' => [],
        ];
        $n = 0;
        foreach (($tpl['secciones'] ?? []) as $s) {
            $tipo = in_array($s['tipo'] ?? '', ['tabla', 'matriz', 'referencia', 'nota'], true) ? $s['tipo'] : 'tabla';
            $sec = ['tipo' => $tipo, 'titulo' => mb_substr(trim((string) ($s['titulo'] ?? '')), 0, 120), 'completa' => !empty($s['completa'])];
            if ($tipo === 'nota') {
                $sec['texto'] = mb_substr(trim((string) ($s['texto'] ?? '')), 0, 2000);
            } elseif ($tipo === 'referencia') {
                $sec['columnas'] = array_values(array_map(fn($c) => mb_substr(trim((string) $c), 0, 80), (array) ($s['columnas'] ?? [])));
                $sec['filas'] = [];
                foreach ((array) ($s['filas'] ?? []) as $f) {
                    $sec['filas'][] = array_values(array_map(fn($c) => mb_substr(trim((string) $c), 0, 120), (array) $f));
                }
            } else {
                if ($tipo === 'matriz') {
                    $sec['columnas'] = array_values(array_filter(array_map(fn($c) => mb_substr(trim((string) $c), 0, 40), (array) ($s['columnas'] ?? [])), 'strlen'));
                }
                $sec['opciones'] = array_values(array_filter(array_map(fn($c) => mb_substr(trim((string) $c), 0, 60), (array) ($s['opciones'] ?? [])), 'strlen'));
                $sec['filas'] = [];
                foreach ((array) ($s['filas'] ?? []) as $f) {
                    $nombre = mb_substr(trim((string) ($f['nombre'] ?? '')), 0, 150);
                    if ($nombre === '') continue;
                    $id = preg_replace('/[^A-Za-z0-9_]/', '', (string) ($f['id'] ?? ''));
                    if ($id === '') $id = 'f' . (++$n) . substr(md5($nombre . $n), 0, 4);
                    $fila = ['id' => $id, 'nombre' => $nombre];
                    if ($tipo === 'tabla') {
                        $fila['unidad'] = mb_substr(trim((string) ($f['unidad'] ?? '')), 0, 40);
                        $fila['ref'] = mb_substr(trim((string) ($f['ref'] ?? '')), 0, 300);
                        $fila['opciones'] = array_values(array_filter(array_map(fn($c) => mb_substr(trim((string) $c), 0, 60), (array) ($f['opciones'] ?? [])), 'strlen'));
                        foreach (['min', 'max'] as $k) {
                            $v = $f[$k] ?? '';
                            $fila[$k] = ($v === '' || $v === null || !is_numeric(str_replace(',', '.', (string) $v))) ? null : (float) str_replace(',', '.', (string) $v);
                        }
                    }
                    $sec['filas'][] = $fila;
                }
            }
            $out['secciones'][] = $sec;
        }
        // Ids únicos
        $vistos = [];
        foreach ($out['secciones'] as &$s) {
            if (empty($s['filas']) || in_array($s['tipo'], ['referencia', 'nota'], true)) continue;
            foreach ($s['filas'] as &$f) {
                while (isset($vistos[$f['id']])) $f['id'] .= 'x';
                $vistos[$f['id']] = true;
            }
            unset($f);
        }
        unset($s);
        return $out;
    }

    /** ¿El valor está fuera del rango de la fila? Devuelve 'alto', 'bajo' o ''. */
    function lab_flag(array $fila, $valor): string
    {
        $v = str_replace(',', '.', trim((string) $valor));
        if ($v === '' || !is_numeric($v)) return '';
        $v = (float) $v;
        if (isset($fila['min']) && $fila['min'] !== null && $v < $fila['min']) return 'bajo';
        if (isset($fila['max']) && $fila['max'] !== null && $v > $fila['max']) return 'alto';
        return '';
    }

    /**
     * Formulario de captura de una plantilla. $name es el prefijo de los inputs, p. ej. "r[15]".
     */
    function lab_render_form(array $tpl, array $valores, string $name, bool $readonly = false): string
    {
        $h = '<div class="lr-form lr-cols-' . (int) $tpl['columnas'] . '">';
        foreach ($tpl['secciones'] as $si => $s) {
            $h .= '<section class="lr-sec lr-sec--' . $s['tipo'] . ($s['completa'] || $s['tipo'] === 'matriz' ? ' lr-sec--full' : '') . '">';
            if ($s['titulo'] !== '') $h .= '<h4 class="lr-sec__t">' . lab_e($s['titulo']) . '</h4>';
            if ($s['tipo'] === 'nota') {
                $h .= '<p class="lr-note">' . nl2br(lab_e($s['texto'])) . '</p>';
            } elseif ($s['tipo'] === 'referencia') {
                $h .= lab_render_ref_table($s, 'lr-ref');
            } elseif ($s['tipo'] === 'tabla') {
                $hasUnit = (bool) array_filter($s['filas'], fn($f) => $f['unidad'] !== '');
                $hasRef = (bool) array_filter($s['filas'], fn($f) => $f['ref'] !== '');
                $h .= '<table class="lr-t"><thead><tr><th>Examen</th><th>Resultado</th>' . ($hasUnit ? '<th>Unidad</th>' : '') . ($hasRef ? '<th>Valor normal</th>' : '') . '</tr></thead><tbody>';
                foreach ($s['filas'] as $f) {
                    $val = (string) ($valores[$f['id']] ?? '');
                    $flag = lab_flag($f, $val);
                    $opts = $f['opciones'] ?: ($s['opciones'] ?? []);
                    $listId = $opts ? 'dl_' . md5($name . $f['id']) : '';
                    $h .= '<tr><td class="lr-name">' . lab_e($f['nombre']) . '</td><td class="lr-val">';
                    $h .= '<input type="text" class="form-control form-control-sm lr-in' . ($flag ? ' is-' . $flag : '') . '" name="' . lab_e($name . '[' . $f['id'] . ']') . '" value="' . lab_e($val) . '"'
                        . ($f['min'] !== null ? ' data-min="' . $f['min'] . '"' : '') . ($f['max'] !== null ? ' data-max="' . $f['max'] . '"' : '')
                        . ($listId ? ' list="' . $listId . '"' : '') . ' aria-label="' . lab_e($f['nombre']) . '"' . ($readonly ? ' readonly' : '') . ' autocomplete="off">';
                    if ($opts && !$readonly) {
                        $h .= '<datalist id="' . $listId . '">' . implode('', array_map(fn($o) => '<option value="' . lab_e($o) . '">', $opts)) . '</datalist>';
                        if (count($opts) <= 3) {
                            $h .= '<div class="lr-quick">' . implode('', array_map(fn($o) => '<button type="button" data-v="' . lab_e($o) . '">' . lab_e($o) . '</button>', $opts)) . '</div>';
                        }
                    }
                    $h .= '</td>' . ($hasUnit ? '<td class="lr-unit">' . lab_e($f['unidad']) . '</td>' : '') . ($hasRef ? '<td class="lr-ref-c">' . nl2br(lab_e($f['ref'])) . '</td>' : '') . '</tr>';
                }
                $h .= '</tbody></table>';
            } else { // matriz
                $h .= '<div class="lr-scroll"><table class="lr-t lr-t--m"><thead><tr><th></th>';
                foreach ($s['columnas'] as $c) $h .= '<th>' . lab_e($c) . '</th>';
                $h .= '</tr></thead><tbody>';
                $listId = $s['opciones'] ? 'dl_' . md5($name . $si) : '';
                foreach ($s['filas'] as $f) {
                    $h .= '<tr><td class="lr-name">' . lab_e($f['nombre']) . '</td>';
                    foreach ($s['columnas'] as $ci => $c) {
                        $k = $f['id'] . '|' . $ci;
                        $h .= '<td><input type="text" class="form-control form-control-sm lr-in lr-in--m" name="' . lab_e($name . '[' . $k . ']') . '" value="' . lab_e($valores[$k] ?? '') . '"'
                            . ($listId ? ' list="' . $listId . '"' : '') . ' aria-label="' . lab_e($f['nombre'] . ' ' . $c) . '"' . ($readonly ? ' readonly' : '') . ' autocomplete="off"></td>';
                    }
                    $h .= '</tr>';
                }
                $h .= '</tbody></table></div>';
                if ($listId) $h .= '<datalist id="' . $listId . '">' . implode('', array_map(fn($o) => '<option value="' . lab_e($o) . '">', $s['opciones'])) . '</datalist>';
            }
            $h .= '</section>';
        }
        return $h . '</div>';
    }

    function lab_render_ref_table(array $s, string $cls): string
    {
        $h = '<table class="' . $cls . '">';
        if ($s['columnas']) $h .= '<thead><tr>' . implode('', array_map(fn($c) => '<th>' . lab_e($c) . '</th>', $s['columnas'])) . '</tr></thead>';
        $h .= '<tbody>';
        foreach ($s['filas'] as $f) $h .= '<tr>' . implode('', array_map(fn($c) => '<td>' . lab_e($c) . '</td>', $f)) . '</tr>';
        return $h . '</tbody></table>';
    }

    /**
     * Impresión de una hoja. $items: [['tpl' => array, 'valores' => array, 'nombre' => string, 'obs' => string], ...]
     * Las tablas simples (sin título, una sola sección) se unen en una sola tabla, como la hoja de Bioquímica.
     */
    function lab_render_print(array $items): string
    {
        $h = '';
        $merge = [];
        $flush = function () use (&$merge, &$h) {
            if (!$merge) return;
            $hasUnit = (bool) array_filter($merge, fn($r) => $r['f']['unidad'] !== '');
            $hasRef = (bool) array_filter($merge, fn($r) => $r['f']['ref'] !== '');
            $partes = count($merge) > 12 ? array_chunk($merge, (int) ceil(count($merge) / 2)) : [$merge];
            $h .= '<div class="lp-grid lp-cols-' . count($partes) . '">';
            foreach ($partes as $p) {
                $h .= '<table class="lp-t"><thead><tr><th>Examen solicitado</th><th>Resultado</th>' . ($hasUnit ? '<th>Unidad</th>' : '') . ($hasRef ? '<th>Valor normal</th>' : '') . '</tr></thead><tbody>';
                foreach ($p as $r) $h .= lab_print_row($r['f'], $r['v'], $hasUnit, $hasRef);
                $h .= '</tbody></table>';
            }
            $h .= '</div>';
            $merge = [];
        };

        foreach ($items as $it) {
            $tpl = $it['tpl'];
            $vals = $it['valores'];
            $simple = count($tpl['secciones']) === 1 && $tpl['secciones'][0]['tipo'] === 'tabla' && $tpl['secciones'][0]['titulo'] === '';
            if ($simple) {
                $filas = $tpl['secciones'][0]['filas'];
                $con = array_filter($filas, fn($f) => trim((string) ($vals[$f['id']] ?? '')) !== '');
                if ($tpl['ocultar_vacios'] && $con) $filas = $con;
                foreach ($filas as $f) $merge[] = ['f' => $f, 'v' => $vals[$f['id']] ?? ''];
                if (!empty($it['obs'])) { $flush(); $h .= '<p class="lp-obs"><b>Observaciones (' . lab_e($it['nombre']) . '):</b> ' . nl2br(lab_e($it['obs'])) . '</p>'; }
                continue;
            }
            $flush();
            $h .= '<div class="lp-grid lp-cols-' . (int) $tpl['columnas'] . '">';
            foreach ($tpl['secciones'] as $s) {
                $h .= '<section class="lp-sec' . ($s['completa'] || $s['tipo'] === 'matriz' ? ' lp-sec--full' : '') . '">';
                if ($s['titulo'] !== '') $h .= '<h3 class="lp-sec__t">' . lab_e($s['titulo']) . '</h3>';
                if ($s['tipo'] === 'nota') {
                    $h .= '<p class="lp-note">' . nl2br(lab_e($s['texto'])) . '</p>';
                } elseif ($s['tipo'] === 'referencia') {
                    $h .= lab_render_ref_table($s, 'lp-t lp-t--ref');
                } elseif ($s['tipo'] === 'tabla') {
                    $filas = $s['filas'];
                    if ($tpl['ocultar_vacios']) {
                        $con = array_filter($filas, fn($f) => trim((string) ($vals[$f['id']] ?? '')) !== '');
                        if ($con) $filas = $con;
                    }
                    $hasUnit = (bool) array_filter($filas, fn($f) => $f['unidad'] !== '');
                    $hasRef = (bool) array_filter($filas, fn($f) => $f['ref'] !== '');
                    $multi = count($tpl['secciones']) > 1 && $tpl['columnas'] > 1;
                    $h .= '<table class="lp-t">' . ($multi ? '' : '<thead><tr><th>Examen solicitado</th><th>Resultado</th>' . ($hasUnit ? '<th>Unidad</th>' : '') . ($hasRef ? '<th>Valor normal</th>' : '') . '</tr></thead>') . '<tbody>';
                    foreach ($filas as $f) $h .= lab_print_row($f, $vals[$f['id']] ?? '', $hasUnit, $hasRef);
                    $h .= '</tbody></table>';
                } else {
                    $h .= '<table class="lp-t lp-t--m"><thead><tr><th></th>' . implode('', array_map(fn($c) => '<th>' . lab_e($c) . '</th>', $s['columnas'])) . '</tr></thead><tbody>';
                    foreach ($s['filas'] as $f) {
                        $h .= '<tr><td>' . lab_e($f['nombre']) . '</td>';
                        foreach ($s['columnas'] as $ci => $c) $h .= '<td class="lp-c">' . lab_e($vals[$f['id'] . '|' . $ci] ?? '') . '</td>';
                        $h .= '</tr>';
                    }
                    $h .= '</tbody></table>';
                }
                $h .= '</section>';
            }
            $h .= '</div>';
            if (!empty($it['obs'])) $h .= '<p class="lp-obs"><b>Observaciones:</b> ' . nl2br(lab_e($it['obs'])) . '</p>';
        }
        $flush();
        return $h;
    }

    function lab_print_row(array $f, $v, bool $hasUnit, bool $hasRef): string
    {
        $flag = lab_flag($f, $v);
        $mark = $flag === 'alto' ? ' ↑' : ($flag === 'bajo' ? ' ↓' : '');
        return '<tr><td>' . lab_e($f['nombre']) . '</td><td class="lp-v' . ($flag ? ' lp-flag' : '') . '">' . lab_e($v) . $mark . '</td>'
            . ($hasUnit ? '<td class="lp-u">' . lab_e($f['unidad']) . '</td>' : '')
            . ($hasRef ? '<td class="lp-r">' . nl2br(lab_e($f['ref'])) . '</td>' : '') . '</tr>';
    }

    /** ¿Tiene la plantilla al menos un valor capturado? */
    function lab_tiene_valores(array $valores): bool
    {
        foreach ($valores as $v) if (trim((string) $v) !== '') return true;
        return false;
    }

    /**
     * Órdenes "Externo": el paciente paga a otro laboratorio. Se crea la orden y el ticket,
     * pero no se guarda cobro. Columnas: ordenes_laboratorio.es_externo y externo_detalle (JSON del ticket).
     * $crear = true agrega las columnas si faltan (la primera vez que se usa).
     */
    function lab_externo_cols($conn, bool $crear = false): bool
    {
        static $ok = null;
        if ($ok === true) return true;
        try {
            $ok = (bool) $conn->query("SHOW COLUMNS FROM ordenes_laboratorio LIKE 'externo_detalle'")->fetch();
            if (!$ok && $crear) {
                $tiene = (bool) $conn->query("SHOW COLUMNS FROM ordenes_laboratorio LIKE 'es_externo'")->fetch();
                if (!$tiene) $conn->exec("ALTER TABLE ordenes_laboratorio ADD COLUMN es_externo TINYINT(1) NOT NULL DEFAULT 0");
                $conn->exec("ALTER TABLE ordenes_laboratorio ADD COLUMN externo_detalle TEXT NULL");
                $ok = true;
            }
        } catch (Throwable $e) {
            error_log('lab_externo_cols: ' . $e->getMessage());
            $ok = false;
        }
        return $ok;
    }

    /** Guarda en la orden lo que lleva el ticket de una orden Externo. */
    function lab_externo_marcar($conn, int $id_orden, int $id_hospital, array $items, string $tipo_pago): void
    {
        $total = 0;
        foreach ($items as $it) $total += (float) $it['precio'];
        $detalle = json_encode(['items' => array_map(fn($it) => ['nombre' => $it['nombre'], 'precio' => round((float) $it['precio'], 2)], $items),
            'total' => round($total, 2), 'tipo_pago' => $tipo_pago, 'usuario' => $_SESSION['nombre'] ?? ''], JSON_UNESCAPED_UNICODE);
        $conn->prepare("UPDATE ordenes_laboratorio SET es_externo = 1, externo_detalle = ? WHERE id_orden = ? AND id_hospital = ?")
            ->execute([$detalle, $id_orden, $id_hospital]);
    }

    /** Edad legible para impresión. */
    function lab_edad(?string $fnac): string
    {
        if (!$fnac || $fnac < '1901-01-01') return '';
        $d = date_diff(date_create($fnac), date_create('today'));
        if ($d->y >= 1) return $d->y . ' año' . ($d->y === 1 ? '' : 's');
        if ($d->m >= 1) return $d->m . ' mes' . ($d->m === 1 ? '' : 'es');
        return $d->d . ' día' . ($d->d === 1 ? '' : 's');
    }
}
