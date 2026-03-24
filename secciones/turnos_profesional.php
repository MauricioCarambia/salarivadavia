<?php
date_default_timezone_set('America/Argentina/Buenos_Aires');
require_once 'inc/db.php';

$profesionalId = $_SESSION['user_id'] ?? 0;
$rand = random_int(1000, 9999);

// === POST AJAX para actualizar "atendido" ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {
    $data = json_decode(file_get_contents('php://input'), true);
    $turno_id = $data['turno_id'] ?? 0;
    $atendido = $data['atendido'] ?? false;

    if ($turno_id) {
        $stmt = $conexion->prepare("UPDATE turnos SET atendido = :atendido WHERE Id = :id");
        $stmt->execute([
            'atendido' => $atendido ? 1 : 0,
            'id' => $turno_id
        ]);
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'ID inválido']);
    }
    exit;
}

// === Filtrar por fecha ===
$fechaSeleccionada = $_POST['fechaSeleccionada'] ?? date('Y-m-d');
$inicioDia = $fechaSeleccionada . ' 00:00:00';
$finDia = $fechaSeleccionada . ' 23:59:59';

// === Consulta de turnos con pacientes y HC ===
$sql = "SELECT t.*,
               p.nombre AS pacienteNombre,
               p.apellido AS pacienteApellido,
               p.documento,
               p.historia_clinica AS pacientehc,
               t.sobreturno
        FROM turnos t
        LEFT JOIN pacientes p ON p.Id = t.paciente_id
        WHERE t.profesional_id = :pid
          AND t.fecha BETWEEN :inicio AND :fin
        ORDER BY t.fecha ASC";

$stmt = $conexion->prepare($sql);
$stmt->execute([
    'pid' => $profesionalId,
    'inicio' => $inicioDia,
    'fin' => $finDia
]);
$turnos = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
?>

<div id="wrapper">
    <div class="content animate-panel">
        <div class="row">
            <div class="col-lg-12">
                <div class="hpanel">
                    <div class="panel-heading d-flex justify-content-between align-items-center">
                        <h2 class="mb-0">
                            TURNOS DEL DÍA - <?= date('d/m/Y', strtotime($fechaSeleccionada)) ?>
                        </h2>

                        <form method="POST" class="form-inline mb-0">
                            <input type="date" name="fechaSeleccionada" value="<?= htmlspecialchars($fechaSeleccionada) ?>" class="form-control mr-2">
                            <button type="submit" class="btn btn-info"><i class="fa fa-search"></i> Buscar</button>
                        </form>
                    </div>

                    <div class="panel-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered table-hover dataTables-example">
                                <thead>
                                    <tr>
                                        <th>HC</th>
                                        <th>Paciente</th>
                                        <th>Documento</th>
                                        <th>Horario</th>
                                        <th>HC antigua</th>
                                        <th>Sobreturno</th>
                                        <th>Asistencia</th>
                                        <th>Atención</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($turnos as $t):
                                        $estadoTxt = $t['atendido'] ? 'Atendido' : 'No Atendido';
                                        $bgAsis = $t['asistio'] ? '#d4edda' : '#f8d7da';
                                        $bgAten = $t['atendido'] ? '#d4edda' : '#f8d7da';
                                    ?>
                                    <tr <?= $t['sobreturno'] ? 'style="background-color:#f8fdad;"' : '' ?>>
                                        <td>
                                            <a href="./?seccion=historia_clinica&id=<?= $t['paciente_id'] ?>&nc=<?= $rand ?>" class="btn btn-info">
                                                <i class="fa fa-search"></i>
                                            </a>
                                        </td>
                                        <td><?= htmlspecialchars($t['pacienteApellido'] . ' ' . $t['pacienteNombre']) ?></td>
                                        <td><?= htmlspecialchars($t['documento']) ?></td>
                                        <td><?= date('H:i', strtotime($t['fecha'])) ?> hs</td>
                                        <td><?= htmlspecialchars($t['pacientehc']) ?></td>
                                        <td><?= $t['sobreturno'] ? 'Sí' : 'No' ?></td>
                                        <td style="background: <?= $bgAsis ?>;">
                                            <?= $t['asistio'] ? 'En sala de espera' : 'No asistió' ?>
                                        </td>
                                        <td style="background: <?= $bgAten ?>;">
                                            <input type="checkbox" class="atendido-checkbox" data-turno-id="<?= $t['Id'] ?>" <?= $t['atendido'] ? 'checked' : '' ?> style="transform:scale(1.5);">
                                            <span class="estado-text"><?= $estadoTxt ?></span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(function(){
    $('.dataTables-example').DataTable({
        "iDisplayLength": 50,
        dom: '<"html5buttons"B>lTfgitp',
        buttons: [{extend:'excel', title:'turnos'},{extend:'pdf', title:'turnos'},{extend:'print', text:'IMPRIMIR'}],
        "language": {"url": "//cdn.datatables.net/plug-ins/1.10.21/i18n/Spanish.json"}
    });

    // Checkbox Atendido
    $(document).on('change', '.atendido-checkbox', function(){
        const $chk = $(this);
        const turnoId = $chk.data('turno-id');
        const celda = $chk.closest('td');
        const spanTxt = celda.find('.estado-text');
        const atendido = $chk.is(':checked');

        celda.css('background-color', atendido ? '#d4edda' : '#f8d7da');
        spanTxt.text(atendido ? 'Atendido' : 'No Atendido');

        fetch(window.location.href, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ turno_id: turnoId, atendido })
        })
        .then(r => r.json())
        .then(d => { if(!d.success) alert('Error al actualizar turno'); })
        .catch(console.error);
    });
});
</script>