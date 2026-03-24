<?php
require_once __DIR__ . "/../inc/db.php";
if (session_status() === PHP_SESSION_NONE)
    session_start();

date_default_timezone_set('America/Argentina/Buenos_Aires');

$profesional = filter_input(INPUT_GET, 'p', FILTER_VALIDATE_INT) ?? 0;
$busqueda = trim($_GET['busqueda'] ?? '');
$fecha = $_GET['fecha'] ?? date('Y-m-d H:i:00');

if (!$profesional)
    exit("Profesional inexistente");

$dt = new DateTime($fecha);
$fechaHora = $dt->format('Y-m-d H:i:00');
$horaSeleccionada = $dt->format('H:i');

// ============================== PROFESIONAL ==============================
$stmt = $conexion->prepare("
    SELECT apellido, nombre, duracion_turnos 
    FROM profesionales 
    WHERE Id = :id 
    LIMIT 1
");
$stmt->execute([':id' => $profesional]);

$prof = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$prof)
    exit("Profesional inexistente");

$nombreProfesional = htmlspecialchars($prof['apellido'] . ' ' . $prof['nombre']);
$duracionTurno = max(5, (int) $prof['duracion_turnos']);

// ============================== HORARIOS ==============================
$diasemana = (int) $dt->format('w');

