<?php
// print_inventory_cut.php - Corte de Inventario Físico Interactivo
// Centro Médico RS - Sistema de Gestión Médica
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/multitenant.php';
require_once '../../includes/module_guard.php';

if (!isset($_SESSION['user_id'])) {
    die("Acceso no autorizado");
}

$hosp_id = hospital_id();
$user_id = $_SESSION['user_id'];

try {
    $database = new Database();
    $conn = $database->getConnection();

    // Create conteo_fisico table if not exists
    $conn->exec("CREATE TABLE IF NOT EXISTS conteo_fisico (
        id_conteo INT AUTO_INCREMENT PRIMARY KEY,
        id_inventario INT NOT NULL,
        id_hospital INT NOT NULL,
        id_usuario INT NOT NULL,
        cantidad_sistema INT NOT NULL DEFAULT 0,
        cantidad_fisica INT NOT NULL DEFAULT 0,
        diferencia INT NOT NULL DEFAULT 0,
        estado ENUM('Pendiente','Listo') DEFAULT 'Pendiente',
        fecha_conteo TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        fecha_equilibrado TIMESTAMP NULL DEFAULT NULL,
        INDEX (id_inventario),
        INDEX (id_hospital),
        INDEX (estado),
        INDEX (fecha_conteo)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Migration: drop old unique constraint if it exists
    try {
        $conn->exec("ALTER TABLE conteo_fisico DROP INDEX unique_item_hospital_date");
    } catch (Exception $e) {
        // Constraint doesn't exist, ignore
    }

    // Get today's date for filtering counts
    $today = date('Y-m-d');

    // Fetch active inventory items
    $sql = "SELECT i.id_inventario, i.codigo_barras, i.nom_medicamento, i.mol_medicamento, i.presentacion_med, 
                   i.cantidad_med, i.fecha_vencimiento, 
                   COALESCE(ph.document_number, '') as document_number, 
                   COALESCE(ph.document_type, '') as document_type
            FROM inventario i
            LEFT JOIN purchase_items pi ON i.id_purchase_item = pi.id
            LEFT JOIN purchase_headers ph ON pi.purchase_header_id = ph.id
            WHERE i.id_hospital = ?
            ORDER BY i.nom_medicamento ASC";

    $stmt = $conn->prepare($sql);
    $stmt->execute([$hosp_id]);
    $inventory = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch today's physical counts
    $stmt_counts = $conn->prepare("
        SELECT id_inventario, cantidad_fisica, diferencia, estado, fecha_equilibrado
        FROM conteo_fisico 
        WHERE id_hospital = ? AND DATE(fecha_conteo) = ?
    ");
    $stmt_counts->execute([$hosp_id, $today]);
    $counts_map = [];
    while ($c = $stmt_counts->fetch(PDO::FETCH_ASSOC)) {
        $counts_map[$c['id_inventario']] = $c;
    }

    // Stats
    $total_items = count($inventory);
    $counted_items = 0;
    $pending_items = 0;
    $discrepancy_items = 0;
    $balanced_items = 0;

    foreach ($inventory as $item) {
        $id = $item['id_inventario'];
        if (isset($counts_map[$id])) {
            $counted_items++;
            if ($counts_map[$id]['estado'] === 'Listo') {
                $balanced_items++;
            }
            if ($counts_map[$id]['diferencia'] != 0) {
                $discrepancy_items++;
            }
        } else {
            $pending_items++;
        }
    }

} catch (Exception $e) {
    error_log("php/inventory/print_inventory_cut.php error: " . $e->getMessage());
        die("Error: " . 'Error del servidor.');
}
?>
<!DOCTYPE html>
<html lang="es" data-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Corte de Inventario Físico - <?php echo date('d/m/Y'); ?></title>

    <link rel="icon" type="image/png" href="../../assets/img/Logo.png">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <?php include '../../includes/theme_head.php'; ?>

    <style>
        .stats-bar {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        .stat-box {
            background: var(--color-card);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-lg);
            padding: 1rem 1.25rem;
            text-align: center;
        }
        .stat-box .stat-num {
            font-size: 1.75rem;
            font-weight: 800;
            line-height: 1;
        }
        .stat-box .stat-label {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--color-text-secondary);
            margin-top: 0.25rem;
        }
        .stat-box.total .stat-num { color: var(--color-primary); }
        .stat-box.counted .stat-num { color: var(--color-success); }
        .stat-box.pending .stat-num { color: var(--color-warning); }
        .stat-box.discrepancy .stat-num { color: var(--color-danger); }
        .stat-box.balanced .stat-num { color: var(--color-info); }

        .count-input {
            width: 70px;
            text-align: center;
            padding: 0.35rem 0.5rem;
            border: 1.5px solid var(--color-border);
            border-radius: var(--radius-sm);
            background: var(--color-card);
            color: var(--color-text);
            font-size: 0.9rem;
            font-weight: 600;
            font-family: var(--font-family);
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .count-input:focus {
            border-color: var(--color-primary);
            box-shadow: 0 0 0 3px rgba(var(--color-primary-rgb), 0.13);
            outline: none;
        }
        .count-input.counted {
            border-color: var(--color-success);
            background: rgba(var(--color-success-rgb), 0.05);
        }

        .diff-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.2rem 0.6rem;
            border-radius: var(--radius-sm);
            font-size: 0.8rem;
            font-weight: 700;
        }
        .diff-badge.zero {
            background: rgba(var(--color-success-rgb), 0.12);
            color: var(--color-success);
        }
        .diff-badge.negative {
            background: rgba(var(--color-danger-rgb), 0.12);
            color: var(--color-danger);
        }
        .diff-badge.positive {
            background: rgba(var(--color-warning-rgb), 0.12);
            color: var(--color-warning);
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            padding: 0.25rem 0.65rem;
            border-radius: var(--radius-sm);
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .status-badge.pendiente {
            background: rgba(var(--color-warning-rgb), 0.12);
            color: var(--color-warning);
        }
        .status-badge.listo {
            background: rgba(var(--color-success-rgb), 0.12);
            color: var(--color-success);
        }
        .status-badge.equilibrado {
            background: rgba(var(--color-info-rgb), 0.12);
            color: var(--color-info);
        }

        .balance-btn {
            padding: 0.3rem 0.6rem;
            border: none;
            border-radius: var(--radius-sm);
            background: var(--color-info);
            color: white;
            font-size: 0.72rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            white-space: nowrap;
        }
        .balance-btn:hover {
            filter: brightness(1.1);
            transform: translateY(-1px);
        }
        .balance-btn:disabled {
            opacity: 0.4;
            cursor: not-allowed;
            transform: none;
        }

        .row-balanced {
            background: rgba(var(--color-info-rgb), 0.04) !important;
        }
        .row-has-count {
            background: rgba(var(--color-success-rgb), 0.03) !important;
        }

        .filter-bar {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
            margin-bottom: 1rem;
        }
        .filter-btn {
            padding: 0.4rem 0.85rem;
            border: 1.5px solid var(--color-border);
            border-radius: var(--radius-md);
            background: var(--color-card);
            color: var(--color-text-secondary);
            font-size: 0.78rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        .filter-btn:hover {
            border-color: var(--color-primary);
            color: var(--color-primary);
        }
        .filter-btn.active {
            background: var(--color-primary);
            border-color: var(--color-primary);
            color: white;
        }

        .bulk-actions {
            display: flex;
            gap: 0.75rem;
            align-items: center;
        }

        @media print {
            .no-print { display: none !important; }
            body, html {
                background: white !important;
                color: black !important;
                padding: 0 !important;
                margin: 0 !important;
            }
            .dashboard-container {
                margin: 0 !important;
                padding: 0 !important;
                background: transparent !important;
                box-shadow: none !important;
            }
            .main-content { padding: 0 !important; margin: 0 !important; }
            * {
                background: transparent !important;
                color: black !important;
                box-shadow: none !important;
                text-shadow: none !important;
                backdrop-filter: none !important;
                -webkit-backdrop-filter: none !important;
            }
            .data-table {
                border: 1px solid #ddd !important;
                border-spacing: 0 !important;
                width: 100% !important;
            }
            .data-table th {
                border-bottom: 2px solid #000 !important;
                color: #000 !important;
                background: #f5f5f5 !important;
                padding: 0.5rem !important;
            }
            .data-table td {
                border-bottom: 1px solid #ddd !important;
                padding: 0.5rem !important;
            }
            .count-input {
                border: 1px solid #ccc !important;
                background: transparent !important;
                box-shadow: none !important;
            }
            .row-balanced, .row-has-count { background: transparent !important; }
        }
    </style>
</head>

<body class="p-4">
    <div class="marble-effect no-print"></div>

    <div class="dashboard-container">
        <header class="dashboard-header no-print">
            <div class="header-content">
                <div class="brand-container">
                    <img src="../../assets/img/Logo.png" alt="Centro Médico RS" class="brand-logo" width="40" height="40">
                </div>
                <div class="header-controls">
                    <div class="theme-toggle">
                        <button id="themeSwitch" class="theme-btn" aria-label="Cambiar tema claro/oscuro">
                            <i class="bi bi-sun theme-icon sun-icon"></i>
                            <i class="bi bi-moon theme-icon moon-icon"></i>
                        </button>
                    </div>
                    <div class="header-user">
                        <div class="header-avatar">
                            <?php echo isset($_SESSION['nombre']) ? strtoupper(substr($_SESSION['nombre'], 0, 1)) : 'U'; ?>
                        </div>
                        <div class="header-details">
                            <span class="header-name"><?php echo htmlspecialchars($_SESSION['nombre'] ?? 'Usuario'); ?></span>
                            <span class="header-role">Inventario</span>
                        </div>
                    </div>
                    <a href="index.php" class="action-btn secondary">
                        <i class="bi bi-arrow-left"></i> Volver
                    </a>
                    <a href="../auth/logout.php" class="logout-btn">
                        <i class="bi bi-box-arrow-right"></i> <span>Salir</span>
                    </a>
                </div>
            </div>
        </header>

        <main class="main-content">
            <section class="calendar-section animate-in mb-4 no-print">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <div>
                        <h3 class="section-title mb-1">
                            <i class="bi bi-clipboard-check section-title-icon"></i>
                            Corte de Inventario Físico
                        </h3>
                        <p class="text-muted small mb-0">
                            Fecha: <strong class="text-primary"><?php echo date('d/m/Y'); ?></strong>
                            <span class="mx-2">•</span>
                            Responsable: <strong><?php echo htmlspecialchars($_SESSION['nombre'] ?? 'Usuario'); ?></strong>
                        </p>
                    </div>
                    <div class="bulk-actions">
                        <button class="action-btn" onclick="window.print()">
                            <i class="bi bi-printer me-1"></i> Imprimir
                        </button>
                        <button class="action-btn" style="background: var(--color-warning);" id="balanceAllBtn" onclick="balanceAllDiscrepancies()">
                            <i class="bi bi-arrow-left-right me-1"></i> Equilibrar Todo
                        </button>
                    </div>
                </div>
            </section>

            <!-- Stats Bar -->
            <div class="stats-bar animate-in no-print">
                <div class="stat-box total">
                    <div class="stat-num"><?php echo $total_items; ?></div>
                    <div class="stat-label">Total Items</div>
                </div>
                <div class="stat-box counted">
                    <div class="stat-num" id="statCounted"><?php echo $counted_items; ?></div>
                    <div class="stat-label">Contados</div>
                </div>
                <div class="stat-box pending">
                    <div class="stat-num" id="statPending"><?php echo $pending_items; ?></div>
                    <div class="stat-label">Pendientes</div>
                </div>
                <div class="stat-box discrepancy">
                    <div class="stat-num" id="statDiscrepancy"><?php echo $discrepancy_items; ?></div>
                    <div class="stat-label">Con Diferencia</div>
                </div>
                <div class="stat-box balanced">
                    <div class="stat-num" id="statBalanced"><?php echo $balanced_items; ?></div>
                    <div class="stat-label">Equilibrados</div>
                </div>
            </div>

            <!-- Filter Bar -->
            <div class="filter-bar no-print">
                <button class="filter-btn active" data-filter="all">
                    <i class="bi bi-grid me-1"></i> Todos
                </button>
                <button class="filter-btn" data-filter="pending">
                    <i class="bi bi-clock me-1"></i> Pendientes
                </button>
                <button class="filter-btn" data-filter="counted">
                    <i class="bi bi-check-circle me-1"></i> Contados
                </button>
                <button class="filter-btn" data-filter="discrepancy">
                    <i class="bi bi-exclamation-triangle me-1"></i> Con Diferencia
                </button>
                <button class="filter-btn" data-filter="balanced">
                    <i class="bi bi-check2-circle me-1"></i> Equilibrados
                </button>
            </div>

            <!-- Table -->
            <div class="card animate-in delay-1 p-4 mb-4">
                <div class="header text-center mb-4">
                    <h4 class="fw-bold">Centro Médico</h4>
                    <h5 class="text-muted">Hoja de Conteo Físico</h5>
                    <p class="mb-0"><strong>Fecha:</strong> <?php echo date('d/m/Y H:i A'); ?></p>
                </div>

                <div class="table-responsive">
                    <table class="data-table" id="inventoryTable">
                        <thead>
                            <tr>
                                <th>Código</th>
                                <th>Producto / Presentación</th>
                                <th>Lote/Doc</th>
                                <th>Vencimiento</th>
                                <th class="text-center" style="width: 10%;">Cant. Sistema</th>
                                <th class="text-center" style="width: 12%;">Cant. Física</th>
                                <th class="text-center" style="width: 10%;">Diferencia</th>
                                <th class="text-center" style="width: 10%;">Estado</th>
                                <th class="text-center no-print" style="width: 14%;">Acción</th>
                            </tr>
                        </thead>
                        <tbody id="inventoryBody">
                            <?php if (count($inventory) > 0): ?>
                                <?php foreach ($inventory as $row): ?>
                                    <?php
                                    $id = $row['id_inventario'];
                                    $sys_qty = $row['cantidad_med'];
                                    $has_count = isset($counts_map[$id]);
                                    $phy_qty = $has_count ? $counts_map[$id]['cantidad_fisica'] : '';
                                    $diff = $has_count ? $counts_map[$id]['diferencia'] : '';
                                    $status = $has_count ? $counts_map[$id]['estado'] : 'Pendiente';
                                    $is_balanced = $has_count && $counts_map[$id]['fecha_equilibrado'] !== null;

                                    $row_class = '';
                                    if ($is_balanced) $row_class = 'row-balanced';
                                    elseif ($has_count) $row_class = 'row-has-count';

                                    $status_class = strtolower($status);
                                    if ($is_balanced) $status_class = 'equilibrado';

                                    $diff_class = 'zero';
                                    if ($has_count) {
                                        if ($diff < 0) $diff_class = 'negative';
                                        elseif ($diff > 0) $diff_class = 'positive';
                                    }
                                    ?>
                                    <tr class="<?php echo $row_class; ?>" 
                                        data-id="<?php echo $id; ?>"
                                        data-status="<?php echo $has_count ? 'counted' : 'pending'; ?>"
                                        data-diff="<?php echo $has_count ? $diff : ''; ?>"
                                        data-balanced="<?php echo $is_balanced ? '1' : '0'; ?>">
                                        <td><?php echo htmlspecialchars($row['codigo_barras'] ?? 'N/A'); ?></td>
                                        <td>
                                            <div class="fw-semibold"><?php echo htmlspecialchars($row['nom_medicamento']); ?></div>
                                            <div class="small text-muted"><?php echo htmlspecialchars($row['mol_medicamento'] . ' • ' . $row['presentacion_med']); ?></div>
                                        </td>
                                        <td><?php echo htmlspecialchars($row['document_number'] ?: ($row['document_type'] ?: 'N/A')); ?></td>
                                        <td><?php echo $row['fecha_vencimiento'] ? date('d/m/y', strtotime($row['fecha_vencimiento'])) : 'N/A'; ?></td>
                                        <td class="text-center fw-bold text-muted bg-light sys-qty">
                                            <?php echo $sys_qty; ?>
                                        </td>
                                        <td class="text-center">
                                            <input type="number" 
                                                   class="count-input <?php echo $has_count ? 'counted' : ''; ?>" 
                                                   data-id="<?php echo $id; ?>"
                                                   data-sys="<?php echo $sys_qty; ?>"
                                                   value="<?php echo $phy_qty; ?>"
                                                   min="0"
                                                   placeholder="-"
                                                   <?php echo $is_balanced ? 'disabled' : ''; ?>>
                                        </td>
                                        <td class="text-center diff-cell">
                                            <?php if ($has_count): ?>
                                                <span class="diff-badge <?php echo $diff_class; ?>">
                                                    <?php echo $diff > 0 ? '+' : ''; ?><?php echo $diff; ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <span class="status-badge <?php echo $status_class; ?>">
                                                <?php if ($is_balanced): ?>
                                                    <i class="bi bi-check2-circle"></i> Equilibrado
                                                <?php elseif ($status === 'Listo'): ?>
                                                    <i class="bi bi-check-circle"></i> Listo
                                                <?php else: ?>
                                                    <i class="bi bi-clock"></i> Pendiente
                                                <?php endif; ?>
                                            </span>
                                        </td>
                                        <td class="text-center no-print">
                                            <?php if ($has_count && $diff != 0 && !$is_balanced): ?>
                                                <button class="balance-btn" data-id="<?php echo $id; ?>" onclick="balanceItem(<?php echo $id; ?>)">
                                                    <i class="bi bi-arrow-left-right"></i> Equilibrar
                                                </button>
                                            <?php elseif ($is_balanced): ?>
                                                <span class="text-muted small"><i class="bi bi-check-all"></i> OK</span>
                                            <?php else: ?>
                                                <span class="text-muted small">-</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="9" class="text-center py-4 text-muted">No hay insumos registrados en el sistema.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Firmas -->
                <div class="signature-section mt-5 pt-4 text-center">
                    <div class="row">
                        <div class="col-6">
                            <div class="signature-line"></div>
                            <p class="mt-2 text-muted fw-semibold small">Firma Responsable</p>
                        </div>
                        <div class="col-6">
                            <div class="signature-line"></div>
                            <p class="mt-2 text-muted fw-semibold small">Firma Recibido</p>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Theme toggle
        const themeSwitch = document.getElementById('themeSwitch');
        if (themeSwitch) {
            themeSwitch.addEventListener('click', () => {
                const current = document.documentElement.getAttribute('data-theme');
                const next = current === 'light' ? 'dark' : 'light';
                document.documentElement.setAttribute('data-theme', next);
                localStorage.setItem('dashboard-theme', next);
            });
        }

        // Stats update
        function updateStats() {
            const rows = document.querySelectorAll('#inventoryBody tr[data-id]');
            let counted = 0, pending = 0, discrepancy = 0, balanced = 0;
            rows.forEach(row => {
                if (row.dataset.balanced === '1') {
                    balanced++;
                    counted++;
                } else if (row.dataset.diff !== '') {
                    counted++;
                    if (parseInt(row.dataset.diff) !== 0) discrepancy++;
                } else {
                    pending++;
                }
            });
            document.getElementById('statCounted').textContent = counted;
            document.getElementById('statPending').textContent = pending;
            document.getElementById('statDiscrepancy').textContent = discrepancy;
            document.getElementById('statBalanced').textContent = balanced;
        }

        // Filter buttons
        document.querySelectorAll('.filter-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                const filter = btn.dataset.filter;
                document.querySelectorAll('#inventoryBody tr[data-id]').forEach(row => {
                    let show = true;
                    if (filter === 'pending') show = row.dataset.status === 'pending';
                    else if (filter === 'counted') show = row.dataset.status === 'counted' && row.dataset.balanced !== '1';
                    else if (filter === 'discrepancy') show = row.dataset.diff !== '' && parseInt(row.dataset.diff) !== 0;
                    else if (filter === 'balanced') show = row.dataset.balanced === '1';
                    row.style.display = show ? '' : 'none';
                });
            });
        });

        // Physical count input handler
        let debounceTimers = {};
        document.querySelectorAll('.count-input').forEach(input => {
            input.addEventListener('input', function() {
                const id = this.dataset.id;
                const sysQty = parseInt(this.dataset.sys);
                const phyQty = parseInt(this.value) || 0;
                const diff = phyQty - sysQty;
                const row = this.closest('tr');
                const diffCell = row.querySelector('.diff-cell');

                clearTimeout(debounceTimers[id]);
                debounceTimers[id] = setTimeout(() => {
                    if (this.value === '' || this.value === null) {
                        // Clear count
                        saveCount(id, null, sysQty);
                    } else {
                        saveCount(id, phyQty, sysQty);
                    }
                }, 800);

                // Update diff display immediately
                if (this.value !== '' && this.value !== null) {
                    this.classList.add('counted');
                    row.dataset.status = 'counted';
                    row.dataset.diff = diff;
                    row.classList.add('row-has-count');
                    row.classList.remove('row-balanced');
                    row.dataset.balanced = '0';

                    const diffClass = diff === 0 ? 'zero' : (diff < 0 ? 'negative' : 'positive');
                    diffCell.innerHTML = `<span class="diff-badge ${diffClass}">${diff > 0 ? '+' : ''}${diff}</span>`;

                    // Update status badge
                    const statusBadge = row.querySelector('.status-badge');
                    statusBadge.className = 'status-badge listo';
                    statusBadge.innerHTML = '<i class="bi bi-check-circle"></i> Listo';

                    // Update action cell
                    const actionCell = row.querySelector('td.no-print');
                    if (diff !== 0) {
                        actionCell.innerHTML = `<button class="balance-btn" data-id="${id}" onclick="balanceItem(${id})"><i class="bi bi-arrow-left-right"></i> Equilibrar</button>`;
                    } else {
                        actionCell.innerHTML = '<span class="text-muted small"><i class="bi bi-check-all"></i> OK</span>';
                    }
                } else {
                    this.classList.remove('counted');
                    row.dataset.status = 'pending';
                    row.dataset.diff = '';
                    row.classList.remove('row-has-count', 'row-balanced');
                    row.dataset.balanced = '0';
                    diffCell.innerHTML = '<span class="text-muted">-</span>';
                    const statusBadge = row.querySelector('.status-badge');
                    statusBadge.className = 'status-badge pendiente';
                    statusBadge.innerHTML = '<i class="bi bi-clock"></i> Pendiente';
                    const actionCell = row.querySelector('td.no-print');
                    actionCell.innerHTML = '<span class="text-muted small">-</span>';
                }
                updateStats();
            });
        });

        function saveCount(id, phyQty, sysQty) {
            const diff = phyQty !== null ? phyQty - sysQty : 0;
            const status = phyQty !== null ? 'Listo' : 'Pendiente';

            fetch('api/save_physical_count.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `id_inventario=${id}&cantidad_fisica=${phyQty !== null ? phyQty : ''}&diferencia=${diff}&estado=${status}`
            })
            .then(r => r.json())
            .then(data => {
                if (!data.success) {
                    Swal.fire('Error', data.message || 'No se pudo guardar el conteo', 'error');
                }
            })
            .catch(err => {
                console.error('Error saving count:', err);
            });
        }

        function balanceItem(id) {
            const row = document.querySelector(`tr[data-id="${id}"]`);
            const sysQty = parseInt(row.querySelector('.sys-qty').textContent);
            const phyQty = parseInt(row.querySelector('.count-input').value) || 0;
            const itemName = row.querySelector('.fw-semibold').textContent;

            Swal.fire({
                title: 'Equilibrar Inventario',
                html: `
                    <div style="text-align: left; font-size: 0.9rem;">
                        <p><strong>Producto:</strong> ${itemName}</p>
                        <p><strong>Cant. Sistema:</strong> ${sysQty}</p>
                        <p><strong>Cant. Física:</strong> ${phyQty}</p>
                        <p><strong>Diferencia:</strong> ${phyQty - sysQty > 0 ? '+' : ''}${phyQty - sysQty}</p>
                        <hr>
                        <p style="color: var(--color-warning);">
                            <i class="bi bi-exclamation-triangle"></i>
                            Esta acción ajustará el stock del sistema a <strong>${phyQty}</strong> unidades.
                        </p>
                    </div>
                `,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#0ea5e9',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Sí, equilibrar',
                cancelButtonText: 'Cancelar'
            }).then(result => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'Equilibrando...',
                        didOpen: () => Swal.showLoading()
                    });

                    fetch('api/balance_inventory.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `id_inventario=${id}&nueva_cantidad=${phyQty}`
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            // Update row UI
                            row.querySelector('.sys-qty').textContent = phyQty;
                            row.querySelector('.count-input').value = phyQty;
                            row.querySelector('.count-input').dataset.sys = phyQty;
                            row.querySelector('.count-input').disabled = true;
                            row.dataset.diff = '0';
                            row.dataset.balanced = '1';
                            row.classList.remove('row-has-count');
                            row.classList.add('row-balanced');

                            const diffCell = row.querySelector('.diff-cell');
                            diffCell.innerHTML = '<span class="diff-badge zero">0</span>';

                            const statusBadge = row.querySelector('.status-badge');
                            statusBadge.className = 'status-badge equilibrado';
                            statusBadge.innerHTML = '<i class="bi bi-check2-circle"></i> Equilibrado';

                            const actionCell = row.querySelector('td.no-print');
                            actionCell.innerHTML = '<span class="text-muted small"><i class="bi bi-check-all"></i> OK</span>';

                            updateStats();

                            Swal.fire('Listo', 'Inventario equilibrado correctamente', 'success');
                        } else {
                            Swal.fire('Error', data.message || 'No se pudo equilibrar el inventario', 'error');
                        }
                    })
                    .catch(err => {
                        Swal.fire('Error', 'Error de conexión', 'error');
                    });
                }
            });
        }

        function balanceAllDiscrepancies() {
            const rows = document.querySelectorAll('#inventoryBody tr[data-id]');
            const items = [];
            rows.forEach(row => {
                if (row.dataset.diff !== '' && parseInt(row.dataset.diff) !== 0 && row.dataset.balanced !== '1') {
                    items.push({
                        id: row.dataset.id,
                        name: row.querySelector('.fw-semibold').textContent,
                        sys: row.querySelector('.sys-qty').textContent,
                        phy: row.querySelector('.count-input').value || '0'
                    });
                }
            });

            if (items.length === 0) {
                Swal.fire('Info', 'No hay diferencias pendientes por equilibrar', 'info');
                return;
            }

            let html = '<div style="text-align: left; max-height: 300px; overflow-y: auto;">';
            items.forEach(item => {
                html += `<p style="margin: 0.25rem 0; font-size: 0.85rem;">
                    <strong>${item.name}</strong>: ${item.sys} → ${item.phy} 
                    (<span style="color: var(--color-warning);">${parseInt(item.phy) - parseInt(item.sys) > 0 ? '+' : ''}${parseInt(item.phy) - parseInt(item.sys)}</span>)
                </p>`;
            });
            html += '</div>';

            Swal.fire({
                title: `Equilibrar ${items.length} item(s)`,
                html: html + '<hr><p style="color: var(--color-warning); font-size: 0.85rem;"><i class="bi bi-exclamation-triangle"></i> Esta acción ajustará el stock de todos los items listados.</p>',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#0ea5e9',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Sí, equilibrar todo',
                cancelButtonText: 'Cancelar'
            }).then(result => {
                if (result.isConfirmed) {
                    let completed = 0;
                    const total = items.length;

                    Swal.fire({
                        title: 'Equilibrando...',
                        html: `<p>Procesando ${completed} de ${total}...</p>`,
                        didOpen: () => Swal.showLoading(),
                        allowOutsideClick: false
                    });

                    function processNext(index) {
                        if (index >= total) {
                            Swal.fire('Completado', `${total} item(s) equilibrados correctamente`, 'success');
                            location.reload();
                            return;
                        }

                        const item = items[index];
                        fetch('api/balance_inventory.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: `id_inventario=${item.id}&nueva_cantidad=${item.phy}`
                        })
                        .then(r => r.json())
                        .then(data => {
                            completed++;
                            Swal.update({ html: `<p>Procesando ${completed} de ${total}...</p>` });
                            processNext(index + 1);
                        })
                        .catch(err => {
                            completed++;
                            processNext(index + 1);
                        });
                    }

                    processNext(0);
                }
            });
        }

        // Init stats
        updateStats();
    </script>
</body>

</html>
