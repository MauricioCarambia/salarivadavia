<?php
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/services/CajaService.php';

$cajaService = new CajaService($pdo);
date_default_timezone_set('America/Argentina/Buenos_Aires');

/* =================================
    👤 USUARIO Y ROL
================================= */
$usuarioSesionId = $_SESSION['user_id'] ?? 0;

$stmt = $pdo->prepare("
    SELECT r.nombre as rol
    FROM empleados e
    INNER JOIN roles r ON r.id = e.rol_id
    WHERE e.id = ?
");
$stmt->execute([$usuarioSesionId]);
$rol = $stmt->fetchColumn();
$esAdmin = ($rol === 'Administrador');

// ==============================
// 🟢 CAJA ACTIVA DEL USUARIO
// ==============================
$stmt = $pdo->prepare("
    SELECT caja_id
    FROM caja_sesion
    WHERE usuario_id = ? AND estado = 'abierta'
    LIMIT 1
");
$stmt->execute([$usuarioSesionId]);
$cajaActiva = $stmt->fetchColumn();

/* =================================
    📅 FILTROS
================================= */
$desde = ($esAdmin && isset($_GET['desde'])) ? $_GET['desde'] : date('Y-m-d');
$hasta = ($esAdmin && isset($_GET['hasta'])) ? $_GET['hasta'] : date('Y-m-d');

$caja_id       = $_GET['caja_id'] ?? $cajaActiva;
$turno_sesion  = $_GET['turno'] ?? null;
$usuarioFiltro = $_GET['usuario_id'] ?? $usuarioSesionId;

$filtros = [
    'desde' => $desde,
    'hasta' => $hasta,
    'caja_id' => $caja_id ?: null,
    'turno' => $turno_sesion ?: null,
    'usuario_id' => $usuarioFiltro ?: null
];

$data = $cajaService->getResumen($filtros);


// 💵 CAJA
$ingEfectivoReal     = $data['caja']['ingresos'];
$egrEfectivo         = $data['caja']['egresos'];
$egrProfesional      = $data['caja']['egresos_profesional'];
$balanceFisico       = $data['caja']['balance'];
$subtotalSinManuales = $ingEfectivoReal - $egrProfesional;

// 🏦 BANCO
$ingTransferenciaReal     = $data['banco']['ingresos'];
$egrTransferencia         = $data['banco']['egresos'];
$egrTransferenciaProf     = $data['banco']['egresos_profesional'];
$balanceBanco             = $data['banco']['balance'];
$subtotalTransfSinManuales = $ingTransferenciaReal - $egrTransferenciaProf;

// 📊 DESTINOS
$destinosActualizados = $data['destinos'];

// 📋 MOVIMIENTOS
$tablaTurnos = $data['turnos'];
$externos    = $data['externos'];

// 👤 DEUDAS
$deudasProfesionales = $data['deudas'];

// 💳 TRANSFERENCIAS EMPLEADOS (🔥 lo que te faltaba)
$transferenciasEmpleados = $data['transferencias'];
function formatearMedioPago(
    $medio,
    $transferenciaTipo = '',
    $empleadoDestino = '',
    $profNombre = '',
    $profApellido = ''
) {

    if ($medio === 'efectivo') {
        return '<span class="badge badge-success">Efectivo</span>';
    }

    if ($medio === 'transferencia') {

        $html = '<span class="badge badge-primary">Transferencia</span>';

        if ($transferenciaTipo === 'clinica') {

            $html .= '<br><small class="badge badge-info">'
                . htmlspecialchars($empleadoDestino ?: '-')
                . '</small>';
        }

        if ($transferenciaTipo === 'profesional') {

            $nombreCompleto = trim($profApellido . ' ' . $profNombre);

            $html .= '<br><small class="badge badge-warning">'
                . 'Cobrado por '
                . htmlspecialchars($nombreCompleto ?: '-')
                . '</small>';
        }

        return $html;
    }

    return '<span class="badge badge-secondary">-</span>';
}
?>

<div class="card card-outline card-info p-3 mb-3">
    <form method="get" class="row">
        <input type="hidden" name="seccion" value="caja">
        <?php if ($esAdmin): ?>
            <div class="col-md-2"><label>Desde</label><input type="date" name="desde" value="<?= $desde ?>" class="form-control"></div>
            <div class="col-md-2"><label>Hasta</label><input type="date" name="hasta" value="<?= $hasta ?>" class="form-control"></div>
        <?php endif; ?>
        <div class="col-md-2">
            <label>Caja</label>
            <select name="caja_id" class="form-control">
                <option value="">Todas</option>
                <?php foreach ($pdo->query("SELECT id, nombre FROM cajas")->fetchAll() as $cj): ?>
                    <option value="<?= $cj['id'] ?>" <?= $caja_id == $cj['id'] ? 'selected' : '' ?>><?= $cj['nombre'] ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label>Turno</label>
            <select name="turno" class="form-control">
                <option value="">Todos</option>
                <option value="mañana" <?= $turno_sesion == 'mañana' ? 'selected' : '' ?>>Mañana</option>
                <option value="tarde" <?= $turno_sesion == 'tarde' ? 'selected' : '' ?>>Tarde</option>
            </select>
        </div>
        <div class="col-md-2">
            <label>Empleado</label>
            <select name="usuario_id" class="form-control">
                <option value="">Todos</option>
                <?php foreach ($pdo->query("SELECT id, nombre FROM empleados ORDER BY nombre")->fetchAll() as $emp): ?>
                    <option value="<?= $emp['id'] ?>" <?= $usuarioFiltro == $emp['id'] ? 'selected' : '' ?>><?= htmlspecialchars($emp['nombre']) ?></option>
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

<div class="row mb-3">

    <!-- 💵 CAJA FÍSICA -->
    <div class="col-md-6">
        <div class="card bg-dark text-white shadow text-center p-3">
            <h5>
                <i class="fas fa-cash-register mr-2"></i> EFECTIVO EN CAJA
            </h5>

            <h2 class="display-4 font-weight-bold">
                $<?= number_format($balanceFisico, 2, ',', '.') ?>
            </h2>

            <div class="d-flex justify-content-around mt-2">
                <small class="text-success">
                    Ingresos Efec: $<?= number_format($ingEfectivoReal, 2, ',', '.') ?>
                </small>

                <small class="text-danger">
                    Egresos Efec: $<?= number_format($egrEfectivo, 2, ',', '.') ?>
                </small>
            </div>

            <hr class="w-100 my-2">

            <small class="text-warning">
                Subtotal (Total ingresos - egresos profesionales): $<?= number_format($subtotalSinManuales, 2, ',', '.') ?>
            </small>
        </div>
    </div>

    <!-- 🏦 BANCO -->
    <div class="col-md-6">
        <div class="card bg-info text-white shadow text-center p-3">
            <h5>
                <i class="fas fa-university mr-2"></i> TRANSFERENCIAS
            </h5>

            <h2 class="display-4 font-weight-bold">
                $<?= number_format($balanceBanco, 2, ',', '.') ?>
            </h2>

            <div class="d-flex justify-content-around mt-2">
                <small>
                    Entró: $<?= number_format($ingTransferenciaReal, 2, ',', '.') ?>
                </small>

                <small>
                    Salió: $<?= number_format($egrTransferencia, 2, ',', '.') ?>
                </small>
            </div>

            <hr class="w-100 my-2">

            <small class="text-light">
                Subtotal (transferencia sala - egresos profesionales): $<?= number_format($subtotalTransfSinManuales, 2, ',', '.') ?>
            </small>
        </div>
    </div>

</div>

<div class="row mb-4">
    <?php foreach ($destinosActualizados as $d): ?>

        <?php
        $tipo = strtolower($d['tipo'] ?? '');
        $categoria = strtolower($d['categoria'] ?? '');

        if ($tipo === 'egreso') {
            $label = 'EGRESO';
            $colorClase = 'danger';
            $textColor = 'text-danger';
        } elseif ($categoria === 'fondo') {
            $label = 'FONDO';
            $colorClase = 'primary';
            $textColor = 'text-primary';
        } else {
            $label = 'INGRESO';
            $colorClase = 'success';
            $textColor = 'text-success';
        }
        ?>

        <div class="col-md-3 mb-2">
            <div class="card shadow-sm border-left-<?= $colorClase ?> h-100">
                <div class="card-body p-2">

                    <div class="d-flex justify-content-between align-items-start">
                        <small class="text-muted font-weight-bold text-uppercase">
                            <?= htmlspecialchars($d['nombre']) ?>
                        </small>

                        <span class="badge badge-<?= $colorClase ?> text-white">
                            <?= $label ?>
                        </span>
                    </div>

                    <div class="h5 mb-1 <?= $textColor ?>">
                        $<?= number_format($d['total_general'], 2, ',', '.') ?>
                    </div>

                    <div class="d-flex justify-content-between" style="font-size: 0.75rem;">
                        <span class="text-success">
                            Efec: $<?= number_format($d['total_efectivo'], 2, ',', '.') ?>
                        </span>

                        <span class="text-info">
                            Trans: $<?= number_format($d['total_transferencia'], 2, ',', '.') ?>
                        </span>
                    </div>

                </div>
            </div>
        </div>

    <?php endforeach; ?>
</div>
<!-- <div class="row mb-4">
<?php foreach ($deudasProfesionales as $p): ?>
    <div class="col-md-3 mb-2">
        <div class="card shadow-sm border-left-warning h-100">
            <div class="card-body p-2">

                <div class="d-flex justify-content-between align-items-start">
                    <small class="text-muted font-weight-bold text-uppercase">
                        <?= htmlspecialchars($p['apellido'] . ' ' . $p['nombre']) ?>
                    </small>

                    <span class="badge badge-warning">
                        DEUDA
                    </span>
                </div>

                <div class="h5 mb-1 text-warning">
                    $<?= number_format($p['deuda_total'], 2, ',', '.') ?>
                </div>

                <small class="text-muted">
                    Debe a la clínica
                </small>

            </div>
        </div>
    </div>
<?php endforeach; ?>
</div> -->
<div class="row mb-4">

    <?php if (empty($transferenciasEmpleados)): ?>
        <div class="col-12">
            <div class="alert alert-light text-center">
                No hay transferencias a empleados en este período
            </div>
        </div>
    <?php endif; ?>

    <?php foreach ($transferenciasEmpleados as $emp): ?>

        <div class="col-md-3 mb-2">
            <div class="card shadow-sm border-left-info h-100">
                <div class="card-body p-2">

                    <div class="d-flex justify-content-between align-items-start">
                        <small class="text-muted font-weight-bold text-uppercase">
                            <?= htmlspecialchars($emp['nombre']) ?>
                        </small>

                        <span class="badge badge-info">
                            TRANSFERENCIA
                        </span>
                    </div>

                    <div class="h5 mb-1 text-info">
                        $<?= number_format($emp['total_transferencias'], 2, ',', '.') ?>
                    </div>

                    <small class="text-muted">
                        Total recibido por transferencia
                    </small>

                </div>
            </div>
        </div>

    <?php endforeach; ?>

</div>

<div class="card card-outline card-primary">
    <div class="card-header"><strong>Listado de Movimientos</strong></div>
    <div class="card-body p-0">
        <table class="table table-sm table-striped tabla">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Concepto / Comprobante</th>
                    <th>Paciente</th>
                    <th>Medio</th>
                    <th>Practica</th>
                    <th>Monto</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>

                <?php
                $movimientos = array_merge($tablaTurnos, $externos);

                // 🔥 Ordenar por fecha DESC (fecha + hora)
                usort($movimientos, function ($a, $b) {
                    return strtotime($b['fecha']) - strtotime($a['fecha']);
                });
                foreach ($movimientos as $m): ?>
                    <tr>

                        <td><?= date('d/m H:i', strtotime($m['fecha'])) ?></td>
                        <td>
                            <?= $m['numero_completo'] ?? $m['concepto'] ?>
                            <?= ($m['estado'] == 'anulado') ? '<span class="badge badge-danger">Anulado</span>' : '' ?>
                        </td>
                        <td>


                            <?php if (!empty($m['pac_nom'])): ?>


                                <?= htmlspecialchars($m['pac_ape'] . ' ' . $m['pac_nom']) ?>

                            <?php endif; ?>
                        </td>

                        <td>
                            <?= formatearMedioPago(
                                $m['medio_pago'],
                                $m['transferencia_tipo'] ?? '',
                                $m['emp_dest'] ?? '',
                                $m['prof_nom'] ?? '',
                                $m['prof_ape'] ?? ''
                            ) ?>
                        </td>
                        <td>
                            <?= htmlspecialchars(
                                $m['concepto']
                                    ?? $m['practica_nombre']
                                    ?? $m['numero_completo']
                                    ?? '-'
                            ) ?>
                        </td>
                        <?php
                        $esEgreso = ($m['tipo'] ?? '') == 'egreso';
                        ?>

                        <td class="font-weight-bold <?= $esEgreso ? 'text-danger' : 'text-success' ?>">
                            <?= $esEgreso ? '-' : '' ?>$<?= number_format($m['monto'], 2) ?>
                        </td>
                        <td>
                            <button class="btn btn-info btn-xs ver-cobro rounded-circle" data-id="<?= $m['cobro_id'] ?>">
                                <i class="fas fa-eye"></i>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>


<div class="modal fade" id="verCobroModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-light">
                <h5 class="modal-title text-muted">
                    <i class="fas fa-file-invoice-dollar mr-2"></i>Detalle de Comprobante
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-4" id="detalleCobroContenido">
            </div>
            <div class="modal-footer bg-light border-0">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cerrar</button>
                <button type="button" class="btn btn-primary btn-sm" id="btnImprimirDesdeModal">
                    <i class="fas fa-print"></i> Imprimir
                </button>
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
    window.imprimirTicket = async function(data) {

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

            function right(txt) {
                return "\x1B\x61\x02" + txt + "\n";
            }

            function separator() {
                return "------------------------------------------------\n";
            }

            function linea(nombre, precio) {

                let izquierda = String(nombre || '').substring(0, 28);

                let derecha =
                    "$ " +
                    parseFloat(precio || 0).toLocaleString(
                        "es-AR", {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2
                        }
                    );

                let espacios =
                    48 - (izquierda.length + derecha.length);

                if (espacios < 1) espacios = 1;

                return izquierda +
                    " ".repeat(espacios) +
                    derecha;
            }

            const ahora = new Date();

            const fecha = ahora.toLocaleDateString("es-AR");
            const hora = ahora.toLocaleTimeString("es-AR");

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

            contenido.push(center(
                "COMPROBANTE N° " +
                (data.comprobante || "")
            ));

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
               FECHA
            ===================================== */

            contenido.push(left("FECHA: " + fecha));
            contenido.push(left("HORA : " + hora));

            contenido.push("\n");

            contenido.push(center("CONSUMIDOR FINAL"));

            contenido.push(separator());

            /* =====================================
               PACIENTE
            ===================================== */

            contenido.push(left("PACIENTE:"));
            contenido.push(left(data.paciente || "-"));

            contenido.push("\n");

            contenido.push(left("PROFESIONAL:"));
            contenido.push(left(data.profesional || "-"));

            contenido.push(separator());

            /* =====================================
               DETALLE
            ===================================== */

            contenido.push(left("DESCRIPCION"));

            contenido.push("\n");

            if (Array.isArray(data.detalle)) {

                data.detalle.forEach(item => {

                    contenido.push(
                        left(
                            linea(
                                item.nombre,
                                item.precio
                            )
                        )
                    );

                });

            }

            contenido.push(separator());

            /* =====================================
               TOTAL
            ===================================== */

            contenido.push("\x1B\x45\x01");
            contenido.push("\x1D\x21\x11");

            contenido.push(center("TOTAL"));

            contenido.push(
                right(
                    "$ " +
                    parseFloat(data.total || 0)
                    .toLocaleString(
                        "es-AR", {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2
                        }
                    )
                )
            );

            contenido.push("\x1D\x21\x00");
            contenido.push("\x1B\x45\x00");

            contenido.push(separator());

            /* =====================================
               PIE
            ===================================== */

            contenido.push("\n");

            contenido.push(center(
                "¡GRACIAS POR ELEGIRNOS!"
            ));

            contenido.push("\n");

            contenido.push(separator());
            contenido.push(separator());

            contenido.push(center(
                "DOCUMENTO NO VALIDO COMO FACTURA"
            ));

            contenido.push("\n\n\n\n");

            /* =====================================
               CORTE
            ===================================== */

            contenido.push("\x1D\x56\x00");

            await qz.print(config, contenido);

        } catch (err) {

            console.error(err);

            throw err;
        }
    };
    $(document).ready(function() {

        /* =========================
           📊 DATATABLE
        ========================= */
        if (typeof initDataTable === "function") {
            $('.tabla').each(function() {
                initDataTable($(this), {
                    pageLength: 10,
                    order: [
                        [0, "desc"]
                    ],
                    columnDefs: [{
                        targets: 0,
                        type: "date"
                    }]
                });
            });
        }

        /* =========================
           👁️ VER COBRO
        ========================= */
        $(document).on("click", ".ver-cobro", function() {

            const cobroId = $(this).data("id");

            if (!cobroId) {
                alert("Este movimiento no tiene comprobante asociado");
                return;
            }

            // Loader
            $("#detalleCobroContenido").html(`
            <div class="text-center py-5">
                <div class="spinner-border text-primary"></div>
                <p class="mt-2 text-muted">Buscando información...</p>
            </div>
        `);

            $("#verCobroModal").modal("show");

            // 🔥 UNA SOLA LLAMADA
            $.get("ajax/get_cobro.php", {
                cobro_id: cobroId
            }, function(res) {

                if (!res.success) {
                    $("#detalleCobroContenido").html(`
                    <div class="alert alert-danger">
                        ${res.message}
                    </div>
                `);
                    return;
                }

                const {
                    cobro,
                    detalle,
                    reparto
                } = res;

                /* =========================
                   🖨️ BOTÓN IMPRIMIR
                ========================= */
                $("#btnImprimirDesdeModal")
                    .off('click')
                    .on('click', async function() {

                        const btn = $(this);
                        btn.prop("disabled", true);

                        Swal.fire({
                            title: "Reimprimiendo...",
                            text: "Enviando a impresora térmica",
                            allowOutsideClick: false,
                            didOpen: () => Swal.showLoading()
                        });

                        try {

                            const data = {
                                comprobante: cobro.numero || '-',
                                paciente: cobro.paciente || '-',
                                profesional: cobro.profesional || '-',
                                total: cobro.total || 0,
                                detalle: (detalle || []).map(d => ({
                                    nombre: d.nombre,
                                    precio: d.precio
                                }))
                            };

                            if (typeof window.imprimirTicket !== "function") {
                                throw new Error("Impresión no disponible");
                            }

                            await window.imprimirTicket(data);

                            Swal.fire("OK", "Ticket reimpreso", "success");

                        } catch (err) {
                            console.error(err);
                            Swal.fire("Error", err.message || "No se pudo imprimir", "error");
                        }

                        btn.prop("disabled", false);
                    });

                /* =========================
                   🧾 HTML DEL MODAL
                ========================= */
                let html = `
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h6 class="text-uppercase text-muted mb-0">Comprobante</h6>
                        <span class="h5 font-weight-bold text-primary">
                            ${cobro.numero || 'N/A'}
                        </span>
                    </div>
                    <div class="text-right">
                        <span class="badge badge-success">Pagado</span>
                        <p class="text-muted small mb-0 mt-1">${cobro.fecha}</p>
                    </div>
                </div>

                <div class="mb-4">
                    <p><strong>Paciente:</strong> ${cobro.paciente}</p>
                </div>

                <div class="table-responsive">
                    <table class="table table-borderless table-striped">
                        <thead>
                            <tr>
                                <th>Descripción</th>
                                <th class="text-right">Monto</th>
                            </tr>
                        </thead>
                        <tbody>
            `;

                if (detalle?.length) {
                    detalle.forEach(d => {
                        html += `
                        <tr>
                            <td>${d.nombre}</td>
                            <td class="text-right font-weight-bold">
                                $${parseFloat(d.precio).toLocaleString('es-AR', { minimumFractionDigits: 2 })}
                            </td>
                        </tr>
                    `;
                    });
                }

                html += `</tbody></table></div>`;

                /* =========================
                   🔁 REPARTO
                ========================= */
                if (reparto?.length) {
                    html += `
                    <div class="mt-4 p-3 bg-light rounded">
                        <h6 class="text-info">Distribución</h6>
                `;

                    reparto.forEach(r => {
                        html += `
                        <div class="d-flex justify-content-between small">
                            <span>${r.destino}</span>
                            <span>$${parseFloat(r.total).toLocaleString('es-AR', { minimumFractionDigits: 2 })}</span>
                        </div>
                    `;
                    });

                    html += `</div>`;
                }

                /* =========================
                   💰 TOTAL
                ========================= */
                html += `
                <div class="mt-4 pt-3 border-top">
                    <div class="d-flex justify-content-between">
                        <strong>TOTAL</strong>
                        <strong class="text-primary">
                            $${parseFloat(cobro.total).toLocaleString('es-AR', { minimumFractionDigits: 2 })}
                        </strong>
                    </div>
                </div>
            `;

                $("#detalleCobroContenido").html(html);

            }, 'json').fail(function() {
                $("#detalleCobroContenido").html(`
                <div class="alert alert-danger">
                    Error de conexión con el servidor.
                </div>
            `);
            });

        });

    });
</script>