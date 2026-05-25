<?php
date_default_timezone_set('America/Argentina/Buenos_Aires');
require_once 'inc/db.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

$desde = $_GET['desde'] ?? date('Y-m-d');
$hasta = $_GET['hasta'] ?? date('Y-m-d');
$caja_id = $_GET['caja_id'] ?? '';
$turno = $_GET['turno'] ?? '';
$empleado_id = $_GET['empleado_id'] ?? '';

$desdeSQL = $desde . " 00:00:00";
$hastaSQL = $hasta . " 23:59:59";

/* ==============================
    👨‍⚕️ PROFESIONAL
============================== */
$stmt = $pdo->prepare("
    SELECT *, CONCAT(apellido,' ',nombre) AS nombre_completo
    FROM profesionales
    WHERE id = :id
");
$stmt->execute([':id' => $id]);
$prof = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$prof) {
    die('<div class="alert alert-danger">Profesional no encontrado</div>');
}

/* ==============================
    🔎 WHERE
============================== */
$where = "
WHERE c.profesional_id = :id
AND c.fecha BETWEEN :inicio AND :fin
";

$params = [
    ':id' => $id,
    ':inicio' => $desdeSQL,
    ':fin' => $hastaSQL
];

if (!empty($caja_id)) {
    $where .= " AND cs.caja_id = :caja_id";
    $params[':caja_id'] = $caja_id;
}

if (!empty($turno)) {
    $where .= " AND cs.turno = :turno";
    $params[':turno'] = $turno;
}

if (!empty($empleado_id)) {
    $where .= " AND cs.usuario_id = :empleado_id";
    $params[':empleado_id'] = $empleado_id;
}

/* ==============================
    📊 QUERY PRINCIPAL
============================== */
$sql = "
SELECT 
    c.id AS cobro_id,
    c.fecha,
    c.numero_completo,
    c.estado,
    c.medio_pago,
    c.transferencia_tipo,

    pa.nombre AS paciente_nom,
    pa.apellido AS paciente_ape,

    d.nombre AS destino_nombre,
    d.categoria,
    d.tipo AS tipo_destino,

    cr.monto

FROM cobros c
JOIN cobros_reparto cr ON c.id = cr.cobro_id
JOIN destinos_reparto d ON d.id = cr.destino_id
LEFT JOIN pacientes pa ON c.paciente_id = pa.id
LEFT JOIN caja_sesion cs ON cs.id = c.caja_sesion_id

$where
ORDER BY c.fecha DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ==============================
    📦 PROCESAMIENTO
============================== */
$pagos = [];
$totalesDestinos = [];
$tiposDestinos = [];
$categoriasDestinos = [];

$totalEfectivo = 0;
$totalTransferencia = 0;

foreach ($rows as $r) {

    $idCobro = $r['cobro_id'];
    $destino = $r['destino_nombre'];
    $monto   = (float)$r['monto'];
    $tipo    = $r['tipo_destino'];

    $categoriasDestinos[$destino] = $r['categoria'];
    $tiposDestinos[$destino] = $tipo;

    if (!isset($pagos[$idCobro])) {
        $pagos[$idCobro] = [
            'fecha' => $r['fecha'],
            'numero_completo' => $r['numero_completo'],
            'paciente' => $r['paciente_ape'] . ' ' . $r['paciente_nom'],
            'estado' => $r['estado'],
            'medio_pago' => $r['medio_pago'],
            'transferencia_tipo' => $r['transferencia_tipo'],
            'destinos' => [],
            'total_cobro' => 0
        ];
    }

    $pagos[$idCobro]['destinos'][$destino] = [
        'monto' => $monto,
        'tipo'  => $tipo
    ];

    if ($r['estado'] !== 'anulado') {

        $pagos[$idCobro]['total_cobro'] += $monto;

        if (!isset($totalesDestinos[$destino])) {
            $totalesDestinos[$destino] = 0;
        }

        $totalesDestinos[$destino] += $monto;

        if (strtolower($r['medio_pago']) === 'efectivo') {
            $totalEfectivo += $monto;
        } else {
            $totalTransferencia += $monto;
        }
    }
}
// convertir a array indexado
$pagos = array_values($pagos);

// ordenar por fecha y hora DESC
usort($pagos, function ($a, $b) {
    return strtotime($b['fecha']) <=> strtotime($a['fecha']);
});
/* ==============================
    📊 TOTALES
============================== */
$totalFacturado = array_sum(array_column($pagos, 'total_cobro'));

$totalPagoProfesional = 0;
$totalGananciaClinica = 0;

foreach ($totalesDestinos as $dest => $monto) {

    $tipo = $tiposDestinos[$dest] ?? '';
    $categoria = $categoriasDestinos[$dest] ?? '';

    if ($tipo === 'egreso') {
        $totalPagoProfesional += $monto;
    }

    if ($tipo === 'ingreso' && $categoria !== 'fondo') {
        $totalGananciaClinica += $monto;
    }
}

ksort($totalesDestinos);
?>

