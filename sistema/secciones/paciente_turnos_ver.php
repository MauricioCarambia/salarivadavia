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
$especialidadProfesional = htmlspecialchars(
    $prof['especialidad'] ?? ''
);
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
                            <th>Imprimir</th>
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
                                <td class="text-center">

                                    <button
                                        class="btn btn-warning btn-sm rounded-circle btnImprimirTurno"
                                        data-id="<?= $t['id'] ?>">

                                        <i class="fas fa-print text-white"></i>

                                    </button>

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
    document.addEventListener("DOMContentLoaded", function() {

        if (typeof qz === "undefined") {
            console.error("QZ no está cargado");
            return;
        }

        qz.security.setCertificatePromise(function(resolve, reject) {
            fetch("certificado/certificate.pem")
                .then(res => res.text())
                .then(resolve)
                .catch(reject);
        });

        qz.security.setSignaturePromise(function(toSign) {
            return function(resolve, reject) {
                fetch("certificado/firma.php", {
                        method: "POST",
                        headers: {
                            "Content-Type": "application/json"
                        },
                        body: JSON.stringify({
                            request: toSign
                        })
                    })
                    .then(res => res.text())
                    .then(resolve)
                    .catch(reject);
            };
        });

    });
    $(document).ready(function() {

        $('.datatable').each(function() {
            initDataTable($(this));
        });

    });
    $(document).on('click', '.btnImprimirTurno', async function() {

        let turnoId = $(this).data('id');

        try {

            await imprimirTurno(turnoId);

        } catch (err) {

            console.error(err);

            Swal.fire(
                'Error',
                'No se pudo imprimir el turno',
                'error'
            );

        }

    });
    async function imprimirTurno(turnoId) {

        const data = await $.getJSON(
            'ajax/obtener_comprobante_turno.php', {
                id: turnoId
            }
        );

        if (!qz.websocket.isActive()) {
            await qz.websocket.connect();
        }

        const config = qz.configs.create("POS-80C", {
            encoding: "CP437"
        });

        let contenido = [];

        function center(txt) {
            return "\x1B\x61\x01" + txt + "\n";
        }

        function left(txt) {
            return "\x1B\x61\x00" + txt + "\n";
        }

        function separator() {
            return "------------------------------------------------\n";
        }

        contenido.push("\x1B\x45\x01");
        contenido.push("\x1D\x21\x11");
        contenido.push(center("SALA RIVADAVIA"));
        contenido.push("\x1D\x21\x00");
        contenido.push("\x1B\x45\x00");

        contenido.push("\n");

        contenido.push(center("COMPROBANTE DE TURNO"));

        contenido.push(separator());

        contenido.push(
            left(
                "Paciente: " +
                data.paciente_apellido +
                " " +
                data.paciente_nombre
            )
        );

        contenido.push(
            left(
                "DNI: " +
                (data.documento || '-')
            )
        );

        contenido.push(
            left(
                "Profesional: " +
                data.profesional_apellido +
                " " +
                data.profesional_nombre
            )
        );
        if (data.profesional_especialidad) {
            contenido.push("\n");
            contenido.push(left("ESPECIALIDAD:"));
            contenido.push(left(data.profesional_especialidad));
        }
        contenido.push("\n");

        const [fechaParte, horaParte] = data.fecha.split(' ');
        const fechaFormateada = fechaParte.split('-').reverse().join('-');
        const horaFormateada = horaParte ? horaParte.substring(0, 5) : '';

        contenido.push(
            left("Fecha: " + fechaFormateada)
        );

        contenido.push(
            left("Hora: " + horaFormateada)
        );

        contenido.push(
            left(
                "Sobreturno: " +
                (data.sobreturno ? 'SI' : 'NO')
            )
        );

        contenido.push(separator());

        contenido.push(
            center(
                "PRESENTARSE 10 MINUTOS ANTES"
            )
        );

        contenido.push("\n");

        contenido.push(
            center(
                "GRACIAS POR ELEGIRNOS"
            )
        );

        contenido.push("\n\n\n\n");

        contenido.push("\x1D\x56\x00");

        await qz.print(config, contenido);
    }
</script>