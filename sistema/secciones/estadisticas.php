<?php
require_once __DIR__ . '/../inc/db.php';

date_default_timezone_set('America/Argentina/Buenos_Aires');

$estadisticas = $_POST['estadisticas'] ?? '';

$empleados = [];
$profesionales = [];

$empleadosLabels = $dataTotalTurnos = $dataSobreturnos = $dataTotales = [];
$profLabels = $profTurnos = $profAsistencias = [];

/* ============================
   EMPLEADOS (OPTIMIZADO SQL)
============================ */
if ($estadisticas === 'empleados') {

    $sql = "
        SELECT 
            e.usuario,
            COUNT(t.Id) AS total_turnos,
            SUM(CASE WHEN t.sobreturno = 1 THEN 1 ELSE 0 END) AS sobreturnos,
            COUNT(t.Id) AS total_general
        FROM empleados e
        LEFT JOIN turnos t 
            ON e.Id = t.usuario_Id
            AND t.fecha_actual >= DATE_SUB(NOW(), INTERVAL 2 MONTH)
        GROUP BY e.Id
        ORDER BY e.usuario ASC
    ";

    $empleados = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

    foreach ($empleados as $r) {
        $empleadosLabels[] = $r['usuario'];
        $dataTotalTurnos[] = (int)$r['total_turnos'] - (int)$r['sobreturnos'];
        $dataSobreturnos[] = (int)$r['sobreturnos'];
        $dataTotales[] = (int)$r['total_general'];
    }
}

/* ============================
   PROFESIONALES
============================ */
if ($estadisticas === 'profesional') {

    $sql = "
        SELECT 
            CONCAT(p.apellido,' ',p.nombre) AS nombre,
            COUNT(t.Id) AS turnos,
            SUM(CASE WHEN t.asistio = 1 THEN 1 ELSE 0 END) AS asistencias
        FROM profesionales p
        LEFT JOIN turnos t ON p.Id = t.profesional_id
        GROUP BY p.Id
        ORDER BY p.apellido
    ";

    $profesionales = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

    foreach ($profesionales as $r) {
        $profLabels[] = $r['nombre'];
        $profTurnos[] = (int)$r['turnos'];
        $profAsistencias[] = (int)$r['asistencias'];
    }
}
?>

<div class="col-md-12">
    <div class="card card-outline card-info">
        <div class="card-header">
            <h3 class="card-title">Estadísticas</h3>
        </div>
        <div class="card-body">
            <form method="POST">
                <div class="row">
                    <div class="col-md-4">
                        <select name="estadisticas" class="form-control" onchange="this.form.submit()">
                            <option value="">Seleccionar</option>
                            <option value="empleados" <?= $estadisticas == 'empleados' ? 'selected' : '' ?>>Empleados</option>
                            <option value="profesional" <?= $estadisticas == 'profesional' ? 'selected' : '' ?>>Profesionales</option>
                        </select>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- EMPLEADOS -->
    <?php if ($estadisticas === 'empleados'): ?>
        <div class="card card-outline card-info">
            <div class="card-header"><strong>Estadísticas de Empleados</strong></div>
            <div class="card-body table-responsive">

                <table class="table table-bordered table-hover tabla">
                    <thead>
                        <tr>
                            <th>Empleado</th>
                            <th>Turnos</th>
                            <th>Sobreturnos</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($empleados as $r):
                            $turnos = (int)$r['total_turnos'] - (int)$r['sobreturnos'];
                            $sobreturnos = (int)$r['sobreturnos'];
                            $total = (int)$r['total_general'];
                        ?>
                            <tr>
                                <td><?= htmlspecialchars($r['usuario']) ?></td>
                                <td><?= $turnos ?></td>
                                <td><?= $sobreturnos ?></td>
                                <td><strong><?= $total ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <canvas id="chartEmpleados" height="100"></canvas>

            </div>
        </div>
    <?php endif; ?>

    <!-- PROFESIONALES -->
    <?php if ($estadisticas === 'profesional'): ?>
        <div class="card card-outline card-success">
            <div class="card-header"><strong>Estadísticas de Profesionales</strong></div>
            <div class="card-body">

                <canvas id="chartProfesionales" height="120"></canvas>

            </div>
        </div>
    <?php endif; ?>

</div>

<!-- LIBS -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

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
                        label: 'Total',
                        data: <?= json_encode($dataTotales) ?>
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
                    }
                ]
            }
        });
    <?php endif; ?>
</script>