<div class="content">
    <div class="container-fluid">
        <div class="content">
            <div class="container-fluid">
                <div class="card card-outline card-info mb-3 p-3">
                    <form method="GET" class="row">
                        <input type="hidden" name="seccion" value="<?= $_GET['seccion'] ?? '' ?>">
                        <input type="hidden" name="id" value="<?= $id ?>">

                        <div class="col-md-2">
                            <label>Desde</label>
                            <input type="date" name="desde" value="<?= $desde ?>" class="form-control">
                        </div>

                        <div class="col-md-2">
                            <label>Hasta</label>
                            <input type="date" name="hasta" value="<?= $hasta ?>" class="form-control">
                        </div>

                        <div class="col-md-2">
                            <label>Caja</label>
                            <select name="caja_id" class="form-control">
                                <option value="">Todas</option>
                                <?php foreach ($pdo->query("SELECT id, nombre FROM cajas")->fetchAll() as $c): ?>
                                    <option value="<?= $c['id'] ?>" <?= $caja_id == $c['id'] ? 'selected' : '' ?>>
                                        <?= $c['nombre'] ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-2">
                            <label>Turno</label>
                            <select name="turno" class="form-control">
                                <option value="">Todos</option>
                                <option value="mañana" <?= $turno == 'mañana' ? 'selected' : '' ?>>Mañana</option>
                                <option value="tarde" <?= $turno == 'tarde' ? 'selected' : '' ?>>Tarde</option>
                            </select>
                        </div>

                        <div class="col-md-2">
                            <label>Empleado</label>
                            <select name="empleado_id" class="form-control">
                                <option value="">Todos</option>
                                <?php foreach ($pdo->query("SELECT id, nombre FROM empleados")->fetchAll() as $e): ?>
                                    <option value="<?= $e['id'] ?>" <?= $empleado_id == $e['id'] ? 'selected' : '' ?>>
                                        <?= $e['nombre'] ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-1 d-flex align-items-end">
                            <button class="btn btn-primary w-100">Filtrar</button>
                        </div>

                        <div class="col-md-1 d-flex align-items-end">
                            <a href="?seccion=<?= $_GET['seccion'] ?>&id=<?= $id ?>" class="btn btn-secondary w-100">
                                Limpiar
                            </a>
                        </div>
                    </form>
                </div>
                <!-- ==============================
    📊 CARDS
============================== -->
                <div class="row mb-3">

                    <div class="col-md-4">
                        <div class="small-box bg-info">
                            <div class="inner">
                                <h3>$<?= number_format($totalFacturado, 0, ',', '.') ?></h3>
                                <p>Total Facturado</p>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="small-box bg-success">
                            <div class="inner">
                                <h3>$<?= number_format($totalEfectivo, 0, ',', '.') ?></h3>
                                <p>Efectivo</p>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="small-box bg-primary">
                            <div class="inner">
                                <h3>$<?= number_format($totalTransferencia, 0, ',', '.') ?></h3>
                                <p>Transferencias</p>
                            </div>
                        </div>
                    </div>

                </div>


            </div>
        </div>

        <div class="card card-outline card-primary">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-list"></i> Detalle de Cobros del Día</h3>
            </div>
            <div class="card-body table-responsive">
                <table class="table table-striped datatable text-center">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Comprobante</th>
                            <th>Paciente</th>
                            <th>Medio</th>
                            <th>Total</th>

                            <?php foreach ($totalesDestinos as $dest => $v): ?>
                                <th><?= ucfirst($dest) ?></th>
                            <?php endforeach; ?>

                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($pagos as $p): ?>
                            <tr class="<?= ($p['estado'] === 'anulado') ? 'table-danger' : '' ?>">

                                <td><?= date('d/m/Y H:i', strtotime($p['fecha'])) ?></td>
                                <td><?= $p['numero_completo'] ?></td>
                                <td><?= $p['paciente'] ?></td>

                                <td>
                                    <?php if ($p['medio_pago'] === 'efectivo'): ?>
                                        <span class="badge badge-success">Efectivo</span>
                                    <?php else: ?>
                                        <span class="badge badge-primary">
                                            Transferencia → <?= ucfirst($p['transferencia_tipo'] ?? 'N/A') ?>
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <?= ($p['estado'] === 'anulado') ? '-' : '' ?>
                                    $<?= number_format($p['total_cobro'], 2, ',', '.') ?>
                                </td>

                                <?php foreach ($totalesDestinos as $dest => $v): ?>
                                    <td>
                                        $<?= number_format($p['destinos'][$dest]['monto'] ?? 0, 2, ',', '.') ?>
                                    </td>
                                <?php endforeach; ?>

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
            initDataTable($(this), {
                order: [
                    [0, "desc"]
                ], // 👈 AHORA SÍ FUNCIONA
                pageLength: 10
            });
        });

    });

    function eliminarCobro(id) {
        Swal.fire({
            title: '¿Anular cobro?',
            text: "Se generará un egreso en caja automáticamente",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
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
                    success: function(resp) {
                        if (resp.success) {
                            Swal.fire('Anulado', 'El cobro ha sido anulado', 'success')
                                .then(() => location.reload());
                        } else {
                            Swal.fire('Error', resp.message, 'error');
                        }
                    }
                });
            }
        });
    }
</script>