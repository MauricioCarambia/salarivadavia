<?php
require_once __DIR__ . '/../inc/db.php'; // $pdo: instancia PDO
$rand = rand(1000, 9999);
?>

<div class="row mb-3">
    <div class="col-12">
        <div class="card card-primary card-outline">
            <div class="card-header">
                <h3 class="card-title">Turnos del día (todos los profesionales)</h3>
            </div>
            <div class="card-body table-responsive">
                <table class="table table-striped  datatable" style="width:100%">
                    <thead class="thead-dark">
                        <tr>
                            <th>Horario</th>
                            <th>Profesional</th>
                            <th>Paciente</th>
                            <th>Documento</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $sql = "SELECT 
            turnos.*, 
            pacientes.documento, 
            pacientes.nombre AS pacienteNombre, 
            pacientes.apellido AS pacienteApellido, 
            profesionales.nombre AS profesionalNombre, 
            profesionales.apellido AS profesionalApellido
        FROM turnos
        LEFT JOIN pacientes ON pacientes.Id = turnos.paciente_id
        LEFT JOIN profesionales ON profesionales.Id = turnos.profesional_id
        WHERE DATE(turnos.fecha) = DATE(NOW())
        ORDER BY turnos.fecha ASC, turnos.sobreturno ASC";

                        $stmt = $pdo->query($sql);
                        $turnos = $stmt->fetchAll(PDO::FETCH_ASSOC);

                        foreach ($turnos as $r):
                            ?>
                            <tr <?= $r['sobreturno'] ? 'style="border-left: 4px solid #ffc107;"' : '' ?>>
                                <td><?= strftime('%H:%M hs', strtotime($r['fecha'])) ?></td>
                                <td><?= htmlspecialchars($r['profesionalApellido'] . ' ' . $r['profesionalNombre']) ?></td>
                                <td>
                                    <div class="d-flex align-items-center justify-content-between">
                                        <?= htmlspecialchars($r['pacienteApellido'] . ' ' . $r['pacienteNombre']) ?>
                                        <?php if ($r['sobreturno']): ?>
                                            <span class="badge badge-warning ml-2">
                                                Sobreturno
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td><?= htmlspecialchars($r['documento']) ?></td>

                                <td>
                                    <a href="./?seccion=turnos_ver&id=<?= $r['Id'] ?>&nc=<?= $rand ?>"
                                        class="btn btn-primary btn-sm rounded-circle">
                                        <i class="fa fa-eye"></i>
                                    </a>
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
    $(document).ready(function () {
        $('.datatable').each(function () {
            initDataTable($(this));
        });
    });
</script>