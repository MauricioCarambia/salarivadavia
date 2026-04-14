<?php
date_default_timezone_set('America/Argentina/Buenos_Aires');
require_once 'inc/db.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$rand = random_int(1000, 9999);
$fecha = $_POST['fecha'] ?? date('Y-m-d');
$desde = $_GET['desde'] ?? date('Y-m-d');
$hasta = $_GET['hasta'] ?? date('Y-m-d');
$caja_id = $_GET['caja_id'] ?? '';
$turno = $_GET['turno'] ?? '';
$empleado_id = $_GET['empleado_id'] ?? '';

$desdeSQL = $desde . " 00:00:00";
$hastaSQL = $hasta . " 23:59:59";
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
    📊 CONSULTA SQL OPTIMIZADA (Por IDs)
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

// 🔥 FILTROS DINÁMICOS
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

$sql = "
SELECT 
    c.id AS cobro_id,
    c.fecha,
    c.numero_completo,
    c.estado,
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
";



$stmtPagos = $pdo->prepare($sql);
$stmtPagos->execute($params);
$rows = $stmtPagos->fetchAll(PDO::FETCH_ASSOC);

$totalesDestinos = []; // Sumas globales por nombre de destino
$pagos = [];           // Agrupado por ID de cobro para la tabla
$tiposDestinos = [];   // Diccionario para saber si un destino es ingreso o egreso

foreach ($rows as $r) {
    $idCobro = $r['cobro_id'];
    $destino = $r['destino_nombre'];
    $monto   = (float)$r['monto'];
    $tipo    = $r['tipo_destino'];
    $categoriasDestinos[$destino] = $r['categoria'];
    if (!isset($pagos[$idCobro])) {
        $pagos[$idCobro] = [
            'fecha'    => $r['fecha'],
            'numero_completo' => $r['numero_completo'],
            'paciente' => $r['paciente_ape'] . ' ' . $r['paciente_nom'],
            'estado'   => $r['estado'], // 🔥 ACA
            'destinos' => [],
            'total_cobro' => 0
        ];
    }

    $pagos[$idCobro]['destinos'][$destino] = [
        'monto' => $monto,
        'tipo'  => $tipo
    ];

    // 🔥 SOLO SUMAR SI NO ESTÁ ANULADO
    if ($r['estado'] !== 'anulado') {

        // Total del cobro
        $pagos[$idCobro]['total_cobro'] += $monto;

        // Totales globales
        if (!isset($totalesDestinos[$destino])) {
            $totalesDestinos[$destino] = 0;
        }

        $totalesDestinos[$destino] += $monto;
    }

    // Este sí siempre (porque define tipo del destino)
    $tiposDestinos[$destino] = $tipo;
}
foreach ($pagos as &$p) {
    if ($p['estado'] === 'anulado') {
        $p['total_cobro'] = 0;
    }
}
unset($p);
ksort($totalesDestinos);

// Cálculos para los Widgets
$totalFacturado = array_sum(array_column($pagos, 'total_cobro'));
$totalPagoProfesional = 0;
$totalGananciasClinica = 0;

