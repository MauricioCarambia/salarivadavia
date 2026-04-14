<?php
require_once __DIR__ . '/../inc/db.php';

// 1. Traer profesionales y pacientes (Usando Id con mayúscula según tu SQL)
$profesionales = $pdo->query("SELECT Id as id, nombre, apellido FROM profesionales ORDER BY apellido ASC")->fetchAll(PDO::FETCH_ASSOC);
$pacientes     = $pdo->query("SELECT Id as id, nombre, apellido FROM pacientes ORDER BY apellido ASC")->fetchAll(PDO::FETCH_ASSOC);

// 2. Traer destinos de la tabla correcta para el formulario
$destinos = $pdo->query("SELECT id, nombre FROM destinos_reparto ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);
date_default_timezone_set('America/Argentina/Buenos_Aires');

/* ==============================
   📅 FILTROS
============================== */
$desde = $_GET['desde'] ?? date('Y-m-d');
$hasta = $_GET['hasta'] ?? date('Y-m-d');
$caja_id = $_GET['caja_id'] ?? '';
$turno = $_GET['turno'] ?? '';
$empleado_id = $_GET['empleado_id'] ?? '';

$desdeSQL = $desde . " 00:00:00";
$hastaSQL = $hasta . " 23:59:59";

/* ==============================
   🧠 WHERE DINÁMICO
============================== */
$where = "WHERE m.cobro_id IS NOT NULL 
          AND c.turno_id IS NULL
          AND m.fecha BETWEEN ? AND ?";

$params = [$desdeSQL, $hastaSQL];

if (!empty($caja_id)) {
    $where .= " AND cs.caja_id = ?";
    $params[] = $caja_id;
}

if (!empty($turno)) {
    $where .= " AND cs.turno = ?";
    $params[] = $turno;
}

if (!empty($empleado_id)) {
    $where .= " AND cs.usuario_id = ?";
    $params[] = $empleado_id;
}
// 3. Movimientos asociados a cobros (JOIN corregido para llegar al destino)
// Cambia la consulta 3 por esta:
$movimientos = $pdo->prepare("
    SELECT 
        m.id, m.tipo, m.concepto, m.monto, m.fecha, m.descripcion,
        c.id as cobro_id, 
        c.estado,
        GROUP_CONCAT(DISTINCT d.nombre SEPARATOR ', ') AS destino_nombre, 
        p.nombre AS paciente_nombre,
        p.apellido AS paciente_apellido,
        pr.nombre AS profesional_nombre,
        pr.apellido AS profesional_apellido
    FROM caja_movimientos m
    LEFT JOIN cobros c ON c.id = m.cobro_id
    LEFT JOIN caja_sesion cs ON cs.id = m.caja_sesion_id
    LEFT JOIN cobros_reparto cr ON cr.cobro_id = c.id
    LEFT JOIN destinos_reparto d ON d.id = cr.destino_id
    LEFT JOIN pacientes p ON p.Id = c.paciente_id
    LEFT JOIN profesionales pr ON pr.Id = c.profesional_id
    $where
    GROUP BY m.id
    ORDER BY m.fecha DESC
");

$movimientos->execute($params);
$movimientos = $movimientos->fetchAll(PDO::FETCH_ASSOC);

// Separar las tablas
$ingresos = array_filter($movimientos, fn($m) => $m['tipo'] === 'ingreso');
$egresos  = array_filter($movimientos, fn($m) => $m['tipo'] === 'egreso');
?>
<div class="card card-outline card-info mb-3 p-3">
    <form method="GET" class="row">
        <input type="hidden" name="seccion" value="<?= $_GET['seccion'] ?? '' ?>">

        <div class="col-md-2">
            <label>Desde</label>
            <input type="date" name="desde" value="<?= $desde ?>" class="form-control">
        </div>

        <div class="col-md-2">
            <label>Hasta</label>
            <input type="date" name="hasta" value="<?= $hasta ?>" class="form-control">
        </div>

        <div class="col-md-2">
            <label>Caja</label>
            <select name="caja_id" class="form-control">
                <option value="">Todas</option>
                <?php foreach ($pdo->query("SELECT id, nombre FROM cajas")->fetchAll() as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $caja_id == $c['id'] ? 'selected' : '' ?>>
                        <?= $c['nombre'] ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-2">
            <label>Turno</label>
            <select name="turno" class="form-control">
                <option value="">Todos</option>
                <option value="mañana" <?= $turno == 'mañana' ? 'selected' : '' ?>>Mañana</option>
                <option value="tarde" <?= $turno == 'tarde' ? 'selected' : '' ?>>Tarde</option>
            </select>
        </div>

        <div class="col-md-2">
            <label>Empleado</label>
            <select name="empleado_id" class="form-control">
                <option value="">Todos</option>
                <?php foreach ($pdo->query("SELECT id, nombre FROM empleados")->fetchAll() as $e): ?>
                    <option value="<?= $e['id'] ?>" <?= $empleado_id == $e['id'] ? 'selected' : '' ?>>
                        <?= $e['nombre'] ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-1 d-flex align-items-end">
            <button class="btn btn-primary w-100">Filtrar</button>
        </div>

        <div class="col-md-1 d-flex align-items-end">
            <a href="?seccion=<?= $_GET['seccion'] ?? '' ?>" class="btn btn-secondary w-100">
                Limpiar
            </a>
        </div>
    </form>
</div>
<div class="card card-outline card-warning mb-3 p-3">
    <form id="formMovimientoManual">

        <div class="row">

            <!-- TIPO -->
            <div class="col-md-2 mb-2">
                <label>Tipo</label>
                <select name="tipo" class="form-control" required>
                    <option value="" selected disabled>Seleccione...</option>
                    <option value="ingreso">Ingreso (+)</option>
                    <option value="egreso">Egreso (-)</option>
                </select>
            </div>

            <!-- CONCEPTO -->
            <div class="col-md-4 mb-2">
                <label>Concepto</label>
                <input type="text" name="concepto" class="form-control" required>
            </div>

            <!-- MONTO -->
            <div class="col-md-2 mb-2">
                <label>Monto</label>
                <input type="number" step="0.01" name="monto" id="monto" class="form-control">
            </div>

            <!-- BOTON -->
            <div class="col-md-2 mb-2 d-flex align-items-end">
                <button type="submit" class="btn btn-success w-100">
                    <i class="fas fa-save"></i> Guardar
                </button>
            </div>

        </div>


        <div class="row">

            <!-- PACIENTE -->
            <div class="col-md-4 mb-2">
                <label>Paciente</label>
                <select name="paciente_id" id="buscadorPacientes" class="form-control"></select>
            </div>

            <!-- PROFESIONAL -->
            <div class="col-md-4 mb-2">
                <label>Profesional</label>
                <select id="profesional" name="profesional_id" class="form-control">
                    <option value="">-- Opcional --</option>
                    <?php foreach ($profesionales as $p): ?>
                        <option value="<?= $p['id'] ?>">
                            <?= $p['apellido'] . ' ' . $p['nombre'] ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- PRACTICA -->
            <div class="col-md-4 mb-2">
                <label>Práctica</label>
                <select name="practica_id" id="practicas" class="form-control">
                    <option value="">-- Opcional --</option>
                </select>
            </div>

        </div>

    </form>
</div>

<div class="row mt-3">
    <div class="col-md-6">
        <div class="card card-outline card-success">
            <div class="card-header">
                <h5 class="mb-0 text-success"><i class="fas fa-arrow-down mr-2"></i>Ingresos Externos</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0 datatable">
                        <thead class="thead-dark">
                            <tr>
                                <th>Fecha</th>
                                <th>Concepto</th>
                                <th>Monto</th>
                                <th class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($ingresos)): ?>
                                <tr>
                                    <td class="text-center text-muted">No hay ingresos registrados</td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                </tr>
                            <?php endif; ?>
                            <?php foreach ($ingresos as $m): ?>
                                <tr class="<?= ($m['estado'] === 'anulado') ? 'table-danger' : '' ?>">
                                    <td class="small align-middle"><?= date('d/m/Y H:i', strtotime($m['fecha'])) ?></td>
                                    <td class="align-middle">
                                        <strong class="<?= ($m['estado'] === 'anulado') ? 'text-muted' : '' ?>">
                                            <?= htmlspecialchars($m['concepto']) ?>
                                        </strong><br>
                                        <small class="text-muted"><?= $m['destino_nombre'] ?? '-' ?></small>
                                    </td>
                                    <td class="align-middle font-weight-bold 
    <?= ($m['estado'] === 'anulado') ? 'text-danger' : 'text-success' ?>">

                                        <?= ($m['estado'] === 'anulado') ? '-' : '' ?>
                                        $<?= number_format($m['monto'], 2) ?>

                                        <?php if ($m['estado'] === 'anulado'): ?>
                                            <span class="badge badge-danger ml-2">ANULADO</span>
                                        <?php endif; ?>

                                    </td>
                                    <td class="text-center align-middle">
                                        <div class="btn-group">
                                            <button class="btn btn-info btn-sm ver-cobro rounded-circle" data-id="<?= $m['cobro_id'] ?>" title="Ver Detalle">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="btn btn-warning rounded-circle btn-sm btnImprimir"
                                                data-id="<?= $m['cobro_id'] ?>"
                                                title="Imprimir">
                                                <i class="fas fa-print text-white"></i>
                                            </button>
                                            <button class="btn btn-danger btn-sm btnEliminar rounded-circle" data-id="<?= $m['id'] ?>" title="Eliminar">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card card-outline card-danger">
            <div class="card-header">
                <h5 class="mb-0 text-danger"><i class="fas fa-arrow-up mr-2"></i>Egresos / Gastos</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0 datatable">
                        <thead class="thead-dark">
                            <tr>
                                <th>Fecha</th>
                                <th>Concepto</th>
                                <th>Monto</th>
                                <th class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($egresos)): ?>
                                <tr>
                                    <td class="text-center text-muted">No hay egresos registrados</td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                </tr>
                            <?php endif; ?>
                            <?php foreach ($egresos as $m): ?>
                                <tr class="<?= ($m['estado'] === 'anulado') ? 'table-danger' : '' ?>">
                                    <td class="small align-middle"><?= date('d/m/Y H:i', strtotime($m['fecha'])) ?></td>
                                    <td class="align-middle">
                                        <strong class="<?= ($m['estado'] === 'anulado') ? 'text-muted' : '' ?>">
                                            <?= htmlspecialchars($m['concepto']) ?>
                                        </strong><br>
                                        <small class="text-muted"><?= $m['destino_nombre'] ?? '-' ?></small>
                                    </td>
                                    <td class="align-middle font-weight-bold text-danger">

                                        -$<?= number_format($m['monto'], 2) ?>

                                        <?php if ($m['estado'] === 'anulado'): ?>
                                            <span class="badge badge-danger ml-2">ANULADO</span>
                                        <?php endif; ?>

                                    </td>
                                    <td class="text-center align-middle">
                                        <div class="btn-group">
                                            <button class="btn btn-info btn-sm ver-cobro rounded-circle" data-id="<?= $m['cobro_id'] ?>" title="Ver Detalle">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="btn btn-warning rounded-circle btn-sm btnImprimir"
                                                data-id="<?= $m['cobro_id'] ?>"
                                                title="Imprimir">
                                                <i class="fas fa-print text-white"></i>
                                            </button>
                                            <button class="btn btn-danger btn-sm btnEliminar rounded-circle" data-id="<?= $m['id'] ?>" title="Eliminar">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
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
<div class="modal fade" id="modalVerCobro" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="fas fa-info-circle mr-2"></i> Detalle del Movimiento</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" id="detalleCobroContenido">
                <div class="text-center p-4">
                    <i class="fas fa-spinner fa-spin fa-2x text-info"></i>
                    <p class="mt-2">Cargando información...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>
<script>
    $(document).ready(function() {
        // 1. Mantener la inicialización de Select2 para pacientes
        $('#buscadorPacientes').select2({
            theme: 'bootstrap4',
            placeholder: 'Escriba nombre...',
            minimumInputLength: 2,
            ajax: {
                url: 'ajax/buscar_pacientes_ajax.php',
                dataType: 'json',
                delay: 250,
                data: function(params) {
                    return {
                        q: params.term
                    };
                },
                processResults: function(data) {
                    return {
                        results: data
                    };
                },
                cache: true
            }
        });

        // 2. Manejo del Envío con SweetAlert2
        $('#formMovimientoManual').on('submit', function(e) {
            e.preventDefault();

            const form = $(this);
            const btn = form.find('button[type="submit"]');
            const originalText = btn.html();

            // Deshabilitar botón para evitar doble click
            btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Procesando...');

            $.ajax({
                url: 'ajax/guardar_movimiento.php',
                type: 'POST',
                data: form.serialize(),
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        // ÉXITO: Preguntar por el ticket
                        Swal.fire({
                            title: '¡Registro Exitoso!',
                            text: "El movimiento se guardó correctamente. ¿Desea imprimir el comprobante?",
                            icon: 'success',
                            showCancelButton: true,
                            confirmButtonColor: '#3085d6',
                            cancelButtonColor: '#d33',
                            confirmButtonText: '<i class="fas fa-print"></i> Imprimir Ticket',
                            cancelButtonText: 'No, finalizar',
                            allowOutsideClick: false
                        }).then((result) => {
                            if (result.isConfirmed) {

                                $.get('ajax/get_cobro_detalle.php', {
                                    cobro_id: response.cobro_id
                                }, function(res) {

                                    if (res.success) {
                                        imprimirTicketMovimiento(res.data);
                                    } else {
                                        Swal.fire('Error', res.message, 'error');
                                    }

                                    location.reload();

                                }, 'json');

                            } else {
                                location.reload();
                            }
                        });
                    } else {
                        // ERROR DE LÓGICA (ej: caja cerrada)
                        Swal.fire({
                            title: 'Error',
                            text: response.message,
                            icon: 'error',
                            confirmButtonText: 'Entendido'
                        });
                        btn.prop('disabled', false).html(originalText);
                    }
                },
                error: function(xhr) {
                    // ERROR DE SERVIDOR
                    Swal.fire({
                        title: 'Error de Sistema',
                        text: 'No se pudo procesar la solicitud. Revise la consola.',
                        icon: 'error'
                    });
                    console.error(xhr.responseText);
                    btn.prop('disabled', false).html(originalText);
                }
            });
        });
        // Manejo del botón Ver Detalle
        $(document).on('click', '.ver-cobro', function() {
            const cobroId = $(this).data('id');

            if (!cobroId) {
                Swal.fire('Aviso', 'Este movimiento no tiene un cobro asociado.', 'info');
                return;
            }

            // Limpiar contenido previo y mostrar modal
            $('#detalleCobroContenido').html('<div class="text-center p-4"><i class="fas fa-spinner fa-spin fa-2x text-info"></i><p>Cargando...</p></div>');
            $('#modalVerCobro').modal('show');

            // Llamada AJAX para obtener los datos
            $.ajax({
                url: 'ajax/obtener_detalle_movimiento.php',
                type: 'GET',
                data: {
                    id: cobroId
                },
                success: function(html) {
                    $('#detalleCobroContenido').html(html);
                },
                error: function() {
                    $('#detalleCobroContenido').html('<div class="alert alert-danger">Error al cargar los datos.</div>');
                }
            });
        });
        // 🗑 Eliminar movimiento
        $(document).on('click', '.btnEliminar', function() {

            const id = $(this).data('id');

            Swal.fire({
                title: 'Anular movimiento?',
                text: 'Esta acción no se puede deshacer',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Sí, anular',
                cancelButtonText: 'Cancelar'
            }).then((result) => {

                if (result.isConfirmed) {

                    $.ajax({
                        url: 'ajax/eliminar_movimiento.php',
                        type: 'POST',
                        data: {
                            id: id
                        },
                        dataType: 'json',

                        success: function(resp) {

                            if (resp.success) {

                                Swal.fire({
                                    icon: 'success',
                                    title: 'Anulado',
                                    text: resp.message,
                                    confirmButtonText: 'OK'
                                }).then(() => {
                                    location.reload(); // 🔥 recarga la página
                                });

                            } else {
                                Swal.fire('Error', resp.message, 'error');
                            }
                        },

                        error: function() {
                            Swal.fire('Error', 'Error de servidor', 'error');
                        }
                    });

                }
            });
        });
        // Inicializar DataTable usando la función global definida en index.php
        $('.datatable').each(function() {
            initDataTable($(this), {
                ordering: false,
                columnDefs: [{
                    targets: "_all",
                    defaultContent: ""
                }]
            });
        });
        $('#profesional').on('change', function() {

            let profesional_id = $(this).val();

            if (!profesional_id) {
                $('#practicas').html('<option value="">-- Sin prácticas --</option>');
                return;
            }

            $.get('ajax/get_practicas.php', {
                profesional_id
            }, function(res) {

                let html = '<option value="">-- Seleccione --</option>';

                res.data.forEach(p => {
                    html += `<option value="${p.Id}">${p.nombre}</option>`;
                });

                $('#practicas').html(html);

            }, 'json');

        });

        $('#practicas, #buscadorPacientes').on('change', function() {

            let practica_id = $('#practicas').val();
            let paciente_id = $('#buscadorPacientes').val();

            if (!practica_id) {
                $('#monto').prop('readonly', false).val('');
                return;
            }

            // 🔥 TRAER PRECIO
            $.get('ajax/get_precio.php', {
                practica_id,
                paciente_id
            }, function(res) {

                if (!res.success) {
                    $('#monto').prop('readonly', false).val('');
                    return;
                }

                let precio = parseFloat(res.precio || 0);

                // setea monto automático
                $('#monto')
                    .val(precio)
                    .prop('readonly', true);

                // opcional: setea concepto automático
                if (res.nombre) {
                    $('input[name="concepto"]').val(res.nombre);
                }

            }, 'json');

        });
        $('#monto').on('input', function() {

            let monto = parseFloat($(this).val() || 0);
            let practica_id = $('#practicas').val();

            if (!practica_id) return;

            $.get('ajax/practicas_reparto_get.php', {
                practica_id
            }, function(res) {

                if (!res.success) return;

                let html = '';

                res.reglas.forEach(r => {

                    let montoCalc = (monto * r.valor) / 100;

                    html += `
                <tr>
                    <td>${r.destino}</td>
                    <td>${r.valor}%</td>
                    <td>$ ${montoCalc.toFixed(2)}</td>
                </tr>
            `;
                });

                $('#previewReparto').html(html);

            }, 'json');

        });
        $('#profesional').select2({
            theme: 'bootstrap4',
            placeholder: 'Buscar profesional...',
            minimumInputLength: 2,
            ajax: {
                url: 'ajax/buscar_profesionales_ajax.php',
                dataType: 'json',
                delay: 250,
                data: function(params) {
                    return {
                        q: params.term
                    };
                },
                processResults: function(data) {
                    return {
                        results: data
                    };
                }
            }
        });
        async function imprimirTicketMovimiento(data) {
            try {
                if (!qz.websocket.isActive()) {
                    await qz.websocket.connect();
                }

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

                /* =========================
                   ENCABEZADO
                ========================= */
                contenido.push("\x1B\x61\x01");
                contenido.push("SALA RIVADAVIA\n");
                contenido.push("Av. Eva Peron 695\n");
                contenido.push("Temperley\n");
                contenido.push("------------------------------------------------\n");

                /* =========================
                   DATOS GENERALES
                ========================= */
                contenido.push("\x1B\x61\x00");

                contenido.push("Tipo: " + (data.tipo === 'ingreso' ? 'INGRESO' : 'EGRESO') + "\n");
                contenido.push("Fecha: " + data.fecha + "\n");

                if (data.paciente) {
                    contenido.push("Paciente: " + data.paciente + "\n");
                }

                if (data.profesional) {
                    contenido.push("Profesional: " + data.profesional + "\n");
                }

                contenido.push("------------------------------------------------\n");

                /* =========================
                   DETALLE
                ========================= */
                contenido.push("Concepto:\n");
                contenido.push(data.concepto + "\n");

                if (data.descripcion) {
                    contenido.push(data.descripcion + "\n");
                }

                contenido.push("------------------------------------------------\n");

                /* =========================
                   TOTAL
                ========================= */
                contenido.push("\x1B\x61\x02");

                let signo = data.tipo === 'egreso' ? "-" : "";

                contenido.push("TOTAL: " + signo + "$" + parseFloat(data.total).toFixed(2) + "\n");

                /* =========================
                   FOOTER
                ========================= */
                contenido.push("\x1B\x61\x01");
                contenido.push("\nComprobante de movimiento\n");
                contenido.push("Sistema de Caja\n");

                /* CORTE */
                contenido.push("\n\n\n");
                contenido.push("\x1D\x56\x00");

                qz.security.setCertificatePromise(function(resolve, reject) {
                    resolve(); // desarrollo sin cert
                });

                qz.security.setSignaturePromise(function(toSign) {
                    return function(resolve, reject) {
                        resolve(); // desarrollo sin firma
                    };
                });

                await qz.print(config, contenido);

            } catch (err) {
                console.error(err);
                alert("Error imprimiendo: " + err);
            }
        }
        $(document).on('click', '.btnImprimir', function() {

            let cobro_id = $(this).data('id');

            if (!cobro_id) {
                Swal.fire('Error', 'No hay cobro asociado', 'error');
                return;
            }

            $.get('ajax/get_cobro_detalle.php', {
                cobro_id
            }, function(res) {

                if (!res.success) {
                    Swal.fire('Error', res.message, 'error');
                    return;
                }

                imprimirTicketMovimiento(res.data); // 🔥 reutilizás la que YA funciona

            }, 'json');

        });
    });
</script>