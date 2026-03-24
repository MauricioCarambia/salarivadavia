<?php
require_once __DIR__ . '/../inc/db.php'; // $pdo: instancia PDO
$rand = rand(1000,9999);
?>

<div class="row mb-3">
    <div class="col-12">
        <div class="card card-primary card-outline">
            <div class="card-header">
                <h3 class="card-title">Turnos del día (todos los profesionales)</h3>
            </div>
            <div class="card-body table-responsive">
                <table class="table table-striped table-bordered datatable" style="width:100%">
                    <thead class="thead-dark">
                        <tr>
                            <th>Horario</th>
                            <th>Profesional</th>
                            <th>Paciente</th>
                            <th>Documento</th>
                            <th>Sobreturno</th>
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
                        <tr class="<?= ($r['sobreturno']=='1') ? 'table-warning' : '' ?>">
                            <td><?= strftime('%H:%M hs', strtotime($r['fecha'])) ?></td>
                            <td><?= htmlspecialchars($r['profesionalApellido'].' '.$r['profesionalNombre']) ?></td>
                            <td><?= htmlspecialchars($r['pacienteApellido'].' '.$r['pacienteNombre']) ?></td>
                            <td><?= htmlspecialchars($r['documento']) ?></td>
                            <td><?= ($r['sobreturno']=='1') ? 'Sobreturno' : '' ?></td>
                            <td>
                                <a href="./?seccion=pacientes_edit&id=<?= $r['Id'] ?>&nc=<?= $rand ?>" 
                                   class="btn btn-success btn-sm rounded-circle">
                                   <i class="fas fa-pencil-alt"></i>
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