<?php
require_once __DIR__ . '/../inc/db.php';
date_default_timezone_set('America/Argentina/Buenos_Aires');

$fecha = date('Y-m-d H:i:s');
$rand = rand(1000, 9999);
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

$swalGuardado = false;

/* =============================
   ACTUALIZAR
=============================*/
if ($id > 0 && $_SERVER['REQUEST_METHOD'] === 'POST') {

    $asistio = isset($_POST['asistio']) ? 1 : 0;

    $stmt = $pdo->prepare("
        UPDATE turnos 
        SET 
            asistio = :asistio
        WHERE Id = :id
    ");

    $stmt->execute([
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

<div class="row mb-3">

    <!-- PACIENTE ARRIBA -->
    <div class="col-12">
        <div class="card card-info card-outline">

            <div class="card-header">
                <h3 class="card-title">Datos del paciente <a href="./?seccion=turnos_calendario&id=<?= $id ?>&nc=<?= $rand ?>"
                        class="btn btn-secondary btn-sm">
                        Volver
                    </a></h3>

                <div class="card-tools">
                    <a href="./?seccion=turnos_calendario&id=<?= $r['profesionalId'] ?>&nc=<?= $rand ?>"
                        class="btn btn-secondary btn-sm">
                        Volver
                    </a>
                </div>
            </div>

            <div class="card-body">

                <?php if ($rPaciente): ?>
                    <div class="row">
                        <div class="col-md-3"><b>Apellido:</b> <?= htmlspecialchars($rPaciente['apellido']) ?></div>
                        <div class="col-md-3"><b>Nombre:</b> <?= htmlspecialchars($rPaciente['nombre']) ?></div>
                        <div class="col-md-3"><b>Documento:</b> <?= htmlspecialchars($rPaciente['documento']) ?></div>
                        <div class="col-md-3"><b>Celular:</b> <?= htmlspecialchars($rPaciente['celular']) ?></div>

                        <div class="col-md-3"><b>Provincia:</b> <?= htmlspecialchars($rPaciente['provincia']) ?></div>
                        <div class="col-md-3"><b>Localidad:</b> <?= htmlspecialchars($rPaciente['localidad']) ?></div>
                        <div class="col-md-3"><b>Domicilio:</b> <?= htmlspecialchars($rPaciente['domicilio']) ?></div>
                        <div class="col-md-3"><b>Email:</b> <?= htmlspecialchars($rPaciente['email']) ?></div>

                        <div class="col-md-3"><b>Nro socio:</b> <?= htmlspecialchars($rPaciente['nro_afiliado']) ?></div>
                        <div class="col-md-3"><b>Obra social:</b> <?= htmlspecialchars($rPaciente['obra_social']) ?></div>
                        <div class="col-md-3"><b>Plan:</b> <?= htmlspecialchars($rPaciente['obra_social_plan']) ?></div>
                        <div class="col-md-3"><b>Sexo:</b> <?= htmlspecialchars($rPaciente['sexo']) ?></div>

                        <div class="col-12 mt-2"><b>Comentario:</b> <?= htmlspecialchars($rPaciente['nota']) ?></div>
                    </div>
                <?php endif; ?>

            </div>
        </div>
    </div>

</div>
<div class="row">

    <!-- IZQUIERDA -->
    <div class="col-md-6">

        <!-- TURNO -->
        <div class="card card-info card-outline">
            <div class="card-header">
                <h3 class="card-title">Detalle del turno</h3>
            </div>

            <div class="card-body">
                <p><b>Profesional:</b> <?= htmlspecialchars($profesional) ?></p>
                <p><b>Paciente:</b> <?= htmlspecialchars($paciente) ?></p>
                <p><b>Fecha:</b> <?= $fecha ?> <?= $hora ?></p>
                <p><b>Sobreturno:</b> <?= $r['sobreturno'] ? 'Si' : 'No' ?></p>

                <form method="post">
                    <div class="custom-control custom-switch">
                        <input type="checkbox" class="custom-control-input" id="asistioSwitch" name="asistio"
                            <?= $r['asistio'] ? 'checked' : '' ?>>

                        <label class="custom-control-label" for="asistioSwitch">
                            Asistió
                        </label>
                    </div>
                </form>

                <div class="card-header">
                    <h3 class="card-title">Cobro</h3>
                </div>



                <div class="form-group">
                    <label>Agregar práctica</label>
                    <select id="selectPractica" class="form-control">
                        <option value="">Seleccionar...</option>
                    </select>
                </div>

                <button type="button" class="btn btn-success btn-sm mb-2" id="agregarPractica">
                    <i class="fas fa-plus"></i> Agregar
                </button>

                <table class="table table-sm" id="tablaCobro">
                    <thead>
                        <tr>
                            <th>Práctica</th>
                            <th>$</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>

                <h4 class="text-right">
                    Total: $<span id="total">0</span>
                </h4>

                <button type="button" class="btn btn-primary btn-block" id="btnPreview">
                    <i class="fas fa-eye"></i> Ver resumen
                </button>
            </div>

        </div>
    </div>

    <!-- DERECHA: HISTORIAL -->
    <div class="col-md-6">
        <div class="card card-info card-outline">

            <div class="card-header">
                <h3 class="card-title">Historial de cobros</h3>
            </div>

            <div class="card-body" style="max-height:500px; overflow:auto;">

                <table class="table table-sm table-striped" id="tablaHistorial">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Detalle</th>
                            <th>$</th>
                            <th>Accion</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td colspan="4" class="text-center">Cargando...</td>
                        </tr>
                    </tbody>
                </table>

            </div>

        </div>
    </div>

</div>




<script>
    $(document).ready(function() {

        $.get('ajax/get_practicas.php', {
            profesional_id: <?= $r['profesionalId'] ?>,
            paciente_id: <?= $r['pacienteId'] ?>
        }, function(res) {

            if (res.success) {

                res.data.forEach(p => {
                    $('#selectPractica').append(
                        `<option value="${p.Id}">${p.nombre}</option>`
                    );
                });

            }

        }, 'json');
        cargarHistorial();
    });

    function mostrarPreview(data) {

        let columnas = new Set();

        // 🔹 detectar destinos dinámicamente
        data.detalle.forEach(p => {
            p.reparto.forEach(r => columnas.add(r.destino));
        });

        columnas = Array.from(columnas);

        let thead = `
        <tr>
            <th>Práctica</th>
            <th>Total</th>
            ${columnas.map(c => `<th>${c}</th>`).join('')}
        </tr>
    `;

        let filas = '';

        data.detalle.forEach(p => {

            let fila = `
            <tr>
                <td>${p.nombre}</td>
                <td>$${parseFloat(p.precio).toFixed(2)}</td>
        `;

            columnas.forEach(col => {

                let encontrado = p.reparto.find(r => r.destino === col);

                fila += `
                <td class="text-primary">
                    $${encontrado ? parseFloat(encontrado.valor).toFixed(2) : '0.00'}
                </td>
            `;
            });

            fila += `</tr>`;
            filas += fila;
        });

        let totalesHtml = `
        <div style="font-size:16px;">
            <b>Total general: $${parseFloat(data.totales.total).toFixed(2)}</b>
        </div>
    `;

        Object.entries(data.totales.destinos).forEach(([k, v]) => {
            totalesHtml += `${k}: $${parseFloat(v).toFixed(2)}<br>`;
        });

        Swal.fire({
            title: 'Resumen de cobro',
            width: 900,
            html: `
            <table class="table table-bordered text-sm">
                <thead>${thead}</thead>
                <tbody>${filas}</tbody>
            </table>

            <hr>
            ${totalesHtml}
        `,
            showCancelButton: true,
            confirmButtonText: 'Cobrar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#28a745'
        }).then(result => {

            if (result.isConfirmed) {
                cobrarTurno();
            }

        });
    }
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
    $(document).on('click', '.quitar', function() {
        $(this).closest('tr').remove();
        calcularTotal();
    });


    $('#agregarPractica').click(function() {
        if (!$('input[name="asistio"]').is(':checked')) {
            return Swal.fire('Error', 'Primero marcar que asistió', 'error');
        }

        let practica_id = $('#selectPractica').val();

        if (!practica_id) return;

        // 🔥 evitar duplicados
        let existe = false;

        $('#tablaCobro tbody tr').each(function() {
            if ($(this).data('id') == practica_id) {
                existe = true;
            }

        });
        if (existe) {
            return Swal.fire('Error', 'La práctica ya fue agregada', 'error');
        }

        $.get('ajax/get_precio.php', {
            practica_id: practica_id,
            paciente_id: <?= $r['pacienteId'] ?>
        }, function(res) {

            console.log(res); // 👈 CLAVE

            if (res.success) {

                let fila = `
            <tr data-id="${practica_id}">
                <td>${res.nombre}</td>
                <td class="precio">${parseFloat(res.precio).toFixed(2)}</td>
                <td>
                    <button class="btn btn-danger btn-sm quitar">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            </tr>`;

                $('#tablaCobro tbody').append(fila);

                calcularTotal();
            }

        }, 'json');

    });

    function calcularTotal() {

        let total = 0;

        $('#tablaCobro tbody tr').each(function() {
            total += parseFloat($(this).find('.precio').text());
        });

        $('#total').text(total.toFixed(2));
    }

    $('#btnPreview').click(function() {

        let practicas = [];

        $('#tablaCobro tbody tr').each(function() {
            practicas.push($(this).data('id'));
        });

        if (practicas.length === 0) {
            return Swal.fire('Error', 'Agregá prácticas', 'error');
        }

        $.post('ajax/cobro_preview.php', {
            turno_id: <?= $id ?>,
            practicas: practicas
        }, function(res) {

            console.log(res);

            if (!res || !res.success) {
                return Swal.fire('Error', res?.message || 'Error en preview', 'error');
            }

            if (!res.detalle || !res.totales) {
                return Swal.fire('Error', 'Respuesta incompleta del servidor', 'error');
            }

            mostrarPreview(res);

        }, 'json');

    });

    function cobrarTurno() {

        if (!$('input[name="asistio"]').is(':checked')) {
            return Swal.fire('Error', 'El paciente no asistió', 'error');
        }

        // 🔒 Validar caja abierta
        $.get('ajax/verificar_caja_abierta.php', function(res) {

                if (!res.success) {
                    return Swal.fire('Error', res.message || 'No hay caja abierta', 'error');
                }

                // 🔹 Obtener prácticas
                let practicas = [];
                $('#tablaCobro tbody tr').each(function() {
                    practicas.push($(this).data('id'));
                });

                if (practicas.length === 0) {
                    return Swal.fire('Error', 'Agregá prácticas para cobrar', 'error');
                }

                // ⚡ Cobrar
                $.post('ajax/cobrar_turno.php', {
                        turno_id: <?= $id ?>,
                        practicas: practicas
                    }, function(res) {

                        console.log("RESPUESTA COBRO:", res); // 🔥 DEBUG

                        if (!res || !res.success) {
                            return Swal.fire('Error', res?.message || 'No se pudo cobrar', 'error');
                        }

                        // 🔹 Normalizar datos SIEMPRE
                        const dataTicket = {
                            paciente: "<?= $paciente ?>",
                            profesional: "<?= $profesional ?>",
                            total: parseFloat(res.total || 0),
                            detalle: Array.isArray(res.detalle) ? res.detalle : []
                        };

                        // 🔹 Validación fuerte
                        if (dataTicket.detalle.length === 0) {
                            console.warn("⚠ Ticket sin detalle", dataTicket);
                        }

                        // 🔹 Actualizar historial
                        cargarHistorial();

                        // 🔹 Limpiar UI
                        $('#tablaCobro tbody').empty();
                        $('#total').text('0');

                        // 🔹 Marcar asistió
                        $('#asistioSwitch')
                            .prop('checked', true)
                            .next('label')
                            .text('Asistió');

                        // 🔹 Imprimir (con protección)
                        try {
                            imprimirTicket(dataTicket);
                        } catch (e) {
                            console.error("Error impresión:", e);
                            Swal.fire('Error', 'Falló la impresión', 'error');
                        }

                        // 🔹 Feedback
                        Swal.fire({
                            icon: 'success',
                            title: 'Cobro realizado',
                            timer: 1500,
                            showConfirmButton: false
                        });

                    }, 'json')

                    .fail(function() {
                        Swal.fire('Error', 'Error de conexión al cobrar', 'error');
                    });

            }, 'json')

            .fail(function() {
                Swal.fire('Error', 'Error de conexión al verificar caja', 'error');
            });
    }
    /*******************************************************
     * codigo para impresora
     **************************************************/
    async function imprimirTicket(data) {
        try {
            if (!qz.websocket.isActive()) {
                await qz.websocket.connect();
            }

            // DEBUG opcional (podés dejarlo o sacarlo)
            const printers = await qz.printers.find();
            console.log("IMPRESORAS:", printers);

            // CONFIGURACIÓN DIRECTA (SIN find)
            const config = qz.configs.create("POS-80C", {
                encoding: 'CP437'
            });

            let contenido = [];

            function linea(nombre, precio) {
                let left = nombre.substring(0, 30);
                let right = "$" + parseFloat(precio).toFixed(2);

                let spaces = 48 - (left.length + right.length);
                if (spaces < 1) spaces = 1;

                return left + " ".repeat(spaces) + right;
            }

            /* ENCABEZADO */
            contenido.push("\x1B\x61\x01");
            contenido.push("SALA RIVADAVIA\n");
            contenido.push("Av. Eva Peron 695\n");
            contenido.push("Temperley\n");
            contenido.push("------------------------------------------------\n");

            /* DATOS */
            contenido.push("\x1B\x61\x00");
            contenido.push("Paciente: " + data.paciente + "\n");
            contenido.push("Profesional: " + data.profesional + "\n");
            contenido.push("------------------------------------------------\n");

            /* DETALLE */
            data.detalle.forEach(d => {
                contenido.push(linea(d.nombre, d.precio) + "\n");
            });

            contenido.push("------------------------------------------------\n");

            /* TOTAL */
            contenido.push("\x1B\x61\x02");
            contenido.push("TOTAL: $" + parseFloat(data.total).toFixed(2) + "\n");

            contenido.push("\x1B\x61\x01");
            contenido.push("\nGracias por su visita\n");

            /* CORTE */
            contenido.push("\n\n\n");
            contenido.push("\x1D\x56\x00");

            await qz.print(config, contenido);

        } catch (err) {
            console.error(err);
            alert("Error imprimiendo: " + err);
        }
    }
    // function imprimirTicket(data) {

    //     function linea(nombre, precio) {
    //         let left = nombre.substring(0, 30);
    //         let right = "$" + parseFloat(precio).toFixed(2);

    //         let spaces = 48 - (left.length + right.length);
    //         if (spaces < 1) spaces = 1;

    //         return left + " ".repeat(spaces) + right;
    //     }

    //     function escapeHtml(text) {
    //         return text.replace(/</g, "&lt;").replace(/>/g, "&gt;");
    //     }

    //     let html = `
    //     <html>
    //     <head>
    //         <title>Vista Ticket</title>
    //         <style>
    //             body{
    //                 font-family: monospace;
    //                 width: 380px;
    //                 margin: auto;
    //                 font-size: 12px;
    //             }
    //             .center{text-align:center;}
    //             .right{text-align:right;}
    //             pre{white-space: pre;}
    //         </style>
    //     </head>
    //     <body>

    //     <div class="center">
    //         <b>SALA RIVADAVIA</b><br>
    //         Av. Eva Peron 695<br>
    //         Temperley
    //     </div>

    //     <pre>------------------------------------------------</pre>

    //     <div>Paciente: ${escapeHtml(data.paciente)}</div>
    //     <div>Profesional: ${escapeHtml(data.profesional)}</div>

    //     <pre>------------------------------------------------</pre>
    //     `;

    //     data.detalle.forEach(d => {
    //         html += `<pre>${escapeHtml(linea(d.nombre, d.precio))}</pre>`;
    //     });

    //     html += `
    //     <pre>------------------------------------------------</pre>

    //     <div class="right">
    //         <b>TOTAL: $${parseFloat(data.total).toFixed(2)}</b>
    //     </div>

    //     <div class="center">
    //         <br>Gracias por su visita
    //     </div>

    //     <br><br>

    //     <div class="center">
    //         ---- COPIA CLINICA ----
    //     </div>

    //     <pre>------------------------------------------------</pre>
    //     `;

    //     data.detalle.forEach(d => {
    //         html += `<pre>${escapeHtml(linea(d.nombre, d.precio))}</pre>`;
    //     });

    //     html += `
    //     <pre>------------------------------------------------</pre>

    //     <div class="right">
    //         <b>TOTAL: $${parseFloat(data.total).toFixed(2)}</b>
    //     </div>

    //     </body>
    //     </html>
    //     `;

    //     let win = window.open('', '_blank', 'width=420,height=700');
    //     win.document.write(html);
    //     win.document.close();
    // }

    function cargarHistorial() {

        $.get('ajax/get_historial_paciente.php', {
            paciente_id: <?= $r['pacienteId'] ?>,
            turno_id: <?= $id ?>
        }, function(res) {

            let html = '';

            if (!res.success || !res.data || res.data.length === 0) {
                html = `<tr><td colspan="4" class="text-center">Sin cobros en este turno</td></tr>`;
            } else {

                res.data.forEach(m => {
                    html += `
    <tr>
        <td>${m.fecha}</td>
        <td>${m.detalle}</td>
        <td>$${parseFloat(m.total).toFixed(2)}</td>
        <td>
            <button class="btn btn-sm btn-primary reimprimir" data-id="${m.id}">
                <i class="fas fa-print"></i>
            </button>
            <button class="btn btn-sm btn-danger anular" data-id="${m.id}">
                <i class="fas fa-times"></i>
            </button>
        </td>
    </tr>`;
                });

            }

            $('#tablaHistorial tbody').html(html);

        }, 'json');
    }


    $(document).on('click', '.reimprimir', function() {

        let cobro_id = $(this).data('id');

        $.get('ajax/get_cobro_detalle.php', {
            cobro_id: cobro_id
        }, function(res) {

            if (!res.success || !res.data) {
                return Swal.fire('Error', 'No se pudo obtener el cobro', 'error');
            }

            imprimirTicket(res.data);

        }, 'json');

    });
    $(document).on('click', '.anular', function() {

        let cobro_id = $(this).data('id');

        Swal.fire({
            title: '¿Anular cobro?',
            text: 'Esta acción revierte el movimiento de caja',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, anular',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#d33'
        }).then(result => {

            if (!result.isConfirmed) return;

            $.post('ajax/anular_cobro.php', {
                cobro_id: cobro_id
            }, function(res) {

                if (!res.success) {
                    return Swal.fire('Error', res.message, 'error');
                }

                Swal.fire({
                    icon: 'success',
                    title: 'Cobro anulado',
                    timer: 1500,
                    showConfirmButton: false
                });

                cargarHistorial(); // 🔥 refresca

            }, 'json');

        });

    });
    $('#asistioSwitch').change(function() {
        if ($(this).is(':checked')) {
            $(this).next('label').text('Asistió');
        } else {
            $(this).next('label').text('No asistió');
        }
    });
</script>