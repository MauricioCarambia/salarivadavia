<?php
require_once __DIR__ . '/../inc/db.php'; // $pdo debe ser PDO

date_default_timezone_set('America/Argentina/Buenos_Aires');

// ============================
// OPCION SELECCIONADA
// ============================
$estadisticas = $_POST['estadisticas'] ?? '';
$empleados = [];
$profesionales = [];
$empleadosLabels = $dataTotalTurnos = $dataContadorSobreturnos = $dataUltimoMes = [];
$profLabels = $profTotalTurnos = $profAsistencias = [];
// ============================
// FECHAS EN ESPAÑOL
// ============================
$dias = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
$meses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

// ============================
// DATOS DE EMPLEADOS
// ============================
$empleadosLabels = $dataTotalTurnos = $dataContadorSobreturnos = $dataUltimoMes = [];

if ($estadisticas === 'empleados') {
    $sql = "
        SELECT e.usuario,
               COUNT(t.Id) AS total_turnos,
               SUM(CASE WHEN t.sobreturno = 1 THEN 1 ELSE 0 END) AS contador_sobreturnos
        FROM empleado e
        LEFT JOIN turnos t ON e.Id = t.usuario_Id
            AND t.fecha_actual >= DATE_SUB(NOW(), INTERVAL 2 MONTH)
            AND t.fecha_actual < NOW()
        GROUP BY e.Id
        ORDER BY e.usuario ASC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $empleados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($empleados as $r) {
        $total_turnos = (int) $r['total_turnos'] - (int) $r['contador_sobreturnos'];
        $sobreturnos = (int) $r['contador_sobreturnos'];
        $ultimo_mes = $total_turnos + $sobreturnos;

        $empleadosLabels[] = $r['usuario'];
        $dataTotalTurnos[] = $total_turnos;
        $dataContadorSobreturnos[] = $sobreturnos;
        $dataUltimoMes[] = $ultimo_mes;
    }
}

// ============================
// DATOS DE PROFESIONALES
// ============================
$profLabels = $profTotalTurnos = $profAsistencias = [];

if ($estadisticas === 'profesional') {
    $sql = "
        SELECT p.Id AS profesional_id,
               CONCAT(p.apellido,' ',p.nombre) AS profesionalNombreCompleto,
               COUNT(t.Id) AS total_turnos,
               SUM(CASE WHEN t.asistio = 1 THEN 1 ELSE 0 END) AS total_asistencias
        FROM profesionales p
        LEFT JOIN turnos t ON p.Id = t.profesional_id
        GROUP BY p.Id
        ORDER BY p.apellido ASC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $profesionales = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($profesionales as $r) {
        $profLabels[] = $r['profesionalNombreCompleto'];
        $profTotalTurnos[] = (int) $r['total_turnos'];
        $profAsistencias[] = (int) $r['total_asistencias'];
    }
}
?>

<!-- Main Wrapper -->
<div id="wrapper">

    <div class="normalheader transition animated fadeIn small-header">
        <div class="hpanel">
            <div class="panel-body">
                <h2>
                    Bienvenido a Sistema de Turnos Online
                    <div class="fecha pull-right">
                        <?php echo $dias[date('w')] . " " . date('d') . " de " . $meses[date('n') - 1] . " de " . date('Y'); ?>
                    </div>
                </h2>
            </div>
        </div>
    </div>

    <div class="content animate-panel">
        <div class="row">
            <div class="col-lg-12">
                <div class="hpanel">
                    <div class="panel-body">
                        <div class="form-group">
                            <label class="col-sm-2 control-label">Estadísticas</label>
                            <div class="col-sm-4">
                                <form action="" method="POST">
                                    <select class="form-control" id="estadisticas" name="estadisticas"
                                        onchange="mostrarOcultar()">
                                        <option value="">Seleccionar</option>
                                        <option value="empleados" <?= $estadisticas === 'empleados' ? 'selected' : '' ?>>
                                            Empleados</option>
                                        <option value="profesional" <?= $estadisticas === 'profesional' ? 'selected' : '' ?>>
                                            Profesionales</option>
                                    </select>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============================
             ESTADISTICAS DE EMPLEADOS
             ============================ -->
        <div id="contenidoOpcion1" style="display:none;">
            <div class="row">
                <div class="col-lg-4">
                    <div class="hpanel">
                        <div class="panel-heading">ESTADÍSTICAS DE EMPLEADOS</div>
                        <div class="panel-body">
                            <div class="table-responsive">
                                <table class="table table-condensed table-striped table-bordered">
                                    <thead>
                                        <tr>
                                            <th>Empleado</th>
                                            <th>Turnos asignados</th>
                                            <th>Sobreturnos asignados</th>
                                            <th>Cantidad de turnos</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($empleados)): ?>
                                            <?php foreach ($empleados as $r): ?>
                                                $total_turnos = (int)$r['total_turnos'] - (int)$r['contador_sobreturnos'];
                                                $sobreturnos = (int)$r['contador_sobreturnos'];
                                                $total = $total_turnos + $sobreturnos;
                                                ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($r['usuario']) ?></td>
                                                    <td><?= $total_turnos ?></td>
                                                    <td><?= $sobreturnos ?></td>
                                                    <td><?= $total ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="chart-container col-lg-4" style="position: relative; height:40vh; width:50vw">
                    <canvas id="chartEmpleados"></canvas>
                </div>
            </div>
        </div>

        <!-- ============================
             ESTADISTICAS DE PROFESIONALES
             ============================ -->
        <div id="contenidoOpcion2" style="display:none;">
            <div class="row">
                <div class="chart-container col-lg-4" style="position: relative; height:80vh; width:100vw">
                    <canvas id="chartProfesionales"></canvas>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- ============================
     SCRIPTS
