<?php
require_once __DIR__ . '/../inc/db.php';

date_default_timezone_set('America/Argentina/Buenos_Aires');

if (empty($_SESSION['login'])) {
    header("Location: login.php");
    exit;
}

if (empty($_SESSION['es_admin']) && !in_array('estadisticas', $_SESSION['accesos'] ?? [])) {
    die('<div class="alert alert-danger">No tenés permisos para acceder a esta sección</div>');
}

function fmtMoney($n): string
{
    return '$' . number_format((float)$n, 2, ',', '.');
}

$estadisticas = $_POST['estadisticas'] ?? 'general';
$hasta = $_POST['hasta'] ?? date('Y-m-d');
$desde = $_POST['desde'] ?? date('Y-m-01');

if ($desde > $hasta) {
    [$desde, $hasta] = [$hasta, $desde];
}

$empleadosLabels = $dataTotalTurnos = $dataSobreturnos = $dataCobros = $dataFacturado = [];
$profLabels = $profTurnos = $profAsistencias = $profAusencias = $profFacturado = [];

/* ============================
   EMPLEADOS
============================ */
$empleados = [];

if ($estadisticas === 'empleados') {

    $sql = "
        SELECT
            e.usuario,
            e.nombre,
            COALESCE(tt.turnos, 0)      AS turnos,
            COALESCE(tt.sobreturnos, 0) AS sobreturnos,
            COALESCE(cc.cobros, 0)      AS cobros,
            COALESCE(cc.facturado, 0)   AS facturado
        FROM empleados e
        LEFT JOIN (
            SELECT usuario_iD,
                COUNT(*) AS turnos,
                SUM(sobreturno = 1) AS sobreturnos
            FROM turnos
            WHERE DATE(fecha_actual) BETWEEN ? AND ?
            GROUP BY usuario_iD
        ) tt ON tt.usuario_iD = e.Id
        LEFT JOIN (
            SELECT usuario_id,
                COUNT(*) AS cobros,
                SUM(CASE WHEN tipo = 'ingreso' AND estado = 'activo' THEN total ELSE 0 END) AS facturado
            FROM cobros
            WHERE DATE(fecha) BETWEEN ? AND ?
            GROUP BY usuario_id
        ) cc ON cc.usuario_id = e.Id
        ORDER BY e.usuario ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$desde, $hasta, $desde, $hasta]);
    $empleados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($empleados as $r) {
        $empleadosLabels[] = $r['usuario'];
        $dataTotalTurnos[] = (int)$r['turnos'] - (int)$r['sobreturnos'];
        $dataSobreturnos[] = (int)$r['sobreturnos'];
        $dataCobros[] = (int)$r['cobros'];
        $dataFacturado[] = round((float)$r['facturado'], 2);
    }
}

/* ============================
   PROFESIONALES
============================ */
$profesionales = [];

if ($estadisticas === 'profesional') {

    $sql = "
        SELECT
            p.Id,
            CONCAT(p.apellido, ' ', p.nombre) AS nombre,
            COALESCE(tt.turnos, 0)     AS turnos,
            COALESCE(tt.asistieron, 0) AS asistieron,
            COALESCE(tt.ausentes, 0)   AS ausentes,
            COALESCE(tt.atendidos, 0)  AS atendidos,
            COALESCE(cc.facturado, 0)  AS facturado
        FROM profesionales p
        LEFT JOIN (
            SELECT profesional_id,
                COUNT(*) AS turnos,
                SUM(asistio = 1) AS asistieron,
                SUM(asistio = 0) AS ausentes,
                SUM(atendido = 1) AS atendidos
            FROM turnos
            WHERE DATE(fecha) BETWEEN ? AND ?
            GROUP BY profesional_id
        ) tt ON tt.profesional_id = p.Id
        LEFT JOIN (
            SELECT profesional_id,
                SUM(CASE WHEN tipo = 'ingreso' AND estado = 'activo' THEN total ELSE 0 END) AS facturado
            FROM cobros
            WHERE DATE(fecha) BETWEEN ? AND ?
            GROUP BY profesional_id
        ) cc ON cc.profesional_id = p.Id
        ORDER BY p.apellido ASC, p.nombre ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$desde, $hasta, $desde, $hasta]);
    $profesionales = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($profesionales as $r) {
        $profLabels[] = $r['nombre'];
        $profTurnos[] = (int)$r['turnos'];
        $profAsistencias[] = (int)$r['asistieron'];
        $profAusencias[] = (int)$r['ausentes'];
        $profFacturado[] = round((float)$r['facturado'], 2);
    }
}