$stmt = $conexion->prepare("
    SELECT hora_inicio, hora_fin 
    FROM profesionales_horarios 
    WHERE profesional_id = :p AND dia = :d
");
$stmt->execute([
    ':p' => $profesional,
    ':d' => $diasemana
]);

$horas = [];

while ($h = $stmt->fetch(PDO::FETCH_ASSOC)) {

    $inicio = strtotime($h['hora_inicio']);
    $fin = strtotime($h['hora_fin']);

    for ($t = $inicio; $t < $fin; $t += ($duracionTurno * 60)) {
        $horas[] = date("H:i", $t);
    }
}

$fechaCompleta = $dt->format('d/m/Y');
?>

<div id="wrapper">

    <div class="card card-info card-outline">

        <!-- HEADER -->
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-user-md"></i>
                <?= $nombreProfesional ?>
            </h3>

            <div class="card-tools">
                <button class="btn btn-danger btn-sm" onclick="cerrar()">
                    <i class="fas fa-times"></i> Cerrar
                </button>
            </div>
        </div>

        <!-- BODY -->
        <div class="card-body">

            <div class="row mb-3">
                <div class="col-md-6">
                    <strong>Fecha:</strong> <?= $fechaCompleta ?>
                </div>

                <div class="col-md-6">
                    <label><strong>Hora</strong></label>
                    <select id="select-hora-turnos" class="form-control form-control-sm">
                        <?php foreach ($horas as $h): ?>
                            <option value="<?= $h ?>" <?= $h === $horaSeleccionada ? 'selected' : '' ?>>
                                <?= $h ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- BUSCADOR -->
            <form id="form-busqueda">
                <input type="hidden" name="p" value="<?= $profesional ?>">
                <input type="hidden" name="fecha" value="<?= $fechaHora ?>">
                <input type="hidden" id="fecha-completa" value="<?= $dt->format('Y-m-d') ?>">

                <div class="input-group mb-3">
                    <input type="text" class="form-control" name="busqueda" id="busqueda"
                        value="<?= htmlspecialchars($busqueda) ?>" placeholder="Buscar paciente (nombre, apellido, DNI)"
                        autofocus>

                    <div class="input-group-append">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </div>
            </form>

            <!-- RESULTADOS -->
            <div id="resultados">

                <?php if ($busqueda !== ''):

                    $stmtPac = $conexion->prepare("
    SELECT Id, apellido, nombre, tipo_documento, documento, nro_afiliado
    FROM pacientes
    WHERE apellido LIKE :b1
       OR nombre LIKE :b2
       OR documento LIKE :b3
    LIMIT 200
");

                    $stmtPac->execute([
                        ':b1' => "%$busqueda%",
                        ':b2' => "%$busqueda%",
                        ':b3' => "%$busqueda%"
                    ]);
                    ?>

                    <div class="table-responsive">
                        <table class="table table-hover table-sm">
                            <thead class="thead-light">
                                <tr>
                                    <th>Apellido</th>
                                    <th>Nombre</th>
                                    <th>Documento</th>
                                    <th>Socio</th>
                                    <th width="50"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($p = $stmtPac->fetch(PDO::FETCH_ASSOC)): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($p['apellido']) ?></td>
                                        <td><?= htmlspecialchars($p['nombre']) ?></td>
                                        <td><?= htmlspecialchars($p['tipo_documento'] . ' ' . $p['documento']) ?></td>
                                        <td><?= htmlspecialchars($p['nro_afiliado']) ?></td>
                                        <td>
                                            <button class="btn btn-primary btn-sm"
                                                onclick="asignarTurno(<?= $p['Id'] ?>, this)">
                                                <i class="fas fa-check"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>

                <?php endif; ?>

            </div>

        </div>
    </div>
</div>

<script>

    // 🔍 BUSQUEDA (MISMA LOGICA PERO MÁS LIMPIA)
    $("#form-busqueda").on('submit', function (e) {
        e.preventDefault();

        const data = $(this).serialize();

        $("#resultados").html(`
        <div class="text-center p-3">
            <i class="fas fa-spinner fa-spin"></i> Buscando...
        </div>
    `);

        $.get("index_clean.php", {
            seccion: 'turnos_asignar',
            ...Object.fromEntries(new URLSearchParams(data))
        }, function (res) {

            // 🔥 extraer SOLO el contenido interno
            const html = $(res).find('#wrapper').html();
            $("#wrapper").html(html);

        });
    });


    // ✅ ASIGNAR TURNO CON SWEETALERT
    function asignarTurno(pacienteId, btn) {

        const fila = $(btn).closest("tr");

        const nombre = fila.find("td:eq(0)").text() + " " + fila.find("td:eq(1)").text();
        const documento = fila.find("td:eq(2)").text();

        const hora = $("#select-hora-turnos").val();
        const fechaBase = $("#fecha-completa").val();
        const fecha = fechaBase + " " + hora + ":00";

        parent.Swal.fire({
            icon: 'question',
            title: 'Confirmar turno',
            html: `
            <b>Paciente:</b> ${nombre}<br>
            <b>Documento:</b> ${documento}<br>
            <b>Hora:</b> ${hora}
        `,
            showCancelButton: true,
            confirmButtonText: 'Asignar',
            confirmButtonColor: '#28a745'
        }).then(result => {

            if (!result.isConfirmed) return;

            parent.Swal.fire({
                title: 'Guardando...',
                allowOutsideClick: false,
                didOpen: () => parent.Swal.showLoading()
            });

            $.getJSON("secciones/turnos_asignar_ok.php", {
                p: <?= $profesional ?>,
                paciente: pacienteId,
                fecha: fecha
            }, function (res) {

                if (res.success) {

                    parent.$('#modalTurno').modal('hide');

                    parent.Swal.fire({
                        icon: 'success',
                        title: 'Turno asignado',
                        timer: 1500,
                        showConfirmButton: false
                    }).then(() => parent.location.reload());

                } else {
                    parent.Swal.fire('Error', res.error, 'error');
                }

            }).fail(() => {
                parent.Swal.fire('Error', 'Error de conexión', 'error');
            });

        });
    }


    // ❌ CERRAR
    function cerrar() {
        parent.$('#modalTurno').modal('hide');
    }


    // 🔥 ENTER BUSCAR
    $("#busqueda").on("keypress", function (e) {
        if (e.which === 13) {
            $("#form-busqueda").submit();
        }
    });

</script>