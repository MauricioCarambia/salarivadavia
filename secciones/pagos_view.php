<?php
date_default_timezone_set('America/Argentina/Buenos_Aires');
require_once 'inc/db.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$rand = random_int(1000, 9999);
$fecha = $_POST['fecha'] ?? date('Y-m-d');

// 1. Info del profesional
$stmt = $pdo->prepare("
    SELECT *, CONCAT(apellido,' ',nombre) AS nombre_completo
    FROM profesionales
    WHERE Id = :id
");
$stmt->execute([':id' => $id]);
$prof = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$prof) {
    die('<div class="alert alert-danger">Profesional no encontrado</div>');
}

// Rango de fecha para el filtro
$inicioDia = $fecha . ' 00:00:00';
$finDia = $fecha . ' 23:59:59';

/* ==============================
   📊 CONSULTA SQL COMPLETA
   Incluye todos los destinos y excluye cobros anulados
============================== */
$sql = "
SELECT 
    c.id AS cobro_id,
    c.fecha,
    pa.nombre AS paciente_nom,
    pa.apellido AS paciente_ape,
    SUM(cr.monto) AS total_facturado,
    SUM(CASE WHEN cr.destino = 'profesional' THEN cr.monto ELSE 0 END) AS pago_profesional,
    SUM(CASE WHEN cr.destino = 'clinica' THEN cr.monto ELSE 0 END) AS pago_clinica,
    SUM(CASE WHEN cr.destino = 'farmacia' THEN cr.monto ELSE 0 END) AS pago_farmacia,
    SUM(CASE WHEN cr.destino = 'patologia' THEN cr.monto ELSE 0 END) AS pago_patologia
FROM cobros c
JOIN cobros_reparto cr ON c.id = cr.cobro_id
LEFT JOIN pacientes pa ON c.paciente_id = pa.id
WHERE c.profesional_id = :id
  AND c.estado = 'activo'
  AND c.fecha BETWEEN :inicio AND :fin
GROUP BY c.id
ORDER BY c.fecha DESC
";

$stmtPagos = $pdo->prepare($sql);
$stmtPagos->execute([':id' => $id, ':inicio' => $inicioDia, ':fin' => $finDia]);
$pagos = $stmtPagos->fetchAll(PDO::FETCH_ASSOC);

// Inicializar acumuladores
$totalFacturado = $totalComisiones = $totalGanancias = $total_efectivo = $total_transferencia = 0;
$totalClinica = $totalFarmacia = $totalPatologia = 0;

foreach ($pagos as $p) {
    $totalFacturado += (float)$p['total_facturado'];
    $totalComisiones += (float)$p['pago_profesional'];
    $totalClinica += (float)$p['pago_clinica'];
    $totalFarmacia += (float)$p['pago_farmacia'];
    $totalPatologia += (float)$p['pago_patologia'];

    $totalGanancias += (float)$p['pago_clinica'] + (float)$p['pago_patologia']; // Ganancia clínica
   
}
?>

<div class="content">
    <div class="container-fluid">

        <!-- Resumen Profesional -->
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-user-md"></i> Reporte: <?= htmlspecialchars($prof['nombre_completo']) ?></h3>
                <div class="card-tools">
                    <a href="./?seccion=profesionales_reporte&nc=<?= $rand ?>" class="btn btn-secondary btn-sm">
                        <i class="fas fa-arrow-left"></i> Volver
                    </a>
                </div>
            </div>
            <div class="card-body">
                <form method="post" class="form-inline mb-4">
                    <label class="mr-2">Fecha de consulta:</label>
                    <input type="date" name="fecha" class="form-control mr-2" value="<?= $fecha ?>">
                    <button type="submit" class="btn btn-primary">Actualizar</button>
                </form>

                <div class="row text-center">
                    <div class="col-md-2"><strong>Facturado</strong><br><span class="h5">$<?= number_format($totalFacturado,2,',','.') ?></span></div>
                    <div class="col-md-2 text-primary"><strong>A Pagar Prof.</strong><br><span class="h5">$<?= number_format($totalComisiones,2,',','.') ?></span></div>
                    <div class="col-md-2 text-success"><strong>Ganancia Clínica</strong><br><span class="h5">$<?= number_format($totalGanancias,2,',','.') ?></span></div>
                    <div class="col-md-2"><strong>Farmacia</strong><br><span>$<?= number_format($totalFarmacia,2,',','.') ?></span></div>
                    <div class="col-md-2"><strong>Patología</strong><br><span>$<?= number_format($totalPatologia,2,',','.') ?></span></div>
                    <div class="col-md-2"><strong>Transferencia</strong><br><span>$<?= number_format($total_transferencia,2,',','.') ?></span></div>
                    <div class="col-md-2"><strong>Efectivo</strong><br><span>$<?= number_format($total_efectivo,2,',','.') ?></span></div>
                </div>
            </div>
        </div>

        <!-- Detalle Cobros -->
        <div class="card card-outline card-primary">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-list"></i> Detalle de Cobros</h3>
            </div>
            <div class="card-body table-responsive">
                <table class="table table-bordered table-striped datatable">
                    <thead>
                        <tr class="text-center">
                            <th>Fecha</th>
                            <th>Paciente</th>
                            <th>Total Facturado</th>
                            <th>Reparto Prof.</th>
                            <th>Clínica</th>
                            <th>Farmacia</th>
                            <th>Patología</th>
                            <th>Ganancia Clínica</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pagos as $p): 
                            $gananciaFila = (float)$p['pago_clinica'] + (float)$p['pago_patologia'];
                        ?>
                        <tr>
                            <td class="text-center"><?= date('d/m/Y H:i', strtotime($p['fecha'])) ?></td>
                            <td><?= htmlspecialchars($p['paciente_ape'].' '.$p['paciente_nom']) ?></td>
                            <td class="text-right">$ <?= number_format($p['total_facturado'],2,',','.') ?></td>
                            <td class="text-right text-primary">$ <?= number_format($p['pago_profesional'],2,',','.') ?></td>
                            <td class="text-right">$ <?= number_format($p['pago_clinica'],2,',','.') ?></td>
                            <td class="text-right">$ <?= number_format($p['pago_farmacia'],2,',','.') ?></td>
                            <td class="text-right">$ <?= number_format($p['pago_patologia'],2,',','.') ?></td>
                            <td class="text-right text-success font-weight-bold">$ <?= number_format($gananciaFila,2,',','.') ?></td>
                            <td class="text-center">
                                <button class="btn btn-info btn-sm rounded-circle" onclick="verDetalle(<?= $p['cobro_id'] ?>)">
                                    <i class="fas fa-search"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<script>
$(document).ready(function() {
    $('.datatable').DataTable({
        "language": { "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json" },
        "order": [[ 0, "desc" ]],
        "dom": 'Bfrtip',
        "buttons": ["copy","excel","pdf","print"]
    });
});

function verDetalle(id){
    window.location.href = `./?seccion=cobros_view&id=${id}`;
}
</script>