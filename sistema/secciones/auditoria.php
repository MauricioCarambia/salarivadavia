<?php
require_once __DIR__ . '/../inc/db.php';

if (empty($_SESSION['login'])) {
    header("Location: login.php");
    exit;
}

if (empty($_SESSION['es_admin']) && !in_array('auditoria', $_SESSION['accesos'] ?? [])) {
    die('<div class="alert alert-danger">No tenés permisos para acceder a esta sección</div>');
}

// Limpieza automática: borra registros con 2 meses o más de antigüedad
// cada vez que se abre esta sección, sin depender de un cron externo
$pdo->prepare("DELETE FROM auditoria WHERE creado_en < DATE_SUB(NOW(), INTERVAL 2 MONTH)")
    ->execute();

$stmt = $pdo->query("
    SELECT id, usuario_nombre, accion, detalle, motivo, creado_en
    FROM auditoria
    ORDER BY creado_en DESC
");
$registros = $stmt->fetchAll(PDO::FETCH_ASSOC);

$accionLabels = [
    'cobro_anulado'            => 'Comprobante anulado',
    'pago_afiliado_eliminado'  => 'Cuota de socio eliminada',
    'acceso_bloqueado'         => 'Acceso bloqueado por IP',
    'acceso_concedido'         => 'Inicio de sesión',
    'autorizacion_ip_otorgada' => 'Autorización de acceso remoto otorgada',
    'autorizacion_ip_revocada' => 'Autorización de acceso remoto revocada',
];
?>

<div class="card card-info card-outline">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-history"></i> Auditoría de anulaciones</h3>
    </div>

    <div class="card-body table-responsive">
        <table class="table table-bordered table-striped datatable">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Usuario</th>
                    <th>Acción</th>
                    <th>Detalle</th>
                    <th>Motivo</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($registros as $r): ?>
                    <tr>
                        <td><?= htmlspecialchars(date('d/m/Y H:i:s', strtotime($r['creado_en']))) ?></td>
                        <td><?= htmlspecialchars($r['usuario_nombre'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($accionLabels[$r['accion']] ?? $r['accion']) ?></td>
                        <td><?= nl2br(htmlspecialchars($r['detalle'] ?? '')) ?></td>
                        <td><?= nl2br(htmlspecialchars($r['motivo'] ?? '')) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    $(document).ready(function() {
        initDataTable($('.datatable'), {
            order: [
                [0, "desc"]
            ]
        });
    });
</script>
