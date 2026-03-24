<?php
require_once __DIR__ . '/../inc/db.php';

$rand = rand(1000, 9999);
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

$swalGuardado = false;

/* =============================
   ACTUALIZAR
=============================*/
if ($id > 0 && $_SERVER['REQUEST_METHOD'] === 'POST') {

    $pago = isset($_POST['pago']) ? floatval($_POST['pago']) : 0;
    $asistio = isset($_POST['asistio']) ? 1 : 0;

    $stmt = $pdo->prepare("
        UPDATE turnos 
        SET pago = :pago, asistio = :asistio 
        WHERE Id = :id
    ");
    $stmt->execute([
        ':pago' => $pago,
        ':asistio' => $asistio,
        ':id' => $id
    ]);

    $swalGuardado = true;
}

/* =============================
   TURNO
=============================*/
$stmt = $pdo->prepare("
    SELECT 
        t.*, 
        p.Id AS pacienteId, p.nombre AS pacienteNombre, p.apellido AS pacienteApellido, p.celular AS pacienteCelular,
        pr.Id AS profesionalId, pr.nombre AS profesionalNombre, pr.apellido AS profesionalApellido
    FROM turnos t
    LEFT JOIN pacientes p ON p.Id = t.paciente_id
    LEFT JOIN profesionales pr ON pr.Id = t.profesional_id
    WHERE t.Id = :id
");
$stmt->execute([':id' => $id]);
$r = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$r) {
    echo '<div class="alert alert-danger">Turno no encontrado</div>';
    exit;
}

/* =============================
   PACIENTE COMPLETO
=============================*/
$stmtPaciente = $pdo->prepare("
    SELECT o.obra_social, p.* 
    FROM pacientes p
    LEFT JOIN obras_sociales o ON o.Id = p.obra_social_id
    WHERE p.Id = :id
");
$stmtPaciente->execute([':id' => $r['pacienteId']]);
$rPaciente = $stmtPaciente->fetch(PDO::FETCH_ASSOC);

/* =============================
   HISTORIAL
=============================*/
$stmtHist = $pdo->prepare("
    SELECT fecha, sobreturno, pago, asistio
    FROM turnos
    WHERE paciente_id = :id
    ORDER BY fecha DESC
    LIMIT 10
");
$stmtHist->execute([':id' => $r['pacienteId']]);
$historial = $stmtHist->fetchAll(PDO::FETCH_ASSOC);

/* =============================
   DATOS
=============================*/
$profesional = $r['profesionalApellido'] . ' ' . $r['profesionalNombre'];
$paciente = $r['pacienteApellido'] . ' ' . $r['pacienteNombre'];

$fecha = date('d/m/Y', strtotime($r['fecha']));
$hora = date('H:i', strtotime($r['fecha']));

$mensaje = urlencode("Turno:
Paciente: $paciente
Profesional: $profesional
Día: $fecha $hora");

$cel = preg_replace('/[^0-9]/', '', $r['pacienteCelular']); // solo números

// 🔥 Normalización Argentina
if (strlen($cel) == 10) {
    // ejemplo: 1123456789 → 5491123456789
    $cel = '549' . $cel;
} elseif (strlen($cel) == 11 && substr($cel, 0, 2) == '15') {
    // ejemplo: 15123456789 → 549123456789
    $cel = '549' . substr($cel, 2);
}
$mensaje = urlencode(
    "Recordatorio de turno

Paciente: $paciente
Profesional: $profesional
Fecha: $fecha
Hora: $hora

Sala Rivadavia
Av. Eva Perón 695 - Temperley

Se abona únicamente en efectivo

Por favor confirmar el turno."
);
?>

<div class="row  mb-3">

    <!-- TURNO -->
    <div class="col-md-6">
        <div class="card card-primary card-outline">

            <div class="card-header">
                <h3 class="card-title">Detalle del turno</h3>

                <div class="card-tools">
                    <?php if (!empty($cel)): ?>
                        <a href="https://wa.me/<?= $cel ?>?text=<?= $mensaje ?>" target="_blank"
                            class="btn btn-success ">
                            <i class="fab fa-whatsapp"></i> Enviar turno
                        </a>
                    <?php else: ?>
                        <button class="btn btn-secondary " disabled>
                            Sin celular
                        </button>
                    <?php endif; ?>
                    <button class="btn btn-danger" onclick="eliminarTurno(<?= $id ?>)">
                        <i class="fa fa-trash"></i> Eliminar Turno
                    </button>
                </div>
            </div>

            <div class="card-body">

                <p><b>Profesional:</b> <?= htmlspecialchars($profesional) ?></p>
                <p><b>Paciente:</b> <?= htmlspecialchars($paciente) ?></p>
                <p><b>Fecha:</b> <?= $fecha ?></p>
                <p><b>Hora:</b> <?= $hora ?></p>
                <p><b>Sobreturno:</b> <?= $r['sobreturno'] ? 'Si' : 'No' ?></p>

                <form method="post">

                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="asistio" <?= $r['asistio'] ? 'checked' : '' ?>>
                            Asistió
                        </label>
                    </div>

                    <div class="form-group">
                        <label>Pago</label>
                        <input type="number" name="pago" class="form-control"
                            value="<?= htmlspecialchars($r['pago']) ?>">
                    </div>

                    <button class="btn btn-primary btn-block">Guardar</button>
                </form>

                <a href="./?seccion=turnos_calendario&id=<?= $r['profesionalId'] ?>&nc=<?= $rand ?>"
                    class="btn btn-secondary btn-block mt-2">
                    Volver
                </a>

            </div>
        </div>
    </div>

    <!-- PACIENTE -->
    <div class="col-md-6">
        <div class="card card-info card-outline">

            <div class="card-header">
                <h3 class="card-title">Datos del paciente</h3>

                <div class="card-tools">
                    <a href="./?seccion=pacientes_edit&id=<?= $r['pacienteId'] ?>&v=<?= $id ?>&nc=<?= $rand ?>"
                        class="btn btn-success btn-sm">
                        <i class="fas fa-edit"></i>
                    </a>
                </div>
            </div>

            <div class="card-body">

                <?php if ($rPaciente): ?>
                    <p><b>Apellido:</b> <?= htmlspecialchars($rPaciente['apellido']) ?></p>
                    <p><b>Nombre:</b> <?= htmlspecialchars($rPaciente['nombre']) ?></p>
                    <p><b>Domicilio:</b> <?= htmlspecialchars($rPaciente['domicilio']) ?></p>
                    <p><b>Provincia:</b> <?= htmlspecialchars($rPaciente['provincia']) ?></p>
                    <p><b>Localidad:</b> <?= htmlspecialchars($rPaciente['localidad']) ?></p>
                    <p><b>Celular:</b> <?= htmlspecialchars($rPaciente['celular']) ?></p>
                    <p><b>Fijo:</b> <?= htmlspecialchars($rPaciente['fijo']) ?></p>
                    <p><b>Email:</b> <?= htmlspecialchars($rPaciente['email']) ?></p>
                    <p><b>Documento:</b> <?= htmlspecialchars($rPaciente['tipo_documento']) ?>
                        <?= htmlspecialchars($rPaciente['documento']) ?>
                    </p>
                    <p><b>Fecha nacimiento:</b> <?= htmlspecialchars($rPaciente['nacimiento']) ?></p>
                    <p><b>Nro socio:</b> <?= htmlspecialchars($rPaciente['nro_afiliado']) ?></p>
                    <p><b>Obra social:</b> <?= htmlspecialchars($rPaciente['obra_social']) ?></p>
                    <p><b>Plan:</b> <?= htmlspecialchars($rPaciente['obra_social_plan']) ?></p>
                    <p><b>Número OS:</b> <?= htmlspecialchars($rPaciente['obra_social_numero']) ?></p>
                    <p><b>Sexo:</b> <?= htmlspecialchars($rPaciente['sexo']) ?></p>
                    <p><b>Comentario:</b> <?= htmlspecialchars($rPaciente['nota']) ?></p>
                <?php endif; ?>

            </div>
        </div>
    </div>

</div>

<!-- HISTORIAL -->
<div class="row">
    <div class="col-12">
        <div class="card card-secondary">

            <div class="card-header">
                <h3 class="card-title">Historial del paciente</h3>
            </div>

            <div class="card-body table-responsive">

                <table class="table table-bordered table-hover">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Hora</th>
                            <th>Sobreturno</th>
                            <th>Asistió</th>
                            <th>Pago</th>
                        </tr>
                    </thead>
                    <tbody>

                        <?php foreach ($historial as $h): ?>
                            <tr>
                                <td><?= date('d/m/Y', strtotime($h['fecha'])) ?></td>
                                <td><?= date('H:i', strtotime($h['fecha'])) ?></td>
                                <td><?= $h['sobreturno'] ? 'Si' : 'No' ?></td>
                                <td><?= $h['asistio'] ? 'Si' : 'No' ?></td>
                                <td>$<?= number_format($h['pago'], 2) ?></td>
                            </tr>
                        <?php endforeach; ?>

                    </tbody>
                </table>

            </div>
        </div>
    </div>
</div>



<script>

    <?php if ($swalGuardado): ?>
        Swal.fire({
            icon: 'success',
            title: 'Guardado',
            timer: 1500,
            showConfirmButton: false
        });
    <?php endif; ?>
    const profesionalId = <?= (int) $r['profesionalId'] ?>;
    function eliminarTurno(id) {

        Swal.fire({
            title: '¿Eliminar turno?',
            text: 'Esta acción no se puede deshacer',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#d33'
        }).then((result) => {

            if (!result.isConfirmed) return;

            fetch('secciones/turno_eliminar.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: 'id=' + id
            })
                .then(res => res.json())
                .then(resp => {

                    if (!resp.ok) {
                        return Swal.fire('Error', resp.error || 'No se pudo eliminar', 'error');
                    }

                    Swal.fire({
                        icon: 'success',
                        title: 'Turno eliminado',
                        timer: 1500,
                        showConfirmButton: false
                    }).then(() => {

                        // 🔥 opción 1: volver al calendario
                        window.location.href = './?seccion=turnos_calendario&id=' + profesionalId;

                        // 🔥 opción 2 (si estás en modal):
                        // location.reload();

                    });

                })
                .catch(() => {
                    Swal.fire('Error', 'Error de conexión', 'error');
                });

        });
    }

</script>