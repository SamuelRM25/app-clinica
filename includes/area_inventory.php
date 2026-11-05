<?php
/**
 * includes/area_inventory.php
 * Almacén por área: inventario propio de Laboratorio y Quirófano.
 *
 * Tablas (ver area_install_sql()):
 *   area_items          Artículos del almacén de cada área (reactivos, insumos)
 *   area_movimientos    Kardex: cada entrada o salida, con referencia al documento
 *   area_compra_items   Detalle de compras del área (el encabezado vive en purchase_headers.area)
 *   area_cortes(+items) Cortes de inventario (conteo físico)
 *   area_recetas        Insumos por prueba de laboratorio o por plantilla de quirófano
 *   qx_plantillas       Operaciones y procedimientos de quirófano
 *
 * Reglas:
 *   - El stock solo cambia con area_mover(), que siempre deja un movimiento.
 *   - Los descuentos automáticos (órdenes, cirugías) nunca bloquean la atención:
 *     si falta stock, la existencia queda en negativo y se muestra la alerta.
 *   - El uso manual sí exige existencia suficiente.
 */

if (!defined('AREAS_ALMACEN')) {

    define('AREAS_ALMACEN', [
        'laboratorio' => [
            'label'  => 'Laboratorio',
            'role'   => 'lab',
            'module' => 'laboratory',
            'icon'   => 'bi-droplet-half',
            'unidades' => ['unidad', 'prueba', 'ml', 'l', 'g', 'tubo', 'tira', 'caja', 'kit', 'frasco', 'par'],
        ],
        'quirofano' => [
            'label'  => 'Quirófano',
            'role'   => 'qx',
            'module' => 'surgery',
            'icon'   => 'bi-heart-pulse',
            'unidades' => ['unidad', 'par', 'paquete', 'caja', 'rollo', 'sobre', 'ml', 'l', 'g', 'frasco', 'kit'],
        ],
    ]);

    define('AREA_MOV_TIPOS', [
        'inicial'    => ['Existencia inicial', 'in'],
        'compra'     => ['Compra', 'in'],
        'uso'        => ['Uso registrado', 'out'],
        'orden_lab'  => ['Orden de laboratorio', 'out'],
        'cirugia'    => ['Cirugía', 'out'],
        'devolucion' => ['Devolución', 'in'],
        'ajuste'     => ['Ajuste', 'adj'],
        'corte'      => ['Corte de inventario', 'adj'],
        'anulacion'  => ['Compra anulada', 'out'],
    ]);

    class AreaStockException extends Exception {}

    /** Áreas que puede ver el usuario actual (admin: todas las que tengan su módulo activo). */
    function area_allowed_for_user(): array
    {
        $rol = $_SESSION['tipoUsuario'] ?? '';
        $mods = $_SESSION['hospital_modulos'] ?? [];
        $out = [];
        foreach (AREAS_ALMACEN as $key => $a) {
            if (!in_array($a['module'], $mods, true)) continue;
            if ($rol === 'admin' || $rol === $a['role']) $out[] = $key;
        }
        return $out;
    }

    function area_label(string $area): string
    {
        return AREAS_ALMACEN[$area]['label'] ?? ucfirst($area);
    }

    function area_can_manage(): bool
    {
        return ($_SESSION['tipoUsuario'] ?? '') === 'admin';
    }

    /** ¿Ya existen las tablas del almacén? (se consulta una vez por petición) */
    function area_tables_ready($conn): bool
    {
        static $ready = null;
        if ($ready !== null) return $ready;
        try {
            $conn->query("SELECT 1 FROM area_items LIMIT 1");
            $conn->query("SELECT 1 FROM area_recetas LIMIT 1");
            $ready = true;
        } catch (Throwable $e) {
            $ready = false;
        }
        return $ready;
    }

    /** ¿purchase_headers ya tiene la columna area? */
    function purchases_have_area($conn): bool
    {
        static $has = null;
        if ($has !== null) return $has;
        try {
            $has = (bool) $conn->query("SHOW COLUMNS FROM purchase_headers LIKE 'area'")->fetch();
        } catch (Throwable $e) {
            $has = false;
        }
        return $has;
    }

    /**
     * Mueve el stock de un artículo y registra el movimiento.
     *
     * @param float $delta  Positivo entra, negativo sale.
     * @param array $o      ref_tipo, ref_id, documento, nota, costo (para compras),
     *                      permitir_negativo (bool), id_usuario
     * @return array        ['stock_antes', 'stock_despues', 'nombre', 'unidad']
     */
    function area_mover($conn, int $id_hospital, int $id_item, float $delta, string $tipo, array $o = []): array
    {
        $st = $conn->prepare("SELECT id_item, area, nombre, unidad, stock, costo_unitario FROM area_items WHERE id_item = ? AND id_hospital = ? FOR UPDATE");
        $st->execute([$id_item, $id_hospital]);
        $it = $st->fetch(PDO::FETCH_ASSOC);
        if (!$it) throw new AreaStockException('No se encontró el artículo.');

        $antes = (float) $it['stock'];
        $despues = round($antes + $delta, 3);
        if ($delta < 0 && $despues < 0 && empty($o['permitir_negativo'])) {
            throw new AreaStockException('No hay suficiente "' . $it['nombre'] . '". Existencia: ' . area_qty($antes) . ' ' . $it['unidad'] . '.');
        }

        $costo = (float) $it['costo_unitario'];
        if ($tipo === 'compra' && isset($o['costo']) && $delta > 0) {
            // Costo promedio ponderado
            $c = (float) $o['costo'];
            $costo = ($antes > 0) ? (($antes * $costo) + ($delta * $c)) / ($antes + $delta) : $c;
        }

        $conn->prepare("UPDATE area_items SET stock = ?, costo_unitario = ? WHERE id_item = ? AND id_hospital = ?")
            ->execute([$despues, round($costo, 4), $id_item, $id_hospital]);

        $conn->prepare("INSERT INTO area_movimientos
                (id_hospital, area, id_item, tipo, cantidad, stock_antes, stock_despues, costo_unitario, ref_tipo, ref_id, documento, nota, id_usuario)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
            ->execute([
                $id_hospital, $it['area'], $id_item, $tipo, round($delta, 3), $antes, $despues,
                $tipo === 'compra' && isset($o['costo']) ? (float) $o['costo'] : $costo,
                $o['ref_tipo'] ?? null, isset($o['ref_id']) ? (int) $o['ref_id'] : null,
                isset($o['documento']) ? mb_substr((string) $o['documento'], 0, 80) : null,
                isset($o['nota']) ? mb_substr((string) $o['nota'], 0, 255) : null,
                (int) ($o['id_usuario'] ?? ($_SESSION['user_id'] ?? 0)),
            ]);

        return ['stock_antes' => $antes, 'stock_despues' => $despues, 'nombre' => $it['nombre'], 'unidad' => $it['unidad']];
    }

    /** Formato corto de cantidades: 3 / 2.5 / 0.125 */
    function area_qty($n): string
    {
        $n = round((float) $n, 3);
        return rtrim(rtrim(number_format($n, 3, '.', ''), '0'), '.');
    }

    /**
     * Devuelve al almacén todo lo que salió con una referencia (orden, cirugía...).
     * Es idempotente: solo regresa el saldo neto que quede.
     */
    function area_revertir_ref($conn, int $id_hospital, string $ref_tipo, int $ref_id, string $nota, ?int $id_item = null, ?float $cantidad = null): array
    {
        $sql = "SELECT id_item, SUM(cantidad) AS neto, MAX(documento) AS documento FROM area_movimientos
                WHERE id_hospital = ? AND ref_tipo = ? AND ref_id = ?";
        $p = [$id_hospital, $ref_tipo, $ref_id];
        if ($id_item) { $sql .= " AND id_item = ?"; $p[] = $id_item; }
        $sql .= " GROUP BY id_item HAVING neto < 0";
        $st = $conn->prepare($sql);
        $st->execute($p);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $regresa = -(float) $r['neto'];
            if ($cantidad !== null) $regresa = min($regresa, $cantidad);
            if ($regresa <= 0) continue;
            $out[] = area_mover($conn, $id_hospital, (int) $r['id_item'], $regresa, 'devolucion', [
                'ref_tipo' => $ref_tipo, 'ref_id' => $ref_id, 'documento' => $r['documento'], 'nota' => $nota,
            ]) + ['cantidad' => $regresa];
        }
        return $out;
    }

    /** Insumos de una receta (prueba o plantilla). */
    function area_receta($conn, int $id_hospital, string $ref_tipo, int $ref_id): array
    {
        $st = $conn->prepare("
            SELECT r.id, r.fuente, r.id_item, r.id_inventario, r.cantidad,
                   COALESCE(ai.nombre, i.nom_medicamento) AS nombre,
                   COALESCE(ai.unidad, i.presentacion_med, 'unidad') AS unidad,
                   CASE WHEN r.fuente = 'area' THEN ai.stock ELSE i.stock_quirofano END AS stock,
                   COALESCE(ai.activo, 1) AS activo
            FROM area_recetas r
            LEFT JOIN area_items ai ON r.fuente = 'area' AND ai.id_item = r.id_item AND ai.id_hospital = r.id_hospital
            LEFT JOIN inventario i ON r.fuente = 'farmacia' AND i.id_inventario = r.id_inventario AND i.id_hospital = r.id_hospital
            WHERE r.id_hospital = ? AND r.ref_tipo = ? AND r.ref_id = ?
            ORDER BY r.fuente, nombre");
        $st->execute([$id_hospital, $ref_tipo, $ref_id]);
        return array_values(array_filter($st->fetchAll(PDO::FETCH_ASSOC), fn($r) => $r['nombre'] !== null));
    }

    /**
     * Descuenta del almacén de Laboratorio los insumos de cada prueba de una orden.
     * Se llama justo después de insertar orden_pruebas. Nunca lanza excepciones:
     * devuelve avisos (p. ej. existencias en negativo) para mostrarlos.
     */
    function area_consumir_orden_lab($conn, int $id_hospital, int $id_orden): array
    {
        $avisos = [];
        try {
            if (!area_tables_ready($conn)) return [];
            $st = $conn->prepare("
                SELECT op.id_orden_prueba, op.id_prueba, cp.nombre_prueba, ol.numero_orden
                FROM orden_pruebas op
                JOIN ordenes_laboratorio ol ON ol.id_orden = op.id_orden
                LEFT JOIN catalogo_pruebas cp ON cp.id_prueba = op.id_prueba
                WHERE op.id_orden = ? AND ol.id_hospital = ?");
            $st->execute([$id_orden, $id_hospital]);
            $ya = $conn->prepare("SELECT COUNT(*) FROM area_movimientos WHERE id_hospital = ? AND ref_tipo = 'orden_prueba' AND ref_id = ?");

            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $op) {
                $ya->execute([$id_hospital, $op['id_orden_prueba']]);
                if ((int) $ya->fetchColumn() > 0) continue; // ya descontado

                foreach (area_receta($conn, $id_hospital, 'prueba', (int) $op['id_prueba']) as $r) {
                    if ($r['fuente'] !== 'area' || !$r['activo']) continue;
                    $res = area_mover($conn, $id_hospital, (int) $r['id_item'], -(float) $r['cantidad'], 'orden_lab', [
                        'ref_tipo' => 'orden_prueba', 'ref_id' => (int) $op['id_orden_prueba'],
                        'documento' => $op['numero_orden'], 'nota' => $op['nombre_prueba'],
                        'permitir_negativo' => true,
                    ]);
                    if ($res['stock_despues'] < 0) {
                        $avisos[] = $res['nombre'] . ' quedó en ' . area_qty($res['stock_despues']) . ' ' . $res['unidad'];
                    }
                }
            }
        } catch (Throwable $e) {
            error_log('area_consumir_orden_lab: ' . $e->getMessage());
        }
        return $avisos;
    }

    /** Regresa los insumos de pruebas devueltas que todavía no se habían procesado. */
    function area_devolver_pruebas_lab($conn, int $id_hospital, array $ids_orden_prueba, string $motivo): void
    {
        try {
            if (!area_tables_ready($conn)) return;
            foreach ($ids_orden_prueba as $id) {
                area_revertir_ref($conn, $id_hospital, 'orden_prueba', (int) $id, 'Prueba devuelta: ' . $motivo);
            }
        } catch (Throwable $e) {
            error_log('area_devolver_pruebas_lab: ' . $e->getMessage());
        }
    }

    /**
     * Aplica una plantilla de quirófano a una cirugía recién creada:
     *  - insumos del almacén de Quirófano → area_movimientos (ref cirugia)
     *  - medicamentos del stock quirófano de farmacia → cirugia_consumos (se cobran como hoy)
     */
    function area_aplicar_plantilla_cirugia($conn, int $id_hospital, int $id_cirugia, int $id_plantilla, string $numero_cirugia): array
    {
        $avisos = [];
        if (!area_tables_ready($conn)) return $avisos;

        foreach (area_receta($conn, $id_hospital, 'plantilla', $id_plantilla) as $r) {
            $cant = (float) $r['cantidad'];
            if ($r['fuente'] === 'area') {
                if (!$r['activo']) continue;
                $res = area_mover($conn, $id_hospital, (int) $r['id_item'], -$cant, 'cirugia', [
                    'ref_tipo' => 'cirugia', 'ref_id' => $id_cirugia, 'documento' => $numero_cirugia,
                    'nota' => 'Plantilla de la cirugía', 'permitir_negativo' => true,
                ]);
                if ($res['stock_despues'] < 0) {
                    $avisos[] = $res['nombre'] . ': el almacén quedó en ' . area_qty($res['stock_despues']) . ' ' . $res['unidad'];
                }
                continue;
            }

            // Medicamento de farmacia (stock quirófano)
            $st = $conn->prepare("SELECT nom_medicamento, stock_quirofano, precio_hospital, precio_venta, precio_quirofano,
                                         COALESCE(NULLIF(precio_compra, 0), 0) AS precio_compra
                                  FROM inventario WHERE id_inventario = ? AND id_hospital = ? FOR UPDATE");
            $st->execute([(int) $r['id_inventario'], $id_hospital]);
            $inv = $st->fetch(PDO::FETCH_ASSOC);
            if (!$inv) continue;
            if ((float) $inv['stock_quirofano'] < $cant) {
                $avisos[] = $inv['nom_medicamento'] . ': no se descontó, en stock quirófano hay ' . area_qty($inv['stock_quirofano']) . ' y la plantilla pide ' . area_qty($cant);
                continue;
            }
            $precio = (float) ($inv['precio_quirofano'] ?: ($inv['precio_hospital'] ?: $inv['precio_venta']));
            $conn->prepare("UPDATE inventario SET stock_quirofano = stock_quirofano - ? WHERE id_inventario = ? AND id_hospital = ?")
                ->execute([$cant, (int) $r['id_inventario'], $id_hospital]);
            $conn->prepare("INSERT INTO cirugia_consumos (id_cirugia, id_inventario, cantidad, precio_unitario, precio_costo, subtotal, id_hospital) VALUES (?, ?, ?, ?, ?, ?, ?)")
                ->execute([$id_cirugia, (int) $r['id_inventario'], $cant, $precio, (float) $inv['precio_compra'], $precio * $cant, $id_hospital]);
        }
        return $avisos;
    }

    /** Lo que una cirugía tiene descontado del almacén de Quirófano (saldo neto por artículo). */
    function area_insumos_cirugia($conn, int $id_hospital, int $id_cirugia): array
    {
        if (!area_tables_ready($conn)) return [];
        $st = $conn->prepare("
            SELECT m.id_item, ai.nombre, ai.unidad, ai.costo_unitario, -SUM(m.cantidad) AS cantidad
            FROM area_movimientos m JOIN area_items ai ON ai.id_item = m.id_item
            WHERE m.id_hospital = ? AND m.ref_tipo = 'cirugia' AND m.ref_id = ?
            GROUP BY m.id_item HAVING cantidad > 0 ORDER BY ai.nombre");
        $st->execute([$id_hospital, $id_cirugia]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** SQL de instalación (MySQL y MariaDB). Se usa en el instalador y en el script .sql */
    function area_install_sql(): array
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
            "CREATE TABLE IF NOT EXISTS area_items (
                id_item INT AUTO_INCREMENT PRIMARY KEY,
                id_hospital INT NOT NULL,
                area VARCHAR(20) NOT NULL,
                codigo VARCHAR(50) NULL,
                nombre VARCHAR(150) NOT NULL,
                categoria VARCHAR(80) NULL,
                unidad VARCHAR(30) NOT NULL DEFAULT 'unidad',
                presentacion VARCHAR(120) NULL,
                stock DECIMAL(12,3) NOT NULL DEFAULT 0,
                stock_minimo DECIMAL(12,3) NOT NULL DEFAULT 0,
                costo_unitario DECIMAL(12,4) NOT NULL DEFAULT 0,
                fecha_vencimiento DATE NULL,
                lote VARCHAR(60) NULL,
                ubicacion VARCHAR(80) NULL,
                activo TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY idx_area (id_hospital, area, activo),
                KEY idx_nombre (nombre)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            "CREATE TABLE IF NOT EXISTS area_movimientos (
                id_mov INT AUTO_INCREMENT PRIMARY KEY,
                id_hospital INT NOT NULL,
                area VARCHAR(20) NOT NULL,
                id_item INT NOT NULL,
                tipo VARCHAR(20) NOT NULL,
                cantidad DECIMAL(12,3) NOT NULL,
                stock_antes DECIMAL(12,3) NOT NULL DEFAULT 0,
                stock_despues DECIMAL(12,3) NOT NULL DEFAULT 0,
                costo_unitario DECIMAL(12,4) NOT NULL DEFAULT 0,
                ref_tipo VARCHAR(30) NULL,
                ref_id INT NULL,
                documento VARCHAR(80) NULL,
                nota VARCHAR(255) NULL,
                id_usuario INT NULL,
                fecha DATETIME DEFAULT CURRENT_TIMESTAMP,
                KEY idx_area_fecha (id_hospital, area, fecha),
                KEY idx_item (id_item),
                KEY idx_ref (ref_tipo, ref_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            "CREATE TABLE IF NOT EXISTS area_compra_items (
                id INT AUTO_INCREMENT PRIMARY KEY,
                id_hospital INT NOT NULL,
                purchase_header_id INT NOT NULL,
                id_item INT NOT NULL,
                cantidad DECIMAL(12,3) NOT NULL,
                costo_unitario DECIMAL(12,4) NOT NULL DEFAULT 0,
                subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
                fecha_vencimiento DATE NULL,
                lote VARCHAR(60) NULL,
                KEY idx_header (purchase_header_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            "CREATE TABLE IF NOT EXISTS area_cortes (
                id_corte INT AUTO_INCREMENT PRIMARY KEY,
                id_hospital INT NOT NULL,
                area VARCHAR(20) NOT NULL,
                fecha DATETIME DEFAULT CURRENT_TIMESTAMP,
                id_usuario INT NULL,
                items_contados INT NOT NULL DEFAULT 0,
                items_diferencia INT NOT NULL DEFAULT 0,
                valor_sistema DECIMAL(14,2) NOT NULL DEFAULT 0,
                valor_fisico DECIMAL(14,2) NOT NULL DEFAULT 0,
                valor_diferencia DECIMAL(14,2) NOT NULL DEFAULT 0,
                notas VARCHAR(500) NULL,
                KEY idx_area (id_hospital, area, fecha)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            "CREATE TABLE IF NOT EXISTS area_corte_items (
                id INT AUTO_INCREMENT PRIMARY KEY,
                id_corte INT NOT NULL,
                id_item INT NOT NULL,
                nombre VARCHAR(150) NOT NULL,
                unidad VARCHAR(30) NULL,
                stock_sistema DECIMAL(12,3) NOT NULL,
                stock_fisico DECIMAL(12,3) NOT NULL,
                diferencia DECIMAL(12,3) NOT NULL,
                costo_unitario DECIMAL(12,4) NOT NULL DEFAULT 0,
                KEY idx_corte (id_corte)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            "CREATE TABLE IF NOT EXISTS area_recetas (
                id INT AUTO_INCREMENT PRIMARY KEY,
                id_hospital INT NOT NULL,
                ref_tipo VARCHAR(20) NOT NULL,
                ref_id INT NOT NULL,
                fuente VARCHAR(10) NOT NULL DEFAULT 'area',
                id_item INT NULL,
                id_inventario INT NULL,
                cantidad DECIMAL(12,3) NOT NULL,
                KEY idx_ref (id_hospital, ref_tipo, ref_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            "CREATE TABLE IF NOT EXISTS qx_plantillas (
                id_plantilla INT AUTO_INCREMENT PRIMARY KEY,
                id_hospital INT NOT NULL,
                nombre VARCHAR(150) NOT NULL,
                tipo VARCHAR(20) NOT NULL DEFAULT 'Cirugía',
                descripcion VARCHAR(500) NULL,
                precio DECIMAL(12,2) NOT NULL DEFAULT 0,
                activo TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                KEY idx_h (id_hospital, activo)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        ],
            $add_col('purchase_headers', 'area', "VARCHAR(20) NOT NULL DEFAULT 'farmacia'"),
            $add_col('cirugias', 'id_plantilla', 'INT NULL')
        );
    }
}
