<?php
require_once __DIR__ . '/../inc/db.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

$stmt = $pdo->prepare("
    SELECT 
        t.id,
        t.fecha,
        t.sobreturno,
        t.asistio,

        pa.documento,
        pa.nombre AS pacienteNombre,
        pa.apellido AS pacienteApellido,

        pr.nombre AS profesionalNombre,
        pr.apellido AS profesionalApellido

    FROM turnos t

    LEFT JOIN pacientes pa 
        ON pa.Id = t.paciente_id

    LEFT JOIN profesionales pr 
        ON pr.Id = t.profesional_id

    WHERE t.paciente_id = :id

    ORDER BY t.fecha DESC

    LIMIT 25
");

$stmt->execute([
    ':id' => $id
]);

$turnos = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* =========================================
   CONTADORES
========================================= */

$asistencia = 0;
$total = 0;

foreach ($turnos as $t) {

    $fecha_turno = strtotime($t['fecha']);
    $hoy = strtotime(date('Y-m-d'));

    $esPasado = $fecha_turno <= $hoy;

    // SOLO turnos pasados
    if ($esPasado) {

        $total++;

        if ($t['asistio']) {
            $asistencia++;
        }
    }
}
?>

<div class="row mb-3">

    <div class="col-12">

        <div class="card card-info card-outline">

            <div class="card-header">

                <h3 class="card-title">
                    Historial de Turnos
                </h3>

            </div>

            <div class="card-header d-flex justify-content-between align-items-center">

                <h3 class="card-title">
                    Últimos 25 turnos
                     <a href="./?seccion=pacientes"
                    class="btn btn-secondary btn-sm">
                    Volver
                </a>
                </h3>

               

            </div>

            <div class="card-footer d-flex justify-content-between">

                <div>

                    <span class="badge badge-success p-2">
                        Asistió: <?= $asistencia ?>
                    </span>

                    <span class="badge badge-primary p-2">
                        Total: <?= $total ?>
                    </span>

                                  <?php
                    $porcentaje = $total > 0
                        ? round(($asistencia / $total) * 100)
                        : 0;
                    ?>

                    <span class="badge badge-info p-2">
                        Asistencia: <?= $porcentaje ?>%
                    </span>

                </div>

            </div>

            <div class="card-body table-responsive">

                <table class="table table-striped  datatable" style="width:100%">

                    <thead class="thead-dark">

                        <tr>
                            <th>#</th>
                            <th>Paciente</th>
                            <th>Documento</th>
                            <th>Profesional</th>
                            <th>Fecha</th>
                            <th>Estado</th>
                            <th>Sobreturno</th>
                            <th>Asistencia</th>
                        </tr>

                    </thead>

                    <tbody>

                        <?php foreach ($turnos as $i => $t): ?>

                            <?php

                            $fecha_turno = strtotime($t['fecha']);
                            $hoy = strtotime(date('Y-m-d'));

                            $esPasado = $fecha_turno <= $hoy;

                            ?>

                            <tr>

                                <td>
                                    <?= $i + 1 ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        $t['pacienteApellido'] . ' ' . $t['pacienteNombre']
                                    ) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($t['documento']) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        $t['profesionalApellido'] . ' ' . $t['profesionalNombre']
                                    ) ?>
                                </td>

                                <td>

                                    <strong>
                                        <?= date('H:i', strtotime($t['fecha'])) ?>
                                    </strong>

                                    <br>

                                    <small class="text-muted">
                                        <?= date('d/m/Y', strtotime($t['fecha'])) ?>
                                    </small>

                                </td>

                                <td>

                                    <?php if ($esPasado): ?>

                                        <span class="badge badge-danger">
                                            Turno pasado
                                        </span>

                                    <?php else: ?>

                                        <span class="badge badge-success">
                                            Turno futuro
                                        </span>

                                    <?php endif; ?>

                                </td>

                                <td>

                                    <span class="badge <?= $t['sobreturno']
                                                                ? 'badge-warning'
                                                                : 'badge-secondary' ?>">

                                        <?= $t['sobreturno']
                                            ? 'Sí'
                                            : 'No' ?>

                                    </span>

                                </td>

                                <td>

                                    <?php if (!$esPasado): ?>

                                        <span class="badge badge-secondary">
                                            Pendiente
                                        </span>

                                    <?php else: ?>

                                        <?php if ($t['asistio']): ?>

                                            <span class="badge badge-success">
                                                Asistió
                                            </span>

                                        <?php else: ?>

                                            <span class="badge badge-danger">
                                                No asistió
                                            </span>

                                        <?php endif; ?>

                                    <?php endif; ?>

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
</script>