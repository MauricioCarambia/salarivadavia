<?php

require_once __DIR__ . "/../inc/db.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
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
$stmt = $pdo->prepare("
    SELECT
        p.apellido,
        p.nombre,
        p.duracion_turnos,
        e.especialidad
    FROM profesionales p
    LEFT JOIN especialidades e
        ON e.Id = p.especialidad_id
    WHERE p.Id = :id
    LIMIT 1
");
$stmt->execute([':id' => $profesional]);

$prof = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$prof)
    exit("Profesional inexistente");

$nombreProfesional = htmlspecialchars($prof['apellido'] . ' ' . $prof['nombre']);
$especialidadProfesional = htmlspecialchars(
    $prof['especialidad'] ?? ''
);
$duracionTurno = max(5, (int) $prof['duracion_turnos']);

// ============================== HORARIOS ==============================
$diasemana = (int) $dt->format('w');

$stmt = $pdo->prepare("
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

                    $stmtPac = $pdo->prepare("
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
    // 🔍 BUSQUEDA (MISMA LOGICA PERO MÁS LIMPIA)
    $("#form-busqueda").on('submit', function(e) {
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
        }, function(res) {

            const resultados = $(res).find('#resultados').html();

            $("#resultados").html(resultados);

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

        <hr>

        <div style="text-align:left">
            <label style="cursor:pointer">
                <input type="checkbox" id="imprimir_turno" checked>
                Imprimir comprobante
            </label>
        </div>
    `,
            showCancelButton: true,
            confirmButtonText: 'Asignar',
            confirmButtonColor: '#28a745'
        }).then(result => {

            if (!result.isConfirmed) return;

            const imprimir = parent.document.getElementById('imprimir_turno')?.checked;

            parent.Swal.fire({
                title: 'Guardando...',
                allowOutsideClick: false,
                didOpen: () => parent.Swal.showLoading()
            });

            $.getJSON("secciones/turnos_asignar_ok.php", {
                p: <?= $profesional ?>,
                paciente: pacienteId,
                fecha: fecha
            }, async function(res) {

                if (res.success) {

                    parent.$('#modalTurno').modal('hide');

                    parent.Swal.fire({
                        icon: 'success',
                        title: 'Turno asignado',
                        timer: 1500,
                        showConfirmButton: false
                    }).then(() => {

                        parent.$('#modalTurno').modal('hide');

                        if (parent.calendar) {
                            parent.calendar.refetchEvents();
                        }

                    });

                    // 🔥 SOLO IMPRIME SI EL CHECK ESTÁ ACTIVO (en segundo plano, no bloquea la confirmación)
                    if (imprimir) {
                        imprimirTicketTurno({
                            paciente: nombre,
                            profesional: res.profesional,
                            especialidad: res.especialidad,
                            fecha: res.fecha.split(' ')[0],
                            hora: res.hora,
                            sobreturno: res.sobreturno
                        }).catch(e => {

                            console.error(e);

                            parent.Swal.fire({
                                icon: 'warning',
                                title: 'Turno guardado',
                                text: 'No se pudo imprimir el comprobante'
                            });
                        });
                    }

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
    $("#busqueda").on("keypress", function(e) {
        if (e.which === 13) {
            $("#form-busqueda").submit();
        }
    });
    async function imprimirTicketTurno(data) {

        try {

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

            const ahora = new Date();

            const fechaEmision =
                ahora.toLocaleDateString("es-AR");

            const horaEmision =
                ahora.toLocaleTimeString("es-AR");

            /* =====================================
               RESET
            ===================================== */

            contenido.push("\x1B\x40");

            /* =====================================
               LOGO
            ===================================== */

            contenido.push("\x1B\x61\x01");



            /* =====================================
               CLINICA
            ===================================== */

            // Negrita ON
            contenido.push("\x1B\x45\x01");

            // Tamaño doble ancho + doble alto
            contenido.push("\x1D\x21\x11");

            contenido.push(center("SALA RIVADAVIA"));

            // Volver a tamaño normal
            contenido.push("\x1D\x21\x00");

            // Negrita OFF
            contenido.push("\x1B\x45\x00");
            contenido.push("\n");

            contenido.push(center("COMPROBANTE DE TURNO"));

            contenido.push(separator());

            contenido.push(center("CUIT: 30-54589575-3"));
            contenido.push(center("ING. BRUTOS: 30-54589575-3"));
            contenido.push(center("IVA RESPONSABLE INSCRIPTO"));
            contenido.push(center("INICIO ACTIVIDAD: 27/11/2013"));

            contenido.push("\n");

            contenido.push(center("AV. EVA PERON 695"));
            contenido.push(center("TEMPERLEY (1834)"));
            contenido.push(center("BUENOS AIRES"));

            contenido.push("\n");

            contenido.push(center("TEL: 3989-4325"));
            contenido.push(center("TEL: 3991-2183"));
            contenido.push(center("WHATSAPP: 11 2243-6786"));

            contenido.push(separator());


            /* =====================================
               PACIENTE
            ===================================== */

            contenido.push(left("PACIENTE:"));
            contenido.push(left(data.paciente || "-"));

            contenido.push("\n");

            contenido.push(left("PROFESIONAL:"));
            contenido.push(left("Dr/Dra " + (data.profesional || "-")));

            if (data.especialidad) {
                contenido.push("\n");
                contenido.push(left("ESPECIALIDAD:"));
                contenido.push(left(data.especialidad));
            }


            contenido.push("\n");

            contenido.push(separator());

            /* =====================================
               FECHA DEL TURNO
            ===================================== */

            contenido.push(left("FECHA DEL TURNO:"));
            contenido.push(left(data.fecha || "-"));

            contenido.push("\n");

            contenido.push(left("HORA DEL TURNO:"));
            contenido.push(left(data.hora || "-"));

            if (parseInt(data.sobreturno) === 1) {

                contenido.push("\n");

                contenido.push("\x1B\x45\x01");
                contenido.push(center("*** SOBRETURNO ***"));
                contenido.push("\x1B\x45\x00");
            }

            contenido.push(separator());

            /* =====================================
               MENSAJES
            ===================================== */

            contenido.push(center("PRESENTARSE 10 MINUTOS ANTES"));
            contenido.push(center("SE ABONA UNICAMENTE EN EFECTIVO"));

            contenido.push("\n");

            contenido.push(center("¡GRACIAS POR ELEGIRNOS!"));




            contenido.push("\n\n\n\n");

            /* =====================================
               CORTE
            ===================================== */

            contenido.push("\x1D\x56\x00");

            await qz.print(config, contenido);

        } catch (err) {

            console.error(err);

            Swal.fire({
                icon: "error",
                title: "Error imprimiendo",
                text: err.toString()
            });
        }
    }
</script>