============================ -->
<script>
    function mostrarOcultar() {
        const opcion = document.getElementById("estadisticas").value;
        document.getElementById("contenidoOpcion1").style.display = (opcion === 'empleados') ? "block" : "none";
        document.getElementById("contenidoOpcion2").style.display = (opcion === 'profesional') ? "block" : "none";
    }

    // Mostrar opción seleccionada al cargar la página
    document.addEventListener('DOMContentLoaded', () => {
        mostrarOcultar();
    });
</script>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    // Chart Empleados
    const ctxEmpleados = document.getElementById('chartEmpleados');
    if (ctxEmpleados) {
        new Chart(ctxEmpleados, {
            type: 'bar',
            data: {
                labels: <?= json_encode($empleadosLabels) ?>,
                datasets: [
                    { label: 'Turnos asignados', data: <?= json_encode($dataTotalTurnos) ?>, backgroundColor: '#F58316' },
                    { label: 'Sobreturnos asignados', data: <?= json_encode($dataContadorSobreturnos) ?>, backgroundColor: '#581BF5' },
                    { label: 'Turnos totales', data: <?= json_encode($dataUltimoMes) ?>, backgroundColor: '#17A825' }
                ]
            },
            options: { responsive: true, maintainAspectRatio: true, plugins: { legend: { position: 'top' }, title: { display: true, text: 'Turnos por empleado' } } }
        });
    }

    // Chart Profesionales
    const ctxProfesionales = document.getElementById('chartProfesionales');
    if (ctxProfesionales) {
        new Chart(ctxProfesionales, {
            type: 'bar',
            data: {
                labels: <?= json_encode($profLabels) ?>,
                datasets: [
                    { label: 'Turnos por profesional', data: <?= json_encode($profTotalTurnos) ?>, backgroundColor: 'rgb(7,157,3)' },
                    { label: 'Asistencias de turnos', data: <?= json_encode($profAsistencias) ?>, backgroundColor: 'rgb(193,18,31)' }
                ]
            },
            options: { responsive: true, maintainAspectRatio: true, plugins: { legend: { position: 'top' }, title: { display: true, text: 'Turnos y asistencias por profesional' } } }
        });
    }
</script>

<!-- DataTables -->
<script>
    $(document).ready(function () {
        $('.table').DataTable({
            "iDisplayLength": 50,
            "aLengthMenu": [[10, 25, 50, 100], [10, 25, 50, 100]],
            "bSort": false,
            dom: '<"html5buttons"B>lTfgitp',
            buttons: [
                { extend: 'excel', title: 'Estadisticas' },
                { extend: 'pdf', title: 'Estadisticas' },
                {
                    extend: 'print', text: 'IMPRIMIR', customize: function (win) {
                        $(win.document.body).addClass('white-bg').css('font-size', '10px');
                        $(win.document.body).find('table').addClass('compact').css('font-size', 'inherit');
                    }
                }
            ],
            "language": {
                "sProcessing": "Procesando...",
                "sLengthMenu": "Mostrar _MENU_ registros",
                "sZeroRecords": "No se encontraron resultados",
                "sEmptyTable": "Ningún dato disponible en esta tabla",
                "sInfo": "Mostrando registros del _START_ al _END_ de un total de _TOTAL_ registros",
                "sInfoEmpty": "Mostrando registros del 0 al 0 de un total de 0 registros",
                "sInfoFiltered": "(filtrado de un total de _MAX_ registros)",
                "sSearch": "Buscar:",
                "oPaginate": { "sFirst": "Primero", "sLast": "Último", "sNext": "Siguiente", "sPrevious": "Anterior" }
            }
        });
    });
</script>