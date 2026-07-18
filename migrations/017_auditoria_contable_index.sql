-- ============================================================
-- MIGRACIÓN 017: Índices optimizados para Auditoría Contable
-- ============================================================
-- Fecha: 2026
-- Propósito: Optimizar consultas sobre audit_log para Reportes > Contabilidad & Ratios
-- ============================================================

-- 1. Índice compuesto para filtros por hospital + módulo + acción + fecha
CREATE INDEX idx_audit_financiero
    ON audit_log (id_hospital, modulo, accion, fecha_audit);

-- 2. Índice para drill-down por tabla + id_registro
CREATE INDEX idx_audit_tabla_financiera
    ON audit_log (tabla_afectada, id_registro);

-- 3. Índice por user_id + fecha para "auditoría por usuario"
CREATE INDEX idx_audit_usuario_fecha
    ON audit_log (user_id, fecha_audit);

-- Vista para consultas rápidas de auditoría financiera
CREATE OR REPLACE VIEW v_auditoria_contable AS
SELECT
    al.id_audit,
    al.id_hospital,
    al.fecha_audit,
    al.user_id,
    al.user_nombre,
    al.user_tipo,
    al.accion,
    al.modulo,
    al.tabla_afectada,
    al.id_registro,
    al.datos_anteriores,
    al.datos_nuevos,
    al.resultado,
    al.ip_address,
    CAST(JSON_UNQUOTE(JSON_EXTRACT(al.datos_nuevos, '$.monto')) AS DECIMAL(12,2)) AS monto,
    CAST(JSON_UNQUOTE(JSON_EXTRACT(al.datos_nuevos, '$.total')) AS DECIMAL(12,2)) AS total,
    CAST(JSON_UNQUOTE(JSON_EXTRACT(al.datos_nuevos, '$.cantidad_consulta')) AS DECIMAL(12,2)) AS cantidad_consulta,
    CAST(JSON_UNQUOTE(JSON_EXTRACT(al.datos_nuevos, '$.unit_cost')) AS DECIMAL(12,2)) AS unit_cost,
    CAST(JSON_UNQUOTE(JSON_EXTRACT(al.datos_nuevos, '$.precio_unitario')) AS DECIMAL(12,2)) AS precio_unitario,
    JSON_UNQUOTE(JSON_EXTRACT(al.datos_nuevos, '$.descripcion')) AS descripcion,
    JSON_UNQUOTE(JSON_EXTRACT(al.datos_nuevos, '$.nombre_cliente')) AS cliente,
    JSON_UNQUOTE(JSON_EXTRACT(al.datos_nuevos, '$.provider_name')) AS proveedor,
    JSON_UNQUOTE(JSON_EXTRACT(al.datos_nuevos, '$.payment_method')) AS metodo_pago,
    JSON_UNQUOTE(JSON_EXTRACT(al.datos_nuevos, '$.tipo_pago')) AS tipo_pago
FROM audit_log al
WHERE al.modulo IN ('billing','dispensary','purchases','gastos','hospitalization','tarifas','surgery','inventory','reports');

-- Vista para KPIs de auditoría
CREATE OR REPLACE VIEW v_audit_kpis AS
SELECT
    id_hospital,
    DATE(fecha_audit) AS fecha,
    modulo,
    accion,
    COUNT(*) AS total_movimientos,
    COUNT(DISTINCT user_id) AS usuarios_unicos,
    SUM(COALESCE(CAST(JSON_UNQUOTE(JSON_EXTRACT(datos_nuevos, '$.monto')) AS DECIMAL(12,2)), 0)) AS monto_total
FROM audit_log
WHERE modulo IN ('billing','dispensary','purchases','gastos','hospitalization','tarifas','surgery','inventory','reports')
GROUP BY id_hospital, DATE(fecha_audit), modulo, accion;

-- ============================================================
-- FIN MIGRACIÓN 017
-- ============================================================