/* ============================
   GENERAL (RESUMEN)
============================ */
$kpis = [
    'turnos' => 0,
    'asistieron' => 0,
    'ausentes' => 0,
    'pendientes' => 0,
    'ingresos' => 0,
    'egresos' => 0,
    'cant_ingresos' => 0,
];
$mediosPago = [];
$topPracticas = [];
$topObrasSociales = [];
$mesesLabels = [];
$mesesIngresos = [];
$mesesEgresos = [];
$mesesTurnos = [];

if ($estadisticas === 'general') {

    // ---- KPIs de turnos ----
    $stmt = $pdo->prepare("
        SELECT
            COUNT(*) AS turnos,
            SUM(asistio = 1) AS asistieron,
            SUM(asistio = 0) AS ausentes,
            SUM(asistio IS NULL) AS pendientes
        FROM turnos
        WHERE DATE(fecha) BETWEEN ? AND ?
    ");
    $stmt->execute([$desde, $hasta]);
    $r = $stmt->fetch(PDO::FETCH_ASSOC);

    $kpis['turnos'] = (int)$r['turnos'];
    $kpis['asistieron'] = (int)$r['asistieron'];
    $kpis['ausentes'] = (int)$r['ausentes'];
    $kpis['pendientes'] = (int)$r['pendientes'];

    // ---- KPIs financieros ----
    $stmt = $pdo->prepare("
        SELECT
            SUM(CASE WHEN tipo = 'ingreso' AND estado = 'activo' THEN total ELSE 0 END) AS ingresos,
            SUM(CASE WHEN tipo = 'egreso' AND estado = 'activo' THEN total ELSE 0 END) AS egresos,
            SUM(CASE WHEN tipo = 'ingreso' AND estado = 'activo' THEN 1 ELSE 0 END) AS cant_ingresos
        FROM cobros
        WHERE DATE(fecha) BETWEEN ? AND ?
    ");
    $stmt->execute([$desde, $hasta]);
    $r = $stmt->fetch(PDO::FETCH_ASSOC);

    $kpis['ingresos'] = (float)$r['ingresos'];
    $kpis['egresos'] = (float)$r['egresos'];
    $kpis['cant_ingresos'] = (int)$r['cant_ingresos'];

    // ---- Medios de pago ----
    $stmt = $pdo->prepare("
        SELECT
            COALESCE(medio_pago, 'efectivo') AS medio_pago,
            COUNT(*) AS cantidad,
            SUM(total) AS total
        FROM cobros
        WHERE tipo = 'ingreso' AND estado = 'activo' AND DATE(fecha) BETWEEN ? AND ?
        GROUP BY medio_pago
    ");
    $stmt->execute([$desde, $hasta]);
    $mediosPago = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ---- Top 5 prácticas ----
    $stmt = $pdo->prepare("
        SELECT
            cd.nombre,
            COUNT(*) AS cantidad,
            SUM(cd.precio) AS total
        FROM cobros_detalle cd
        INNER JOIN cobros c ON c.id = cd.cobro_id
        WHERE c.tipo = 'ingreso' AND c.estado = 'activo' AND DATE(c.fecha) BETWEEN ? AND ?
        GROUP BY cd.nombre
        ORDER BY cantidad DESC
        LIMIT 5
    ");
    $stmt->execute([$desde, $hasta]);
    $topPracticas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ---- Top 5 obras sociales (por turnos) ----
    $stmt = $pdo->prepare("
        SELECT
            COALESCE(os.obra_social, 'Sin obra social') AS obra_social,
            COUNT(*) AS turnos
        FROM turnos t
        INNER JOIN pacientes p ON p.Id = t.paciente_id
        LEFT JOIN obras_sociales os ON os.Id = p.obra_social_id
        WHERE DATE(t.fecha) BETWEEN ? AND ?
        GROUP BY obra_social
        ORDER BY turnos DESC
        LIMIT 5
    ");
    $stmt->execute([$desde, $hasta]);
    $topObrasSociales = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ---- Evolución últimos 6 meses (ingresos/egresos/turnos) ----
    $meses = [];
    for ($i = 5; $i >= 0; $i--) {
        $m = date('Y-m', strtotime("$hasta -$i months"));
        $meses[$m] = ['ingresos' => 0, 'egresos' => 0, 'turnos' => 0];
    }
    $inicio6 = array_key_first($meses) . '-01';

    $stmt = $pdo->prepare("
        SELECT
            DATE_FORMAT(fecha, '%Y-%m') AS mes,
            SUM(CASE WHEN tipo = 'ingreso' AND estado = 'activo' THEN total ELSE 0 END) AS ingresos,
            SUM(CASE WHEN tipo = 'egreso' AND estado = 'activo' THEN total ELSE 0 END) AS egresos
        FROM cobros
        WHERE fecha >= ? AND fecha <= ?
        GROUP BY mes
    ");
    $stmt->execute([$inicio6, $hasta]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        if (isset($meses[$r['mes']])) {
            $meses[$r['mes']]['ingresos'] = (float)$r['ingresos'];
            $meses[$r['mes']]['egresos'] = (float)$r['egresos'];
        }
    }

    $stmt = $pdo->prepare("
        SELECT DATE_FORMAT(fecha, '%Y-%m') AS mes, COUNT(*) AS turnos
        FROM turnos
        WHERE fecha >= ? AND fecha <= ?
        GROUP BY mes
    ");
    $stmt->execute([$inicio6, $hasta]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        if (isset($meses[$r['mes']])) {
            $meses[$r['mes']]['turnos'] = (int)$r['turnos'];
        }
    }

    foreach ($meses as $mes => $datos) {
        $mesesLabels[] = $mes;
        $mesesIngresos[] = $datos['ingresos'];
        $mesesEgresos[] = $datos['egresos'];
        $mesesTurnos[] = $datos['turnos'];
    }
}

$asistenciaTotal = $kpis['asistieron'] + $kpis['ausentes'];
$porcAsistencia = $asistenciaTotal > 0 ? round($kpis['asistieron'] / $asistenciaTotal * 100, 1) : 0;
$ticketPromedio = $kpis['cant_ingresos'] > 0 ? $kpis['ingresos'] / $kpis['cant_ingresos'] : 0;
?>

<div class="col-md-12">
    <div class="card card-outline card-info">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-chart-bar"></i> Estadísticas</h3>
        </div>
        <div class="card-body">
            <form method="POST">
                <div class="row align-items-end">
                    <div class="col-md-3 form-group">
                        <label>Vista</label>
                        <select name="estadisticas" class="form-control">
                            <option value="general" <?= $estadisticas == 'general' ? 'selected' : '' ?>>Resumen general</option>
                            <option value="empleados" <?= $estadisticas == 'empleados' ? 'selected' : '' ?>>Empleados</option>
                            <option value="profesional" <?= $estadisticas == 'profesional' ? 'selected' : '' ?>>Profesionales</option>
                        </select>
                    </div>
                    <div class="col-md-3 form-group">
                        <label>Desde</label>
                        <input type="date" name="desde" class="form-control" value="<?= htmlspecialchars($desde) ?>">
                    </div>
                    <div class="col-md-3 form-group">
                        <label>Hasta</label>
                        <input type="date" name="hasta" class="form-control" value="<?= htmlspecialchars($hasta) ?>">
                    </div>
                    <div class="col-md-3 form-group">
                        <button type="submit" class="btn btn-primary btn-block">
                            <i class="fas fa-filter"></i> Filtrar
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- ===================== RESUMEN GENERAL ===================== -->
    <?php if ($estadisticas === 'general'): ?>

        <div class="row">
            <div class="col-md-2">
                <div class="info-box">
                    <span class="info-box-icon bg-info"><i class="fas fa-calendar-check"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Turnos</span>
                        <span class="info-box-number"><?= $kpis['turnos'] ?></span>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="info-box">
                    <span class="info-box-icon bg-success"><i class="fas fa-user-check"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">% Asistencia</span>
                        <span class="info-box-number"><?= $porcAsistencia ?>%</span>
                        <span class="progress-description">
                            <?= $kpis['asistieron'] ?> asistieron / <?= $kpis['ausentes'] ?> ausentes
                        </span>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="info-box">
                    <span class="info-box-icon bg-primary"><i class="fas fa-arrow-down"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Ingresos</span>
                        <span class="info-box-number"><?= fmtMoney($kpis['ingresos']) ?></span>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="info-box">
                    <span class="info-box-icon bg-danger"><i class="fas fa-arrow-up"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Egresos</span>
                        <span class="info-box-number"><?= fmtMoney($kpis['egresos']) ?></span>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="info-box">
                    <span class="info-box-icon bg-secondary"><i class="fas fa-balance-scale"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Resultado</span>
                        <span class="info-box-number"><?= fmtMoney($kpis['ingresos'] - $kpis['egresos']) ?></span>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="info-box">
                    <span class="info-box-icon bg-warning"><i class="fas fa-receipt"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Ticket promedio</span>
                        <span class="info-box-number"><?= fmtMoney($ticketPromedio) ?></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="card card-outline card-primary">
                    <div class="card-header"><strong>Ingresos vs Egresos (últimos 6 meses)</strong></div>
                    <div class="card-body">
                        <canvas id="chartFinanciero" height="200"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card card-outline card-info">
                    <div class="card-header"><strong>Turnos por mes (últimos 6 meses)</strong></div>
                    <div class="card-body">
                        <canvas id="chartTurnosMes" height="200"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-4">
                <div class="card card-outline card-success">
                    <div class="card-header"><strong>Medios de pago</strong></div>
                    <div class="card-body">
                        <canvas id="chartMediosPago" height="200"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card card-outline card-warning">
                    <div class="card-header"><strong>Prácticas más facturadas</strong></div>
                    <div class="card-body table-responsive">
                        <table class="table table-sm table-bordered">
                            <thead>
                                <tr>
                                    <th>Práctica</th>
                                    <th>Cant.</th>
                                    <th>Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($topPracticas as $tp): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($tp['nombre']) ?></td>
                                        <td><?= $tp['cantidad'] ?></td>
                                        <td><?= fmtMoney($tp['total']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($topPracticas)): ?>
                                    <tr>
                                        <td colspan="3" class="text-center text-muted">Sin datos</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card card-outline card-secondary">
                    <div class="card-header"><strong>Top obras sociales (por turnos)</strong></div>
                    <div class="card-body table-responsive">
                        <table class="table table-sm table-bordered">
                            <thead>
                                <tr>
                                    <th>Obra social</th>
                                    <th>Turnos</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($topObrasSociales as $os): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($os['obra_social']) ?></td>
                                        <td><?= $os['turnos'] ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($topObrasSociales)): ?>
                                    <tr>
                                        <td colspan="2" class="text-center text-muted">Sin datos</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    <?php endif; ?>

    <!-- ===================== EMPLEADOS ===================== -->
    <?php if ($estadisticas === 'empleados'): ?>
        <div class="card card-outline card-info">
            <div class="card-header"><strong>Estadísticas de Empleados</strong></div>
            <div class="card-body table-responsive">

                <table class="table table-bordered table-hover">
                    <thead>
                        <tr>
                            <th>Empleado</th>
                            <th>Turnos dados</th>
                            <th>Sobreturnos</th>
                            <th>Cobros realizados</th>
                            <th>Total facturado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $totTurnos = $totSobre = $totCobros = 0;
                        $totFacturado = 0.0;
                        foreach ($empleados as $r):
                            $turnos = (int)$r['turnos'] - (int)$r['sobreturnos'];
                            $sobreturnos = (int)$r['sobreturnos'];
                            $cobros = (int)$r['cobros'];
                            $facturado = (float)$r['facturado'];

                            $totTurnos += $turnos;
                            $totSobre += $sobreturnos;
                            $totCobros += $cobros;
                            $totFacturado += $facturado;
                        ?>
                            <tr>
                                <td><?= htmlspecialchars($r['nombre'] ?: $r['usuario']) ?></td>
                                <td><?= $turnos ?></td>
                                <td><?= $sobreturnos ?></td>
                                <td><?= $cobros ?></td>
                                <td><?= fmtMoney($facturado) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="font-weight-bold">
                            <td>Total</td>
                            <td><?= $totTurnos ?></td>
                            <td><?= $totSobre ?></td>
                            <td><?= $totCobros ?></td>
                            <td><?= fmtMoney($totFacturado) ?></td>
                        </tr>
                    </tfoot>
                </table>

                <canvas id="chartEmpleados" height="100"></canvas>

            </div>
        </div>
    <?php endif; ?>

    <!-- ===================== PROFESIONALES ===================== -->
    <?php if ($estadisticas === 'profesional'): ?>
        <div class="card card-outline card-success">
            <div class="card-header"><strong>Estadísticas de Profesionales</strong></div>
            <div class="card-body table-responsive">

                <table class="table table-bordered table-hover">
                    <thead>
                        <tr>
                            <th>Profesional</th>
                            <th>Turnos</th>
                            <th>Asistieron</th>
                            <th>Ausentes</th>
                            <th>% Asistencia</th>
                            <th>Atendidos</th>
                            <th>Total facturado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $totTurnos = $totAsist = $totAusent = $totAtend = 0;
                        $totFacturado = 0.0;
                        foreach ($profesionales as $r):
                            $turnos = (int)$r['turnos'];
                            $asistieron = (int)$r['asistieron'];
                            $ausentes = (int)$r['ausentes'];
                            $atendidos = (int)$r['atendidos'];
                            $facturado = (float)$r['facturado'];

                            $denom = $asistieron + $ausentes;
                            $porc = $denom > 0 ? round($asistieron / $denom * 100, 1) : 0;

                            $totTurnos += $turnos;
                            $totAsist += $asistieron;
                            $totAusent += $ausentes;
                            $totAtend += $atendidos;
                            $totFacturado += $facturado;
                        ?>
                            <tr>
                                <td><?= htmlspecialchars($r['nombre']) ?></td>
                                <td><?= $turnos ?></td>
                                <td><?= $asistieron ?></td>
                                <td><?= $ausentes ?></td>
                                <td><?= $porc ?>%</td>
                                <td><?= $atendidos ?></td>
                                <td><?= fmtMoney($facturado) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="font-weight-bold">
                            <td>Total</td>
                            <td><?= $totTurnos ?></td>
                            <td><?= $totAsist ?></td>
                            <td><?= $totAusent ?></td>
                            <td><?= ($totAsist + $totAusent) > 0 ? round($totAsist / ($totAsist + $totAusent) * 100, 1) : 0 ?>%</td>
                            <td><?= $totAtend ?></td>
                            <td><?= fmtMoney($totFacturado) ?></td>
                        </tr>
                    </tfoot>
                </table>

                <canvas id="chartProfesionales" height="120"></canvas>

            </div>
        </div>
    <?php endif; ?>

</div>

<!-- LIBS -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    <?php if ($estadisticas === 'empleados'): ?>
        new Chart(document.getElementById('chartEmpleados'), {
            type: 'bar',
            data: {
                labels: <?= json_encode($empleadosLabels) ?>,
                datasets: [{
                        label: 'Turnos',
                        data: <?= json_encode($dataTotalTurnos) ?>
                    },
                    {
                        label: 'Sobreturnos',
                        data: <?= json_encode($dataSobreturnos) ?>
                    },
                    {
                        label: 'Cobros realizados',
                        data: <?= json_encode($dataCobros) ?>
                    }
                ]
            }
        });
    <?php endif; ?>

    <?php if ($estadisticas === 'profesional'): ?>
        new Chart(document.getElementById('chartProfesionales'), {
            type: 'bar',
            data: {
                labels: <?= json_encode($profLabels) ?>,
                datasets: [{
                        label: 'Turnos',
                        data: <?= json_encode($profTurnos) ?>
                    },
                    {
                        label: 'Asistencias',
                        data: <?= json_encode($profAsistencias) ?>
                    },
                    {
                        label: 'Ausencias',
                        data: <?= json_encode($profAusencias) ?>
                    }
                ]
            }
        });
    <?php endif; ?>

    <?php if ($estadisticas === 'general'): ?>
        new Chart(document.getElementById('chartFinanciero'), {
            type: 'bar',
            data: {
                labels: <?= json_encode($mesesLabels) ?>,
                datasets: [{
                        label: 'Ingresos',
                        data: <?= json_encode($mesesIngresos) ?>,
                        backgroundColor: '#28a745'
                    },
                    {
                        label: 'Egresos',
                        data: <?= json_encode($mesesEgresos) ?>,
                        backgroundColor: '#dc3545'
                    }
                ]
            }
        });

        new Chart(document.getElementById('chartTurnosMes'), {
            type: 'line',
            data: {
                labels: <?= json_encode($mesesLabels) ?>,
                datasets: [{
                    label: 'Turnos',
                    data: <?= json_encode($mesesTurnos) ?>,
                    borderColor: '#17a2b8',
                    fill: false,
                    tension: 0.2
                }]
            }
        });

        new Chart(document.getElementById('chartMediosPago'), {
            type: 'doughnut',
            data: {
                labels: <?= json_encode(array_map(fn($m) => ucfirst($m['medio_pago']), $mediosPago)) ?>,
                datasets: [{
                    data: <?= json_encode(array_map(fn($m) => round((float)$m['total'], 2), $mediosPago)) ?>,
                    backgroundColor: ['#28a745', '#007bff', '#ffc107', '#dc3545']
                }]
            }
        });
    <?php endif; ?>
</script>
