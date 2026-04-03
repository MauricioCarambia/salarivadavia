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
    d.nombre AS destino,
    d.tipo AS tipo_destino,
    SUM(cr.monto) AS monto
FROM cobros c
JOIN cobros_reparto cr ON c.id = cr.cobro_id
JOIN destinos_reparto d ON d.nombre = cr.destino
LEFT JOIN pacientes pa ON c.paciente_id = pa.id
WHERE c.profesional_id = :id
  AND c.estado = 'activo'
  AND c.fecha BETWEEN :inicio AND :fin
GROUP BY c.id, d.nombre, d.tipo
";

$stmtPagos = $pdo->prepare($sql);
$stmtPagos->execute([':id' => $id, ':inicio' => $inicioDia, ':fin' => $finDia]);

$rows = $stmtPagos->fetchAll(PDO::FETCH_ASSOC);

$totalesDestinos = [];
$pagos = [];
$tiposDestinos = [];

foreach ($rows as $r) {

    $idCobro = $r['cobro_id'];
    $destino = $r['destino'];
    $monto = (float)$r['monto'];
    $tipo = $r['tipo_destino'];

    if (!isset($pagos[$idCobro])) {
        $pagos[$idCobro] = [
            'fecha' => $r['fecha'],
            'paciente' => $r['paciente_ape'] . ' ' . $r['paciente_nom'],
            'destinos' => [],
            'total' => 0
        ];
    }

    $pagos[$idCobro]['destinos'][$destino] = [
        'monto' => $monto,
        'tipo' => $tipo
    ];

    $pagos[$idCobro]['total'] += $monto;

    if (!isset($totalesDestinos[$destino])) {
        $totalesDestinos[$destino] = 0;
    }
    $totalesDestinos[$destino] += $monto;

    // 🔥 CLAVE
    $tiposDestinos[$destino] = $tipo;
}

ksort($totalesDestinos);

$totalFacturado = array_sum(array_column($pagos, 'total'));
$totalProfesional = $totalesDestinos['profesional'] ?? 0;


$totalGanancias = 0;

foreach ($totalesDestinos as $dest => $monto) {
    if (($tiposDestinos[$dest] ?? 'egreso') === 'ingreso') {
        $totalGanancias += $monto;
    } else {
        $totalGanancias -= $monto;
    }
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

                <div class="row">

                    <!-- Total Facturado -->
                    <div class="col-lg-3 col-6">
                        <div class="small-box bg-info">
                            <div class="inner">
                                <h3>$<?= number_format($totalFacturado, 0, ',', '.') ?></h3>
                                <p>Total Facturado</p>
                            </div>
                            <div class="icon">
                                <i class="fas fa-dollar-sign"></i>
                            </div>
                        </div>
                    </div>

                    <!-- Profesional -->
                    <div class="col-lg-3 col-6">
                        <div class="small-box bg-primary">
                            <div class="inner">
                                <h3>$<?= number_format($totalesDestinos['profesional'] ?? 0, 0, ',', '.') ?></h3>
                                <p>Pago Profesional</p>
                            </div>
                            <div class="icon">
                                <i class="fas fa-user-md"></i>
                            </div>
                        </div>
                    </div>

                    <!-- Clínica -->
                    <div class="col-lg-3 col-6">
                        <div class="small-box bg-success">
                            <div class="inner">
                                <h3>$<?= number_format($totalGanancias, 0, ',', '.') ?></h3>
                                <p>Resultado Neto</p>
                            </div>
                            <div class="icon">
                                <i class="fas fa-hospital"></i>
                            </div>
                        </div>
                    </div>

                    <!-- Otros destinos dinámicos -->
                    <?php foreach ($totalesDestinos as $dest => $monto):
                        if (in_array($dest, ['profesional', 'clinica'])) continue;
                    ?>
                        <div class="col-lg-3 col-6">
                            <div class="small-box bg-warning">
                                <div class="inner">
                                    <h3>$<?= number_format($monto, 0, ',', '.') ?></h3>
                                    <p><?= ucfirst($dest) ?></p>
                                </div>
                                <div class="icon">
                                    <i class="fas fa-layer-group"></i>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>

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
                            <th>Total</th>

                            <?php foreach ($totalesDestinos as $dest => $v): ?>
                                <th><?= ucfirst($dest) ?></th>
                            <?php endforeach; ?>

                            <th>Ganancia</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pagos as $id => $p):

                            $gananciaFila = 0;

                            foreach ($p['destinos'] as $dest => $info) {

                                if ($info['tipo'] === 'ingreso') {
                                    $gananciaFila += $info['monto'];
                                } else {
                                    $gananciaFila -= $info['monto'];
                                }
                            }

                        ?>
                            <tr>
                                <td class="text-center"><?= date('d/m/Y H:i', strtotime($p['fecha'])) ?></td>
                                <td><?= htmlspecialchars($p['paciente']) ?></td>

                                <td class="text-right">
                                    $ <?= number_format($p['total'], 2, ',', '.') ?>
                                </td>

                                <?php foreach ($totalesDestinos as $dest => $v): ?>
                                    <td class="text-right <?= $dest == 'profesional' ? 'text-primary font-weight-bold' : '' ?>">
                                        $ <?= number_format($p['destinos'][$dest]['monto'] ?? 0, 2, ',', '.') ?>
                                    </td>
                                <?php endforeach; ?>

                                <!-- ✅ GANANCIA CORRECTA -->
                                <td class="text-right text-success font-weight-bold">
                                    $ <?= number_format($gananciaFila, 2, ',', '.') ?>
                                </td>

                                <td class="text-center">
                                    <button class="btn btn-danger btn-sm rounded-circle" onclick="eliminarCobro(<?= $id ?>)">
                                        <i class="fas fa-trash"></i>
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
        $('.datatable').each(function() {
            initDataTable($(this));
        });
    });


    function eliminarCobro(id) {

        Swal.fire({
            title: '¿Anular cobro?',
            text: "Esta acción generará un egreso en caja y no se puede deshacer",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Sí, anular',
            cancelButtonText: 'Cancelar'
        }).then((result) => {

            if (result.isConfirmed) {

                $.ajax({
                    url: 'ajax/eliminar_cobro.php',
                    method: 'POST',
                    data: {
                        id: id
                    },
                    dataType: 'json',

                    beforeSend: function() {
                        Swal.fire({
                            title: 'Procesando...',
                            text: 'Anulando cobro',
                            allowOutsideClick: false,
                            didOpen: () => {
                                Swal.showLoading();
                            }
                        });
                    },

                    success: function(resp) {

                        if (resp.success) {

                            Swal.fire({
                                icon: 'success',
                                title: 'Cobro anulado',
                                text: 'Se registró correctamente en caja',
                                timer: 1800,
                                showConfirmButton: false
                            }).then(() => {
                                location.reload();
                            });

                        } else {

                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: resp.message
                            });

                        }
                    },

                    error: function() {

                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'No se pudo conectar con el servidor'
                        });

                    }
                });
            }
        });
    }
</script>