foreach ($totalesDestinos as $dest => $monto) {
    // Si el destino está marcado como profesional (según tu lógica previa o nombre)
    if (strtolower($dest) == 'profesional') {
        $totalPagoProfesional += $monto;
    }
    // Sumamos a ganancia todo lo que sea tipo 'ingreso' (ej: Clinica, Materiales, etc)
    $tipo = $tiposDestinos[$dest] ?? '';
    $categoria = $categoriasDestinos[$dest] ?? '';

    // 🔥 SOLO INGRESOS Y QUE NO SEAN FONDO
    if ($tipo === 'ingreso' && $categoria !== 'fondo') {
        $totalGananciasClinica += $monto;
    }
}
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
                <div class="card card-info">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-user-md"></i> Reporte Diario: <?= htmlspecialchars($prof['nombre_completo']) ?></h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-lg-4 col-6">
                                <div class="small-box bg-info">
                                    <div class="inner">
                                        <h3>$<?= number_format($totalFacturado, 0, ',', '.') ?></h3>
                                        <p>Total Facturado</p>
                                    </div>
                                    <div class="icon"><i class="fas fa-dollar-sign"></i></div>
                                </div>
                            </div>
                            <div class="col-lg-4 col-6">
                                <div class="small-box bg-primary">
                                    <div class="inner">
                                        <h3>$<?= number_format($totalPagoProfesional, 0, ',', '.') ?></h3>
                                        <p>Pago Profesional</p>
                                    </div>
                                    <div class="icon"><i class="fas fa-user-md"></i></div>
                                </div>
                            </div>
                            <div class="col-lg-4 col-12">
                                <div class="small-box bg-success">
                                    <div class="inner">
                                        <h3>$<?= number_format($totalGananciasClinica, 0, ',', '.') ?></h3>
                                        <p>Ganancia Clínica (Total Ingresos)</p>
                                    </div>
                                    <div class="icon"><i class="fas fa-hospital"></i></div>
                                </div>
                            </div>
                        </div>

                        <h5 class="mb-3 mt-2"><i class="fas fa-chart-pie"></i> Resumen por Destino</h5>
                        <div class="row">
                            <?php foreach ($totalesDestinos as $destNom => $montoTotal):
                                // Asignamos un color según el tipo de destino
                                $esEgreso = (strtolower($destNom) == 'profesional' || $tiposDestinos[$destNom] === 'egreso');
                                $colorBadge = $esEgreso ? 'danger' : 'success';
                            ?>
                                <div class="col-md-3 col-sm-6 col-12">
                                    <div class="info-box shadow-sm">
                                        <span class="info-box-icon bg-<?= $colorBadge ?>"><i class="fas fa-tag"></i></span>
                                        <div class="info-box-content">
                                            <span class="info-box-text"><?= ucfirst($destNom) ?></span>
                                            <span class="info-box-number">
                                                $<?= number_format($montoTotal, 2, ',', '.') ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
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
                <table class="table text-center table-striped datatable">
                    <thead>
                        <tr class="text-center">
                            <th>Fecha/Hora</th>
                            <th>Comprobante</th>
                            <th>Paciente</th>
                            <th>Total</th>
                            <?php foreach ($totalesDestinos as $dest => $v): ?>
                                <th><?= ucfirst($dest) ?></th>
                            <?php endforeach; ?>
                            <th>Ganancia Fila</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pagos as $idCobro => $p):
                            $gananciaFila = 0;
                            foreach ($p['destinos'] as $destNom => $destInfo) {
                                $categoria = $categoriasDestinos[$destNom] ?? '';

                                if ($destInfo['tipo'] === 'ingreso' && $categoria !== 'fondo') {
                                    $gananciaFila += $destInfo['monto'];
                                }
                            }
                        ?>
                            <tr class="<?= ($p['estado'] === 'anulado') ? 'table-danger' : '' ?>">
                                <td class="text-center"><?= date('d/m/Y H:i', strtotime($p['fecha'])) ?></td>
                                <td class="text-center"><?= htmlspecialchars($p['numero_completo']) ?></td>
                                <td class="text-center"><?= htmlspecialchars($p['paciente']) ?></td>
                                <td class="text-center font-weight-bold 
    <?= ($p['estado'] === 'anulado') ? 'text-danger' : '' ?>">

                                    <?= ($p['estado'] === 'anulado') ? '-' : '' ?>
                                    $ <?= number_format($p['total_cobro'], 2, ',', '.') ?>

                                    <?php if ($p['estado'] === 'anulado'): ?>
                                        <span class="badge badge-danger ml-1">ANULADO</span>
                                    <?php endif; ?>
                                </td>

                                <?php foreach ($totalesDestinos as $destNom => $v):
                                    $montoCelda = $p['destinos'][$destNom]['monto'] ?? 0;
                                ?>
                                    <td class="text-center <?= strtolower($destNom) == 'profesional' ? 'text-primary' : '' ?>">
                                        $ <?= number_format($montoCelda, 2, ',', '.') ?>
                                    </td>
                                <?php endforeach; ?>

                                <td class="text-center text-success font-weight-bold">
                                    $ <?= number_format($gananciaFila, 2, ',', '.') ?>
                                </td>

                                <td class="text-center">
                                    <div class="btn-group">
                                        <button class="btn btn-danger btn-sm rounded-circle" onclick="eliminarCobro(<?= $idCobro ?>)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
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