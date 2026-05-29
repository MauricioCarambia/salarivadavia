<?php
require_once __DIR__ . '/../inc/db.php';
date_default_timezone_set('America/Argentina/Buenos_Aires');

$fecha = date('Y-m-d H:i:s');
$rand = rand(1000, 9999);
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$swalGuardado = false;

$medio_pago = $_POST['medio_pago'] ?? 'efectivo';
$transferencia_tipo = $_POST['transferencia_tipo'] ?? 'clinica';
$empleado_destino_id = !empty($_POST['empleado_destino_id'])
    ? (int)$_POST['empleado_destino_id']
    : null;

$stmt = $pdo->query("
    SELECT id, nombre
    FROM empleados
    ORDER BY nombre ASC
");
$empleados = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* =============================
   ACTUALIZAR
=============================*/
if ($id > 0 && $_SERVER['REQUEST_METHOD'] === 'POST') {

    // 🔥 FIX: NO romper si no es transferencia
    if ($medio_pago === 'transferencia') {

        if ($transferencia_tipo === 'clinica' && !$empleado_destino_id) {
            die("Debe seleccionar a quién se transfiere");
        }

        // profesional no necesita empleado, pero dejamos claro el flujo
        if ($transferencia_tipo === 'profesional') {
            $empleado_destino_id = null;
        }
    }

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
function obtenerTipoPaciente(PDO $pdo, int $paciente_id): array
{
    $stmt = $pdo->prepare("
        SELECT 
            p.tipo_socio, 
            p.fecha_alta,
            GREATEST(0, COALESCE(PERIOD_DIFF(
                DATE_FORMAT(CURDATE(), '%Y%m'),
                DATE_FORMAT(MAX(pa.fecha_correspondiente), '%Y%m')
            ), 999)) AS meses_adeudados,
            MAX(pa.fecha_correspondiente) AS ultimo_pago
        FROM pacientes p
        LEFT JOIN pagos_afiliados pa ON pa.paciente_id = p.Id
        WHERE p.Id = ?
        GROUP BY p.Id
    ");
    $stmt->execute([$paciente_id]);
    $p = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$p) return ['tipo' => 'particular', 'cobra_como' => 'particular'];

    // 🟣 Vitalicio
    if (strtolower($p['tipo_socio']) === 'vitalicio') {
        return ['tipo' => 'vitalicio', 'cobra_como' => 'socio'];
    }

    // 🟡 Primeros 90 días → EN GRACIA (cobra como particular, SIEMPRE)
    if (!empty($p['fecha_alta']) && $p['fecha_alta'] !== '0000-00-00') {
        $dias = (new DateTime())->diff(new DateTime($p['fecha_alta']))->days;
        if ($dias <= 90) {
            return ['tipo' => 'nuevo', 'cobra_como' => 'particular'];
        }
    }

    // ✅ Al día → SOCIO (cubre pacientes viejos sin fecha_alta)
    if ($p['ultimo_pago'] && (int)$p['meses_adeudados'] <= 1) {
        return ['tipo' => 'socio', 'cobra_como' => 'socio'];
    }

    // 🔴 Nunca pagó
    if (!$p['ultimo_pago']) {
        return ['tipo' => 'particular', 'cobra_como' => 'particular'];
    }

    // 🔴 Moroso
    return ['tipo' => 'moroso', 'cobra_como' => 'particular'];
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
$tipoPaciente = obtenerTipoPaciente($pdo, (int)$r['pacienteId']);
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
$paciente_id = $r['Id'];

$telefono = $r['pacienteCelular'];
$fecha = date('d/m/Y', strtotime($r['fecha']));
$hora = date('H:i', strtotime($r['fecha']));

$mensaje = urlencode("Turno:
Paciente: $paciente
Profesional: $profesional
Día: $fecha $hora");

function normalizarCelular($numero)
{
    $numero = preg_replace('/\D/', '', $numero);

    // Quitar +54 si viene
    if (substr($numero, 0, 2) === '54') {
        $numero = substr($numero, 2);
    }

    // Quitar 0 inicial (ej: 011)
    if (substr($numero, 0, 1) === '0') {
        $numero = substr($numero, 1);
    }

    // 🔥 Detectar y quitar 15 SOLO si está después del código de área
    // Caso AMBA (11)
    if (substr($numero, 0, 2) === '11' && substr($numero, 2, 2) === '15') {
        $numero = '11' . substr($numero, 4);
    }
    // Otros códigos (ej: 221, 341, etc)
    elseif (preg_match('/^(\d{3})15/', $numero, $m)) {
        $numero = $m[1] . substr($numero, 5);
    }

    return '549' . $numero;
}
$cel = normalizarCelular($r['pacienteCelular']);
$mensaje = "Recordatorio de turno

Paciente: $paciente
Profesional: $profesional
Fecha: $fecha
Hora: $hora

Sala Rivadavia
Av. Eva Perón 695 - Temperley

Se abona únicamente en efectivo

Por favor confirmar el turno.";

$mensaje = urlencode($mensaje);

?>

<div class="row mb-3">

    <!-- PACIENTE ARRIBA -->
    <div class="col-12">
        <div class="card card-info card-outline">

            <div class="card-header">
                <h3 class="card-title">
                    Datos del paciente
                    <a href="https://wa.me/<?php echo $cel; ?>?text=<?php echo $mensaje; ?>"
                        target="_blank"
                        class="btn btn-success"
                        onclick="setTimeout(() => window.focus(), 1000)">

                        <i class="fab fa-whatsapp"></i> Enviar turno
                    </a>
                </h3>

                <div class="card-tools">
                    <a href="./?seccion=pacientes_edit&id=<?= $r['pacienteId'] ?>&nc=<?= $rand ?>" class="btn btn-success btn-sm">Editar paciente</a>
                    <button type="button"
                        class="btn btn-danger btn-sm"
                        onclick="eliminarTurno(<?= (int)$id ?>)">
                        <i class="fas fa-trash"></i> Eliminar turno
                    </button>
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
                        <div class="col-md-3">
                            <b>Tipo socio:</b>
                            <?php
                            $tipo = $tipoPaciente['tipo'];

                            if ($tipo == 'vitalicio') {
                                echo '<span class="badge badge-primary">Vitalicio</span>';
                            } elseif ($tipo == 'nuevo') {
                                echo '<span class="badge badge-info">Nuevo</span>';
                            } elseif ($tipo == 'socio') {
                                echo '<span class="badge badge-success">Socio</span>';
                            } elseif ($tipo == 'moroso') {
                                echo '<span class="badge badge-danger">Moroso</span>';
                            } else {
                                echo '<span class="badge badge-secondary">Particular</span>';
                            }
                            ?>
                        </div>

                        <div class="col-md-3">
                            <b>Paga como:</b>
                            <?php
                            $cobra = $tipoPaciente['cobra_como'];

                            if ($cobra == 'socio') {
                                echo '<span class="text-success">Socio</span>';
                            } else {
                                echo '<span class="text-danger">Particular</span>';
                            }
                            ?>
                        </div>
                        <div class="col-12 mt-2"><b>Comentario:</b> <?= htmlspecialchars($rPaciente['nota']) ?></div>
                    </div>
                <?php endif; ?>

            </div>
        </div>
    </div>

</div>
<div class="row">

    <div class="col-md-6">

        <div class="card card-info card-outline">

            <div class="card-header">
                <h3 class="card-title">Detalle del turno</h3>
            </div>

            <div class="card-body">

                <!-- ================= DATOS ================= -->
                <div class="row mb-3">

                    <div class="col-md-6">
                        <b>Profesional:</b><br>
                        <?= htmlspecialchars($profesional) ?>
                    </div>

                    <div class="col-md-6">
                        <b>Paciente:</b><br>
                        <?= htmlspecialchars($paciente) ?>
                    </div>

                    <div class="col-md-6 mt-2">
                        <b>Fecha:</b><br>
                        <?= $fecha ?> <?= $hora ?>
                    </div>

                    <div class="col-md-6 mt-2">
                        <b>Sobreturno:</b><br>
                        <?php if ($r['sobreturno']): ?>
                            <span class="badge badge-warning">Sí</span>
                        <?php else: ?>
                            <span class="badge badge-secondary">No</span>
                        <?php endif; ?>
                    </div>

                </div>

                <hr>

                <!-- ================= ASISTENCIA ================= -->
                <div class="row mb-3">
                    <div class="col-md-4">

                        <div class="custom-control custom-switch">
                            <input type="checkbox" class="custom-control-input" id="asistioSwitch" name="asistio"
                                <?= $r['asistio'] ? 'checked' : '' ?>>
                            <label class="custom-control-label" for="asistioSwitch">
                                No asistió
                            </label>
                        </div>

                    </div>


                    <div class="col-md-4">
                        <label>Medio de pago</label>
                        <select name="medio_pago" id="medio_pago" class="form-control" required>
                            <option value="efectivo">Efectivo</option>
                            <option value="transferencia">Transferencia</option>
                        </select>
                    </div>
                    <div class="col-md-4" id="boxTipoTransferencia" style="display:none;">
                        <label>Tipo transferencia</label>
                        <select name="transferencia_tipo" id="transferencia_tipo" class="form-control">
                            <option value="clinica">A clínica</option>
                            <option value="profesional">Directo al profesional</option>
                        </select>
                    </div>
                    <div class="col-md-4" id="boxDestino" style="display:none;">
                        <label>Destino transferencia</label>
                        <select name="empleado_destino_id" class="form-control">
                            <option value="">Seleccionar</option>

                            <?php foreach ($empleados as $e): ?>
                                <option value="<?= $e['id'] ?>">
                                    <?= $e['nombre'] ?>
                                </option>
                            <?php endforeach; ?>

                        </select>
                    </div>

                </div>

                <hr>

                <div class="form-group">
                    <label>Agregar práctica</label>
                    <select id="selectPractica" class="form-control">
                        <option value="">Seleccionar...</option>
                    </select>
                </div>

                <button type="button" class="btn btn-success btn-sm mb-3" id="agregarPractica">
                    <i class="fas fa-plus"></i> Agregar
                </button>

                <table class="table table-sm " id="tablaCobro">
                    <thead class="thead-dark">
                        <tr>
                            <th>Práctica</th>
                            <th width="100">$</th>
                            <th width="50"></th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>

                <h4 class="text-right">
                    Total: $<span id="total">0.00</span>
                </h4>

                <button type="button" class="btn btn-primary btn-block mt-3" id="btnPreview">
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
                            <th>Medio</th>
                            <th>$</th>
                            <th>Accion</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td colspan="5" class="text-center">Cargando...</td>
                        </tr>
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
        if ($('#medio_pago').val() === 'transferencia') {
            $('#boxDestino').show();
        }

        if ($('#medio_pago').val() === 'transferencia') {
            $('#boxDestino').show();
            $('#boxTipoTransferencia').show();
        }

        $('#medio_pago').change(function() {
            if ($(this).val() === 'transferencia') {
                $('#boxDestino').show();
                $('#boxTipoTransferencia').show();
            } else {
                $('#boxDestino').hide();
                $('#boxTipoTransferencia').hide();
            }
        });
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
        if (!$('#asistioSwitch').is(':checked')) {
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

        // ✅ FIX: selector correcto
        if (!$('#asistioSwitch').is(':checked')) {
            return Swal.fire('Error', 'El paciente no asistió', 'error');
        }

        let medio_pago = $('#medio_pago').val();
        let transferencia_tipo = $('#transferencia_tipo').val();

        let empleado_destino_id = $('#boxDestino').is(':visible') ?
            $('select[name="empleado_destino_id"]').val() :
            null;

        // 🔥 VALIDACIÓN FRONT
        if (medio_pago === 'transferencia' &&
            transferencia_tipo === 'clinica' &&
            !empleado_destino_id) {

            return Swal.fire('Error', 'Seleccionar destino de transferencia', 'error');
        }

        $.get('ajax/verificar_caja_abierta.php', function(res) {

            if (!res.success) {
                return Swal.fire('Error', res.message || 'No hay caja abierta', 'error');
            }

            let practicas = [];

            $('#tablaCobro tbody tr').each(function() {
                practicas.push($(this).data('id'));
            });

            if (practicas.length === 0) {
                return Swal.fire('Error', 'Agregá prácticas para cobrar', 'error');
            }

            $.post('ajax/cobrar_turno.php', {
                turno_id: <?= $id ?>,
                practicas: practicas,
                medio_pago: medio_pago,
                empleado_destino_id: empleado_destino_id,
                transferencia_tipo: transferencia_tipo
            }, function(res) {

                console.log("RESPUESTA COBRO:", res);

                if (!res || !res.success) {
                    return Swal.fire('Error', res?.message || 'No se pudo cobrar', 'error');
                }

                const dataTicket = {
                    paciente: "<?= $paciente ?>",
                    profesional: "<?= $profesional ?>",
                    total: parseFloat(res.total || 0),
                    detalle: Array.isArray(res.detalle) ? res.detalle : []
                };

                cargarHistorial();

                $('#tablaCobro tbody').empty();
                $('#total').text('0');

                $('#asistioSwitch')
                    .prop('checked', true)
                    .trigger('change');

                try {
                    imprimirTicket(dataTicket);
                } catch (e) {
                    console.error("Error impresión:", e);
                }

                Swal.fire({
                    icon: 'success',
                    title: 'Cobro realizado',
                    timer: 1500,
                    showConfirmButton: false
                });

            }, 'json');

        }, 'json');
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
            // const printers = await qz.printers.find();
            // console.log("IMPRESORAS:", printers);

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

            /* ENCABEZADO CON LOGO */
            contenido.push("\x1B\x61\x01"); // Centrado

            // 1. Insertar la imagen (puede ser URL o Base64)
            contenido.push({
                type: 'pixel',
                format: 'png', // o 'png'
                flavor: 'file',
                data: 'images/logo_blanco_negro.png', // Ruta relativa, absoluta o base64
                options: {
                    language: "ESCPOS",
                    dotDensity: "double"
                }
            });

            //             const logo = {
            //    type: 'pixel',
            //    format: 'png',
            //    flavor: 'file',
            //    data: 'https://tuweb.com/logo.png',
            //    options: { 
            //       language: "ESCPOS", 
            //       dotDensity: "double",
            //       width: 200 // Ajusta el tamaño en píxeles según tu papel de 80mm
            //    }
            // };

            // // Luego en tu función:
            // let contenido = [logo, "\x1B\x61\x01", "SALA RIVADAVIA\n", ...];

            contenido.push("\n"); // Salto de línea después del logo
            // contenido.push("\x1B\x61\x01");
            contenido.push("SALA RIVADAVIA\n");
            contenido.push("Av. Eva Peron 695\n");
            contenido.push("Temperley\n");
            contenido.push("Fecha: " + new Date().toLocaleString() + "\n");
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


    function cargarHistorial() {

        $.get('ajax/get_historial_paciente.php', {
            paciente_id: <?= $r['pacienteId'] ?>,
            turno_id: <?= $id ?>
        }, function(res) {

            let html = '';

            if (!res.success || !res.data || res.data.length === 0) {
                html = `<tr><td colspan="5" class="text-center">Sin cobros en este turno</td></tr>`;
            } else {

                res.data.forEach(m => {

                    let medioTexto = m.medio_pago.toLowerCase();

                    let medio = medioTexto.includes('transferencia') ?
                        `<span class="badge badge-info">${m.medio_pago}</span>` :
                        `<span class="badge badge-success">${m.medio_pago}</span>`;
                    let estadoBadge = m.estado === 'anulado' ?
                        '<span class="badge badge-danger">ANULADO</span>' :
                        '';
                    let filaClass = m.estado === 'anulado' ? 'table-danger' : '';

                    html += `
<tr class="${filaClass}">
    <td>${m.fecha}</td>
    <td>${m.detalle}</td>
    <td>${medio}</td>
    <td>
        $${parseFloat(m.total).toFixed(2)}
        ${estadoBadge}
    </td>
    <td>
        ${
            m.estado !== 'anulado' 
            ? `
            <button class="btn btn-sm btn-primary reimprimir" data-id="${m.id}">
                <i class="fas fa-print"></i>
            </button>
            <button class="btn btn-sm btn-danger anular" data-id="${m.id}">
                <i class="fas fa-times"></i>
            </button>
            `
            : '<span class="text-danger">Sin acciones</span>'
        }
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
            text: 'Esta acción anula el cobro y su impacto en caja',
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
    $('#transferencia_tipo').change(function() {

        if ($(this).val() === 'profesional') {
            $('#boxDestino').hide();
            $('select[name="empleado_destino_id"]').val('');
        } else {
            $('#boxDestino').show();
        }

    });
</script>