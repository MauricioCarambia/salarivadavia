<?php
date_default_timezone_set('America/Argentina/Buenos_Aires');
require_once 'inc/db.php';

$profesionalId = $_SESSION['user_id'] ?? 0;
$rand = random_int(1000, 9999);

// === AJAX UPDATE ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {

    header('Content-Type: application/json');

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

// === FECHA ===
$fechaSeleccionada = $_POST['fechaSeleccionada'] ?? date('Y-m-d');
$inicioDia = $fechaSeleccionada . ' 00:00:00';
$finDia = $fechaSeleccionada . ' 23:59:59';

// === QUERY ===
$sql = "SELECT t.*,
               p.nombre AS pacienteNombre,
               p.apellido AS pacienteApellido,
               p.documento,
               p.historia_clinica AS pacientehc
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
$totalTurnos = count($turnos);
$atendidosCount = 0;

foreach ($turnos as $t) {
    if ($t['atendido'])
        $atendidosCount++;
}

$pendientesCount = $totalTurnos - $atendidosCount;
?>

<div class="card card-info card-outline">

    <div class="card-header d-flex  align-items-center">
        <h3 class="card-title">
            <h3 class="card-title">
                TURNOS DEL DÍA - <?= date('d/m/Y', strtotime($fechaSeleccionada)) ?>
            </h3>

            <form method="POST" class="form-inline">
                <input type="date" name="fechaSeleccionada" value="<?= htmlspecialchars($fechaSeleccionada) ?>"
                    class="form-control form-control-sm mr-2 ml-2">
                <button class="btn btn-info btn-sm">
                    <i class="fas fa-search"></i> Buscar
                </button>
            </form>
    </div>
    <div class="mb-3 d-flex gap-3 justify-content-center">
<div>Turnos: </div>
        <div class="badge badge-info p-2 ml-2">
            Total: <span id="totalTurnos"><?= $totalTurnos ?></span>
        </div>

        <div class="badge badge-success p-2 ml-2">
            Atendidos: <span id="atendidosCount"><?= $atendidosCount ?></span>
        </div>

        <div class="badge badge-danger p-2 ml-2">
            Pendientes: <span id="pendientesCount"><?= $pendientesCount ?></span>
        </div>

    </div>
    <div class="card-body table-responsive">

        <table id="tablaTurnos" class="table table-bordered table-striped datatable">
            <thead>
                <tr>
                    <th>HC</th>
                    <th>Paciente</th>
                    <th>Documento</th>
                    <th>Horario</th>
                    <th>HC Antigua</th>
                    <th>Asistencia</th>
                    <th>Atención</th>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($turnos as $t):

                    $bgAsis = $t['asistio'] ? 'bg-success' : 'bg-danger';
                    $bgAten = $t['atendido'] ? 'bg-success' : 'bg-danger';
                    ?>
                    <tr <?= $t['sobreturno'] ? 'style="border-left: 4px solid #ffc107;"' : '' ?>>

                        <td>
                            <a href="./?seccion=historia_clinica&id=<?= $t['paciente_id'] ?>&nc=<?= $rand ?>"
                                class="btn btn-info btn-sm rounded-circle">
                                <i class="fas fa-search"></i>
                            </a>
                        </td>

                        <td>
                            <div class="d-flex align-items-center justify-content-between">

                                <span>
                                    <?= htmlspecialchars($t['pacienteApellido'] . ' ' . $t['pacienteNombre']) ?>
                                </span>

                                <?php if ($t['sobreturno']): ?>
                                    <span class="badge badge-warning ml-2">
                                        Sobreturno
                                    </span>
                                <?php endif; ?>

                            </div>
                        </td>

                        <td><?= htmlspecialchars($t['documento']) ?></td>

                        <td><?= date('H:i', strtotime($t['fecha'])) ?> hs</td>

                        <td><?= htmlspecialchars($t['pacientehc']) ?></td>


                        <td>
                            <span class="badge <?= $bgAsis ?> ">
                                <?= $t['asistio'] ? 'En sala de espera' : 'No asistió' ?>
                            </span>
                        </td>

                        <td>
                            <div class="d-flex align-items-center justify-content-around gap-2">

                                <!-- Switch -->
                                <div class="d-flex align-items-center">
                                    <label class="switch small-switch mb-0">
                                        <input type="checkbox" class="atendido-checkbox" data-id="<?= $t['Id'] ?>"
                                            <?= $t['atendido'] ? 'checked' : '' ?>>
                                        <span class="slider round"></span>
                                    </label>
                                </div>

                                <!-- Estado -->
                                <span class="badge badge-atencion <?= $bgAten ?> px-2 py-1 status-badge">
                                    <?= $t['atendido'] ? 'Atendido' : 'Pendiente' ?>
                                </span>

                            </div>
                        </td>

                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

    </div>
</div>

</div>



<script>
    $(function () {

        // DataTable
        $('.datatable').each(function () {
            initDataTable($(this));
        });

        function actualizarContadores(deltaAtendido) {

            let atendidos = parseInt($('#atendidosCount').text()) || 0;
            let pendientes = parseInt($('#pendientesCount').text()) || 0;

            if (deltaAtendido) {
                atendidos++;
                pendientes--;
            } else {
                atendidos--;
                pendientes++;
            }

            $('#atendidosCount').text(atendidos);
            $('#pendientesCount').text(pendientes);
        }

        // Switch atendido
        $(document).on('change', '.atendido-checkbox', function () {

            const checkbox = $(this);
            const turnoId = checkbox.data('id');
            const atendido = checkbox.is(':checked');

            fetch('./ajax/actualizar_turno_profesional.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    turno_id: turnoId,
                    atendido: atendido
                })
            })
                .then(async res => {

                    const text = await res.text(); // primero leer como texto

                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        console.error("Respuesta no es JSON:", text);
                        throw new Error("Respuesta inválida del servidor");
                    }

                })
                .then(data => {

                    if (data.success) {

                        const row = checkbox.closest('tr');
                        const badge = row.find('.badge-atencion');

                        if (atendido) {
                            badge.removeClass('bg-danger').addClass('bg-success').text('Atendido');
                        } else {
                            badge.removeClass('bg-success').addClass('bg-danger').text('Pendiente');
                        }

                        actualizarContadores(atendido);

                    } else {
                        throw new Error(data.error || 'Error desconocido');
                    }

                })
                .catch(err => {

                    console.error(err);

                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: err.message || 'Error de conexión'
                    });

                    checkbox.prop('checked', !atendido);
                });

        });

    });
</script>