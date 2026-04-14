<?php
require_once __DIR__ . '/../inc/db.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

$asistencia = 0;
$total = 0;

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
    LEFT JOIN pacientes pa ON pa.Id = t.paciente_id
    LEFT JOIN profesionales pr ON pr.Id = t.profesional_id
    WHERE t.paciente_id = :id
    ORDER BY t.fecha DESC
    LIMIT 25
");
$stmt->execute([':id' => $id]);
$turnos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="row mb-3">
    <div class="col-12">
        <div class="card card-info card-outline">
            <div class="card-header">
                <h3 class="card-title">
                    <h3>Historial de Turnos</h3>
            </div>

            <div class="card-header">
                <h3 class="card-title">Últimos 25 turnos 
                    <a href="./?seccion=pacientes" class="btn btn-secondary">
                    Volver
                </a></h3>
            </div>

          <div class="card-body table-responsive">
                    <table class="table table-striped datatable" style="width:100%">
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

                        <?php foreach ($turnos as $i => $t):

                            $fecha_turno = strtotime($t['fecha']);
                            $hoy = strtotime(date('Y-m-d'));

                            $esPasado = $fecha_turno <= $hoy;

                            $total++;
                            if ($t['asistio']) $asistencia++;

                        ?>

                            <tr>

                                <td><?= $i + 1 ?></td>

                                <td>
                                    <?= htmlspecialchars($t['pacienteApellido'] . ' ' . $t['pacienteNombre']) ?>
                                </td>

                                <td><?= htmlspecialchars($t['documento']) ?></td>

                                <td>
                                    <?= htmlspecialchars($t['profesionalApellido'] . ' ' . $t['profesionalNombre']) ?>
                                </td>

                                <td>
                                    <?= date('H:i', strtotime($t['fecha'])) ?>
                                    <br>
                                    <small class="text-muted">
                                        <?= date('d/m/Y', strtotime($t['fecha'])) ?>
                                    </small>
                                </td>

                                <td>
                                    <?php if ($esPasado): ?>
                                        <span class="badge badge-danger">Turno pasado</span>
                                    <?php else: ?>
                                        <span class="badge badge-success">Turno futuro</span>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <span class="badge <?= $t['sobreturno'] ? 'badge-warning' : 'badge-light' ?>">
                                        <?= $t['sobreturno'] ? 'Sí' : 'No' ?>
                                    </span>
                                </td>

                                <td>
                                    <?php if ($t['asistio']): ?>
                                        <span class="badge badge-success">Asistió</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">No asistió</span>
                                    <?php endif; ?>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>
                </table>

            </div>

            <div class="card-footer d-flex justify-content-between">

                <div>
                    <span class="badge badge-success">
                        Asistió: <?= $asistencia ?>
                    </span>

                    <span class="badge badge-primary">
                        Total: <?= $total ?>
                    </span>
                </div>

                

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