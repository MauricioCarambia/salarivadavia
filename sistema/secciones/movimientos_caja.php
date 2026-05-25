<?php
require_once __DIR__ . '/../inc/db.php';

$usuario_id = $_SESSION['user_id'] ?? 0;

/* =========================
   🔐 ROL
========================= */
$stmt = $pdo->prepare("
    SELECT r.nombre
    FROM empleados e
    INNER JOIN roles r ON r.id = e.rol_id
    WHERE e.id = ?
");
$stmt->execute([$usuario_id]);

$rol = $stmt->fetchColumn();
$esAdmin = ($rol === 'Administrador');

/* =========================
   📅 FILTROS
========================= */
if ($esAdmin) {
    $desde = $_GET['desde'] ?? date('Y-m-d');
    $hasta = $_GET['hasta'] ?? date('Y-m-d');
} else {
    $desde = date('Y-m-d');
    $hasta = date('Y-m-d');
}

$desdeSQL = $desde . " 00:00:00";
$hastaSQL = $hasta . " 23:59:59";

$caja_id = $_GET['caja_id'] ?? '';
$turno = $_GET['turno'] ?? '';
$empleado_id = $_GET['empleado_id'] ?? '';

/* =========================
   🧠 WHERE
========================= */
$where = "WHERE c.fecha BETWEEN ? AND ?";
$params = [$desdeSQL, $hastaSQL];

if ($caja_id) {
    $where .= " AND cs.caja_id = ?";
    $params[] = $caja_id;
}

if ($turno) {
    $where .= " AND cs.turno = ?";
    $params[] = $turno;
}

if ($empleado_id) {
    $where .= " AND cs.usuario_id = ?";
    $params[] = $empleado_id;
}

/* =========================
   📊 MOVIMIENTOS
========================= */
$stmt = $pdo->prepare("
    SELECT 
        c.id,
        c.tipo,
        c.total as monto,
        c.fecha,
        c.concepto,
        c.numero_completo,
        c.estado,
        c.medio_pago,
        c.empleado_destino_id,
        ed.nombre AS empleado_destino_nombre,
        GROUP_CONCAT(DISTINCT d.nombre SEPARATOR ', ') AS destino_nombre
    FROM cobros c
    LEFT JOIN caja_sesion cs ON cs.id = c.caja_sesion_id
    LEFT JOIN cobros_reparto cr ON cr.cobro_id = c.id
    LEFT JOIN destinos_reparto d ON d.id = cr.destino_id
    LEFT JOIN empleados ed ON ed.id = c.empleado_destino_id
    $where
    GROUP BY c.id
    ORDER BY c.fecha DESC
");

$stmt->execute($params);
$movimientos = $stmt->fetchAll(PDO::FETCH_ASSOC);
/* =========================
   🎯 DESTINOS
========================= */
$stmt = $pdo->query("
    SELECT id, nombre, tipo
    FROM destinos_reparto
    ORDER BY nombre ASC
");
$destinos = $stmt->fetchAll(PDO::FETCH_ASSOC);
$ingresos = array_filter($movimientos, fn($m) => $m['tipo'] === 'ingreso');
$egresos  = array_filter($movimientos, fn($m) => $m['tipo'] === 'egreso');

/* =========================
   👨‍⚕️ PROFESIONALES
========================= */
$stmt = $pdo->query("
    SELECT id, nombre, apellido
    FROM profesionales
    ORDER BY apellido ASC, nombre ASC
");
$profesionales = $stmt->fetchAll(PDO::FETCH_ASSOC);

function obtenerTipoPaciente(PDO $pdo, int $paciente_id): array
{
    $stmt = $pdo->prepare("
        SELECT tipo_socio, fecha_alta
        FROM pacientes
        WHERE Id = ?
    ");
    $stmt->execute([$paciente_id]);

    $p = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$p) {
        return ['tipo' => 'particular', 'cobra_como' => 'particular'];
    }

    // 🔥 NUEVO (primer mes)
    if (!empty($p['fecha_alta'])) {

        $alta = new DateTime($p['fecha_alta']);
        $actual = new DateTime();

        $diff = $alta->diff($actual);

        if ($diff->y == 0 && $diff->m < 1) {
            return ['tipo' => 'nuevo', 'cobra_como' => 'particular'];
        }
    }

    // 🔥 VITALICIO (valor específico)
    if (!empty($p['tipo_socio']) && strtolower($p['tipo_socio']) === 'vitalicio') {
        return ['tipo' => 'vitalicio', 'cobra_como' => 'socio'];
    }

    // 🔥 SOCIO NORMAL (validar deuda)
    $stmt = $pdo->prepare("
        SELECT MAX(fecha_correspondiente)
        FROM pagos_afiliados
        WHERE paciente_id = ?
    ");
    $stmt->execute([$paciente_id]);

    $ultimaFecha = $stmt->fetchColumn();

    if (!$ultimaFecha) {
        return ['tipo' => 'particular', 'cobra_como' => 'particular'];
    }

    $ultimo = new DateTime(date('Y-m-01', strtotime($ultimaFecha)));
    $actual = new DateTime(date('Y-m-01'));

    $diff = $ultimo->diff($actual);
    $meses = ($diff->y * 12) + $diff->m;

    if ($meses <= 3) {
        return ['tipo' => 'socio', 'cobra_como' => 'socio'];
    }

    return ['tipo' => 'moroso', 'cobra_como' => 'particular'];
}
?>
<div class="card card-outline card-info mb-3 p-3">
    <form method="GET" class="row">
        <input type="hidden" name="seccion" value="<?= $_GET['seccion'] ?? '' ?>">
        <?php if ($esAdmin): ?>
            <div class="col-md-2">
                <label>Desde</label>
                <input type="date" name="desde" value="<?= $desde ?>" class="form-control" <?= !$esAdmin ? 'readonly' : '' ?>>
            </div>

            <div class="col-md-2">
                <label>Hasta</label>
                <input type="date" name="hasta" value="<?= $hasta ?>" class="form-control" <?= !$esAdmin ? 'readonly' : '' ?>>
            </div>
        <?php endif; ?>
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
            <!-- DESTINO -->
            <div class="col-md-2 mb-2">
                <label>Destino</label>
                <select name="destino_id" id="destino" class="form-control">
                    <option value="">-- Seleccionar destino --</option>
                    <?php foreach ($destinos as $d): ?>
                        <option value="<?= $d['id'] ?>" data-tipo="<?= $d['tipo'] ?>"><?= $d['nombre'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <!-- <div class="col-md-2 mb-2">
                <label>Origen del dinero</label>
                <select name="origen" id="origen" class="form-control" required>
                    <option value="caja">Caja del día</option>
                    <option value="fondo">Fondo acumulado</option>
                </select>
            </div> -->
            <!-- CONCEPTO -->
            <div class="col-md-2 mb-2">
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
            <div class="col-md-3 mb-2">
                <label>Paciente</label>
                <select name="paciente_id" id="buscadorPacientes" class="form-control"></select>
            </div>

            <!-- PROFESIONAL -->
            <div class="col-md-3 mb-2">
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
            <div class="col-md-2 mb-2">
                <label>Práctica</label>
                <select name="practica_id" id="practicas" class="form-control">
                    <option value="">-- Opcional --</option>
                </select>
            </div>
            <div class="col-md-2 mb-2">
                <label>Medio de Pago</label>
                <select name="medio_pago" id="medio_pago" class="form-control" required>
                    <option value="efectivo">Efectivo</option>
                    <option value="transferencia">Transferencia</option>
                </select>
            </div>
            <div class="col-md-2 mb-2" id="boxEmpleadoDestino" style="display:none;">
                <label>Empleado Destino</label>
                <select name="empleado_destino_id" class="form-control">
                    <option value="">Seleccione...</option>
                    <?php foreach ($pdo->query("SELECT id, nombre FROM empleados")->fetchAll() as $e): ?>
                        <option value="<?= $e['id'] ?>">
                            <?= $e['nombre'] ?>
                        </option>
                    <?php endforeach; ?>
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
                                <th>Comprobante</th>
                                <th>Medio</th>
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
                                    <td class="small align-middle"><?= $m['numero_completo'] ?></td>
                                    <td class="small align-middle">

                                        <?php if ($m['medio_pago'] === 'efectivo'): ?>
                                            <span class="badge badge-success">Efectivo</span>
                                        <?php endif; ?>

                                        <?php if ($m['medio_pago'] === 'transferencia'): ?>
                                            <span class="badge badge-info">
                                                Transferencia → <?= $m['empleado_destino_nombre'] ?? 'Sin empleado' ?>
                                            </span>
                                        <?php endif; ?>
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
                                            <button class="btn btn-info btn-sm ver-cobro rounded-circle" data-id="<?= $m['id'] ?>" title="Ver Detalle">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="btn btn-warning rounded-circle btn-sm btnImprimir"
                                                data-id="<?= $m['id'] ?>"
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
                                <th>Comprobante</th>
                                <th>Medio</th>
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
                                    <td class="small align-middle"><?= $m['numero_completo'] ?></td>
                                    <td class="small align-middle">

                                        <?php if ($m['medio_pago'] === 'efectivo'): ?>
                                            <span class="badge badge-success">Efectivo</span>
                                        <?php endif; ?>

                                        <?php if ($m['medio_pago'] === 'transferencia'): ?>
                                            <span class="badge badge-info">
                                                Transferencia → <?= $m['empleado_destino_nombre'] ?? 'Sin empleado' ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="align-middle font-weight-bold text-danger">

                                        -$<?= number_format($m['monto'], 2) ?>

                                        <?php if ($m['estado'] === 'anulado'): ?>
                                            <span class="badge badge-danger ml-2">ANULADO</span>
                                        <?php endif; ?>

                                    </td>
                                    <td class="text-center align-middle">
                                        <div class="btn-group">
                                            <button class="btn btn-info btn-sm ver-cobro rounded-circle" data-id="<?= $m['id'] ?>" title="Ver Detalle">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button
                                                class="btn btn-warning rounded-circle btn-sm btnImprimir"
                                                data-id="<?= $m['id'] ?>"
                                                data-paciente="<?= htmlspecialchars($m['pac_nom'] . ' ' . $m['pac_ape']) ?>"
                                                data-profesional="<?= htmlspecialchars($m['prof_nom'] . ' ' . $m['prof_ape']) ?>"
                                                data-total="<?= $m['monto'] ?>"
                                                data-concepto="<?= htmlspecialchars($m['concepto']) ?>"
                                                data-estado="<?= $m['estado'] ?>"
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

        /* =========================
           🔎 SELECT2 PACIENTES
        ========================= */
        $('#buscadorPacientes').select2({
            theme: 'bootstrap4',
            placeholder: 'Buscar paciente...',
            minimumInputLength: 2,
            ajax: {
                url: 'ajax/buscar_pacientes_ajax.php',
                dataType: 'json',
                delay: 250,
                data: params => ({
                    q: params.term
                }),
                processResults: data => ({
                    results: data
                }),
                cache: true
            }
        });

        /* =========================
           🔎 SELECT2 PROFESIONALES
        ========================= */
        $('#profesional').select2({
            theme: 'bootstrap4',
            placeholder: 'Buscar profesional...',
            minimumInputLength: 2,
            ajax: {
                url: 'ajax/buscar_profesionales_ajax.php',
                dataType: 'json',
                delay: 250,
                data: params => ({
                    q: params.term
                }),
                processResults: data => ({
                    results: data
                })
            }
        });

        /* =========================
           💾 SUBMIT
        ========================= */
        $('#formMovimientoManual').on('submit', function(e) {
            e.preventDefault();

            let tipo = $('select[name="tipo"]').val();
            let monto = parseFloat($('#monto').val() || 0);

            guardar(); // 👈 SIEMPRE guarda

            function guardar() {
                $.post('ajax/guardar_movimiento.php', $('#formMovimientoManual').serialize(), function(res) {

                    if (res.success) {
                        Swal.fire('OK', 'Movimiento guardado', 'success')
                            .then(() => location.reload());
                    } else {
                        Swal.fire('Error', res.message, 'error');
                    }

                }, 'json');
            }
        });

        /* =========================
           👁 VER DETALLE
        ========================= */
        $(document).on('click', '.ver-cobro', function() {

            let id = $(this).data('id');

            $('#modalVerCobro').modal('show');

            $.get('ajax/obtener_detalle_movimiento.php', {
                id
            }, function(html) {
                $('#detalleCobroContenido').html(html);
            });
        });

        /* =========================
           🗑 ANULAR
        ========================= */
        $(document).on('click', '.btnEliminar', function() {

            let id = $(this).data('id');

            Swal.fire({
                title: '¿Anular?',
                icon: 'warning',
                showCancelButton: true
            }).then(r => {

                if (!r.isConfirmed) return;

                $.post('ajax/eliminar_movimiento.php', {
                    id
                }, function(res) {

                    if (res.success) {
                        location.reload();
                    } else {
                        Swal.fire('Error', res.message, 'error');
                    }

                }, 'json');
            });
        });

        /* =========================
           📊 DATATABLE
        ========================= */
        $('.datatable').each(function() {
            initDataTable($(this), {
                ordering: false
            });
        });

        /* =========================
           🧠 PROFESIONAL → PRACTICAS
        ========================= */
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

        /* =========================
           💰 PRECIO AUTOMATICO
        ========================= */
        $('#practicas, #buscadorPacientes').on('change', function() {

            let practica_id = $('#practicas').val();
            let paciente_id = $('#buscadorPacientes').val();

            if (!practica_id) {
                $('#monto').prop('readonly', false).val('');
                return;
            }

            $.get('ajax/get_precio.php', {
                practica_id,
                paciente_id
            }, function(res) {

                if (!res.success) {
                    $('#monto').prop('readonly', false).val('');
                    return;
                }

                $('#monto')
                    .val(parseFloat(res.precio))
                    .prop('readonly', true);

                if (res.nombre) {
                    $('input[name="concepto"]').val(res.nombre);
                }

            }, 'json');
        });

        /* =========================
           🔁 DESTINO / PRACTICA
        ========================= */
        function toggleDestino() {
            let practica = $('#practicas').val();

            if (practica) {
                // Si hay práctica → bloquea destino
                $('#destino').prop('disabled', true);
            } else {
                // Si NO hay práctica → habilita destino
                $('#destino').prop('disabled', false);
            }
        }

        function filtrarDestinos() {
            let tipo = $('select[name="tipo"]').val();

            $('#destino option').each(function() {

                let t = $(this).data('tipo');

                if (!t) return;

                // 👉 SI NO HAY TIPO, MOSTRAR TODO
                if (!tipo) {
                    $(this).show();
                } else {
                    $(this).toggle(t === tipo);
                }

            });

            $('#destino').val('');
        }

        $('#practicas').on('change', toggleDestino);
        $('select[name="tipo"]').on('change', filtrarDestinos);

        /* =========================
           💳 MEDIO DE PAGO
        ========================= */
        $('#medio_pago').on('change', function() {

            let esTransferencia = $(this).val() === 'transferencia';

            $('#boxEmpleadoDestino').toggle(esTransferencia);

            if (!esTransferencia) {
                $('select[name="empleado_destino_id"]').val('');
            }

        }).trigger('change');

        /* =========================
           📝 AUTO CONCEPTO
        ========================= */
        $('#destino').on('change', function() {

            let texto = $(this).find('option:selected').text().trim();

            if (texto && texto !== '-- Seleccionar destino --') {
                $('input[name="concepto"]').val(texto);
            }
        });

        /* =========================
           🚀 INIT
        ========================= */
        toggleDestino();
        filtrarDestinos();

    });
    /* =========================
       🖨 IMPRIMIR COMPROBANTE
    ========================= */
    $(document).on('click', '.btnImprimir', async function() {

        let btn = $(this);

        // 🔥 ARMAMOS DATA DIRECTO DEL BOTÓN
        let data = {
            paciente: btn.data('paciente') || '---',
            profesional: btn.data('profesional') || '---',
            total: btn.data('total') || 0,
            estado: btn.data('estado') || 'activo',
            detalle: [{
                nombre: btn.data('concepto') || 'Movimiento',
                precio: btn.data('total') || 0
            }]
        };

        try {

            if (!qz.websocket.isActive()) {
                await qz.websocket.connect();
            }

            const config = qz.configs.create("POS-80C", {
                encoding: 'CP437'
            });

            let contenido = [];

            function linea(nombre, precio) {
                let left = (nombre || '').substring(0, 30);
                let right = "$" + parseFloat(precio).toFixed(2);

                let spaces = 48 - (left.length + right.length);
                if (spaces < 1) spaces = 1;

                return left + " ".repeat(spaces) + right;
            }

            /* =========================
               🧾 ENCABEZADO
            ========================= */
            contenido.push("\x1B\x61\x01");

            contenido.push({
                type: 'pixel',
                format: 'png',
                flavor: 'file',
                data: 'images/logo_blanco_negro.png',
                options: {
                    language: "ESCPOS",
                    dotDensity: "double"
                }
            });

            contenido.push("\n");
            contenido.push("SALA RIVADAVIA\n");
            contenido.push("Av. Eva Peron 695\n");
            contenido.push("Temperley\n");
            contenido.push("Fecha: " + new Date().toLocaleString() + "\n");
            contenido.push("------------------------------------------------\n");

            /* =========================
               📄 DATOS
            ========================= */
            contenido.push("\x1B\x61\x00");
            contenido.push("Paciente: " + data.paciente + "\n");
            contenido.push("Profesional: " + data.profesional + "\n");
            contenido.push("------------------------------------------------\n");

            /* =========================
               📦 DETALLE
            ========================= */
            data.detalle.forEach(d => {
                contenido.push(linea(d.nombre, d.precio) + "\n");
            });

            contenido.push("------------------------------------------------\n");

            /* =========================
               💰 TOTAL
            ========================= */
            contenido.push("\x1B\x61\x02");
            contenido.push("TOTAL: $" + parseFloat(data.total).toFixed(2) + "\n");

            /* =========================
               🔴 ANULADO
            ========================= */
            if (data.estado === 'anulado') {
                contenido.push("\x1B\x61\x01");
                contenido.push("***************\n");
                contenido.push("   ANULADO\n");
                contenido.push("***************\n");
            }

            contenido.push("\x1B\x61\x01");
            contenido.push("\nGracias por su visita\n");

            /* =========================
               ✂️ CORTE
            ========================= */
            contenido.push("\n\n\n");
            contenido.push("\x1D\x56\x00");

            await qz.print(config, contenido);

        } catch (err) {
            console.error(err);
            alert("Error imprimiendo: " + err);
        }

    });
</script>