<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/multitenant.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit;
}

$db = new Database();
$conn = $db->getConnection();
$h_config = get_hospital_config($conn, $_SESSION['id_hospital']);

$page_title = "Suscripción - " . $h_config['nombre'];
include_once '../../includes/header.php';
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-lg border-0 rounded-4">
                <div class="card-header bg-primary text-white p-4 rounded-top-4">
                    <h3 class="mb-0"><i class="bi bi-credit-card-2-front me-2"></i>Estado de Suscripción</h3>
                </div>
                <div class="card-body p-5">
                    <div class="row mb-4">
                        <div class="col-sm-4 text-muted">Hospital:</div>
                        <div class="col-sm-8 fw-bold"><?php echo htmlspecialchars($h_config['nombre']); ?></div>
                    </div>
                    <div class="row mb-4">
                        <div class="col-sm-4 text-muted">Plan Actual:</div>
                        <div class="col-sm-8">
                            <span class="badge bg-info text-dark px-3 py-2">Plan Modular Personalizado</span>
                        </div>
                    </div>
                    <div class="row mb-4">
                        <div class="col-sm-4 text-muted">Estado:</div>
                        <div class="col-sm-8">
                            <?php if ($h_config['estado_suscripcion'] === 'Activo'): ?>
                                <span class="badge bg-success px-3 py-2">ACTIVO</span>
                            <?php else: ?>
                                <span class="badge bg-danger px-3 py-2"><?php echo strtoupper($h_config['estado_suscripcion']); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="row mb-4">
                        <div class="col-sm-4 text-muted">Vencimiento:</div>
                        <div class="col-sm-8 fw-bold text-primary">
                            <?php echo $h_config['fecha_vencimiento'] ?: 'No definido'; ?>
                        </div>
                    </div>
                    
                    <hr class="my-4">
                    
                    <h5>Módulos Contratados</h5>
                    <div class="d-flex flex-wrap gap-2 mt-3">
                        <?php foreach ($h_config['modulos_activos'] as $mod): ?>
                            <span class="badge border text-primary border-primary px-3 py-2">
                                <i class="bi bi-check-circle-fill me-1"></i> <?php echo ucfirst($mod); ?>
                            </span>
                        <?php endforeach; ?>
                    </div>

                    <div class="mt-5 p-4 bg-light rounded-3 border">
                        <h6><i class="bi bi-info-circle me-2"></i>Métodos de Pago Aceptados</h6>
                        <ul class="list-unstyled mt-3 mb-0">
                            <li><i class="bi bi-bank me-2"></i>Transferencia Bancaria (BI, Banrural, G&T)</li>
                            <li><i class="bi bi-credit-card me-2"></i>Tarjeta de Crédito/Débito (Visa, Mastercard)</li>
                            <li><i class="bi bi-paypal me-2"></i>PayPal / Enlace de Pago</li>
                        </ul>
                    </div>
                </div>
                <div class="card-footer bg-white p-4 text-center">
                    <a href="../dashboard/index.php" class="btn btn-outline-secondary px-4">Volver al Panel</a>
                    <a href="https://wa.me/tu_numero" class="btn btn-primary px-4 ms-2">Renovar o Agregar Módulos</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once '../../includes/footer.php'; ?>
