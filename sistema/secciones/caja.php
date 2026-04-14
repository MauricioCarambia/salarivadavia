<?php
require_once __DIR__ . '/../inc/db.php';

date_default_timezone_set('America/Argentina/Buenos_Aires');

/* =================================
   📅 FILTROS
================================= */
$desde = $_GET['desde'] ?? date('Y-m-d');
$hasta = $_GET['hasta'] ?? date('Y-m-d');

$inicioHoy = $desde . ' 00:00:00';
$finHoy = $hasta . ' 23:59:59';

$caja_id = $_GET['caja_id'] ?? '';
$turno_sesion = $_GET['turno'] ?? '';
$usuario_id = $_GET['usuario_id'] ?? '';

/* =================================
   🧾 WHERE DINÁMICO (REUTILIZABLE)
================================= */
$where = "WHERE m.fecha BETWEEN ? AND ?";
$params = [$inicioHoy, $finHoy];

if (!empty($caja_id)) {
    $where .= " AND cs.caja_id = ?";
    $params[] = $caja_id;
}
if (!empty($turno_sesion)) {
    $where .= " AND cs.turno = ?";
    $params[] = $turno_sesion;
}
if (!empty($usuario_id)) {
    $where .= " AND cs.usuario_id = ?";
    $params[] = $usuario_id;
}

/* =================================
   🧾 1) TURNOS (SIN DUPLICAR)
================================= */
$stmtTurnos = $pdo->prepare("
    SELECT 
        c.id as cobro_id,
        c.numero_completo,
        c.total as monto,
        MAX(m.fecha) as fecha,
        cs.turno as sesion_turno,
        e.nombre as empleado_nombre,
        c.estado,
        m.concepto
    FROM cobros c
    INNER JOIN caja_movimientos m ON m.cobro_id = c.id
    INNER JOIN caja_sesion cs ON cs.id = m.caja_sesion_id
    LEFT JOIN empleados e ON e.id = cs.usuario_id
    $where
    AND c.turno_id IS NOT NULL
    GROUP BY c.id
    ORDER BY c.fecha DESC
");
$stmtTurnos->execute($params);
$tablaTurnos = $stmtTurnos->fetchAll(PDO::FETCH_ASSOC);

/* =================================
   💸 2) EXTERNOS (CON DESTINO)
================================= */
$stmtExt = $pdo->prepare("
    SELECT 
        m.id,
        m.tipo,
        m.concepto,
        m.monto,
        m.fecha,
         c.id as cobro_id,
        c.numero_completo,
        d.nombre AS destino_nombre,
        c.estado
    FROM caja_movimientos m
    INNER JOIN cobros c ON c.id = m.cobro_id
    INNER JOIN caja_sesion cs ON cs.id = m.caja_sesion_id
    LEFT JOIN cobros_reparto cr ON cr.cobro_id = c.id
    LEFT JOIN destinos_reparto d ON d.id = cr.destino_id
    $where
    AND c.turno_id IS NULL
    ORDER BY m.fecha DESC
");
$stmtExt->execute($params);
$externos = $stmtExt->fetchAll(PDO::FETCH_ASSOC);

/* =================================
   📊 CLASIFICACIÓN
================================= */
$tablaIngresosExt = [];
$tablaEgresosExt = [];

foreach ($externos as $m) {
    if (strtolower($m['tipo']) === 'ingreso') {
        $tablaIngresosExt[] = $m;
    } else {
        $tablaEgresosExt[] = $m;
    }
}

/* =================================
   💰 TOTALES
================================= */
$totalTurnos = 0;
foreach ($tablaTurnos as $t) {
    if (($t['estado'] ?? 'activo') !== 'anulado') {
        $totalTurnos += $t['monto'];
    }
}

$totalIngresosExt = 0;
foreach ($tablaIngresosExt as $m) {
    if (($m['estado'] ?? 'activo') !== 'anulado') {
        $totalIngresosExt += $m['monto'];
    }
}

$totalEgresosExt = 0;
foreach ($tablaEgresosExt as $m) {
    if (($m['estado'] ?? 'activo') !== 'anulado') {
        $totalEgresosExt += $m['monto'];
    }
}

$balance = $totalTurnos + $totalIngresosExt - $totalEgresosExt;

?>

<div class="card card-outline card-info p-3 mb-3">
    <form method="get" class="row">
        <input type="hidden" name="seccion" value="caja">

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
                <?php foreach ($pdo->query("SELECT id, nombre FROM cajas")->fetchAll() as $cj): ?>
                    <option value="<?= $cj['id'] ?>" <?= $caja_id == $cj['id'] ? 'selected' : '' ?>>
                        <?= $cj['nombre'] ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- 🔥 TURNO -->
        <div class="col-md-2">
            <label>Turno</label>
            <select name="turno" class="form-control">
                <option value="">Todos</option>
                <option value="mañana" <?= $turno_sesion == 'mañana' ? 'selected' : '' ?>>Mañana</option>
                <option value="tarde" <?= $turno_sesion == 'tarde' ? 'selected' : '' ?>>Tarde</option>
            </select>
        </div>

        <!-- 🔥 EMPLEADO -->
        <div class="col-md-2">
            <label>Empleado</label>
            <select name="usuario_id" class="form-control">
                <option value="">Todos</option>
                <?php
                $empleados = $pdo->query("SELECT id, nombre FROM empleados ORDER BY nombre")->fetchAll();
                foreach ($empleados as $emp): ?>
                    <option value="<?= $emp['id'] ?>" <?= $usuario_id == $emp['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($emp['nombre']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-1 d-flex align-items-end">
            <button class="btn btn-primary w-100 mr-1">Filtrar</button>
        </div>

        <div class="col-md-1 d-flex align-items-end">
            <a href="?seccion=caja" class="btn btn-secondary w-100">
                Limpiar
            </a>
        </div>
    </form>
</div>

<div class="row mb-3">
    <div class="col-md-3">
        <div class="card bg-info text-white p-3">
            <h6>Ingresos por turnos</h6>
            <h3>$<?= number_format($totalTurnos, 2) ?></h3>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-success text-white p-3">
            <h6>Ingresos Externos</h6>
            <h3>$<?= number_format($totalIngresosExt, 2) ?></h3>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-danger text-white p-3">
            <h6>Egresos Externos</h6>
            <h3>$<?= number_format($totalEgresosExt, 2) ?></h3>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-dark text-white p-3">
            <h6>Balance Neto</h6>
            <h3>$<?= number_format($balance, 2) ?></h3>
        </div>
    </div>
</div>
<div class="row mb-3">
    <div class="col-md-6">
        <div class="card mb-3 card-outline card-success">
            <div class="card-header"><strong>Ingresos Externos / Manuales</strong></div>
            <div class="card-body p-0 table-responsive">
                <table class="table table-sm table-striped mb-0 tabla">
                    <thead class="thead-dark">
                        <tr>
                            <th>Fecha</th>
                            <th>Concepto</th>
                            <th>Destino</th>
                            <th>Monto</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tablaIngresosExt as $m): ?>
                            <?php $esAnulado = ($m['estado'] === 'anulado'); ?>

                            <tr>
                                <td data-order="<?= $m['fecha'] ?>">
                                    <?= date('d/m/Y H:i', strtotime($m['fecha'])) ?>
                                </td>
                                <td><?= htmlspecialchars($m['concepto']) ?> <?php if ($esAnulado): ?>
                                        <span class="badge badge-danger ml-1">Anulado</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= $m['destino_nombre'] ?? '-' ?></td>
                                <td class="text-success font-weight-bold <?= $esAnulado ? 'text-danger' : '' ?>">
                                    <?= $esAnulado ? '-' : '' ?>$<?= number_format($m['monto'], 2) ?>
                                </td>
                                <td class="text-center">
                                    <div class="btn-group">

                                        <?php if (!empty($m['cobro_id'])): ?>
                                            <button class="btn btn-info btn-sm ver-cobro rounded-circle" data-id="<?= $m['cobro_id'] ?>">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card mb-3 card-outline card-danger">
            <div class="card-header"><strong>Egresos / Gastos</strong></div>
            <div class="card-body p-0 table-responsive">
                <table class="table table-sm table-striped mb-0 tabla">
                    <thead class="thead-dark">
                        <tr>
                            <th>Fecha</th>
                            <th>Concepto</th>
                            <th>Destino</th>
                            <th>Monto</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tablaEgresosExt as $m): ?>
                            <?php $esAnulado = ($m['estado'] === 'anulado'); ?>

                            <tr>
                                <td data-order="<?= $m['fecha'] ?>">
                                    <?= date('d/m/Y H:i', strtotime($m['fecha'])) ?>
                                </td>
                                <td><?= htmlspecialchars($m['concepto']) ?> <?php if ($esAnulado): ?>
                                        <span class="badge badge-danger ml-1">Anulado</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= $m['destino_nombre'] ?? '-' ?></td>
                                <td class="text-success font-weight-bold <?= $esAnulado ? 'text-danger' : '' ?>">
                                    <?= $esAnulado ? '-' : '' ?>$<?= number_format($m['monto'], 2) ?>
                                </td>
                                <td class="text-center">
                                    <div class="btn-group">

                                        <?php if (!empty($m['cobro_id'])): ?>
                                            <button class="btn btn-info btn-sm ver-cobro rounded-circle" data-id="<?= $m['cobro_id'] ?>">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
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
<div class="row mb-3">
    <div class="col-md-12">
        <div class="card mb-3 card-outline card-info">
            <div class="card-header"><strong>Cobros por Turnos</strong></div>
            <div class="card-body p-0 table-responsive">
                <table class="table table-sm table-striped mb-0 tabla">
                    <thead class="thead-dark">
                        <tr>
                            <th>Fecha</th>
                            <th>Comprobante</th>
                            <th>Concepto</th>
                            <th>Monto</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tablaTurnos as $t): ?>
                            <?php $esAnulado = ($t['estado'] === 'anulado'); ?>

                            <tr>
                                <td data-order="<?= $t['fecha'] ?>">
                                    <?= date('d/m/Y H:i', strtotime($t['fecha'])) ?>
                                </td>
                                <td>
                                    <?= $t['numero_completo'] ?>
                                    <?php if ($esAnulado): ?>
                                        <span class="badge badge-danger ml-1">Anulado</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= $t['concepto'] ?></td>
                                <td class="text-primary font-weight-bold <?= $esAnulado ? 'text-danger' : '' ?>">
                                    <?= $esAnulado ? '-' : '' ?>$<?= number_format($t['monto'], 2) ?>
                                </td>
                                <td class="text-center">
                                    <div class="btn-group">

                                        <button class="btn btn-info btn-sm ver-cobro rounded-circle" data-id="<?= $t['cobro_id'] ?>">
                                            <i class="fas fa-eye"></i>
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
    $(document).ready(function() {
        // Inicializar DataTable si la función existe
        if (typeof initDataTable === "function") {
            $('.tabla').each(function() {
                initDataTable($(this), {
                    pageLength: 10,

                    order: [
                        [0, "desc"]
                    ], // 👈 ordenar por columna 0 (fecha DESC)

                    columnDefs: [{
                        targets: 0,
                        type: "date" // 👈 importante
                    }]
                });
            });
        }

        // Lógica del Modal para ver detalle de cobro
        $(document).on("click", ".ver-cobro", function() {
            const cobroId = $(this).data("id");

            if (!cobroId) {
                alert("Este movimiento no tiene comprobante asociado");
                return;
            }
            // Feedback visual de carga
            $("#detalleCobroContenido").html(`
        <div class="text-center py-5">
            <div class="spinner-border text-primary" role="status"></div>
            <p class="mt-2 text-muted">Buscando información...</p>
        </div>
    `);
            $("#verCobroModal").modal("show");

            // Guardamos el ID en el botón de imprimir del modal por si quiere usarlo
            $("#btnImprimirDesdeModal").off('click').on('click', function() {
                window.open('imprimir_ticket.php?cobro_id=' + cobroId, '_blank');
            });

            $.get("ajax/get_cobro.php", {
                cobro_id: cobroId
            }, function(res) {
                if (!res.success) {
                    $("#detalleCobroContenido").html(`<div class="alert alert-custom alert-light-danger"><i class="fas fa-exclamation-circle"></i> ${res.message}</div>`);
                    return;
                }

                const {
                    cobro,
                    detalle,
                    reparto
                } = res;

                let html = `
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h6 class="text-uppercase text-muted mb-0" style="letter-spacing: 1px; font-size: 0.8rem;">Comprobante</h6>
                    <span class="h5 font-weight-bold text-primary">${cobro.numero || 'N/A'}</span>
                </div>
                <div class="text-right">
                    <span class="badge badge-pill badge-success px-3">Pagado</span>
                    <p class="text-muted small mb-0 mt-1">${cobro.fecha}</p>
                </div>
            </div>

            <div class="mb-4">
                <p class="mb-1"><i class="fas fa-user-circle text-muted mr-2"></i><strong>Paciente:</strong> ${cobro.paciente}</p>
            </div>

            <div class="table-responsive">
                <table class="table table-borderless table-striped">
                    <thead class="small text-uppercase text-muted" style="border-bottom: 2px solid #f4f6f9">
                        <tr>
                            <th>Descripción</th>
                            <th class="text-right">Monto</th>
                        </tr>
                    </thead>
                    <tbody>`;

                if (detalle?.length) {
                    detalle.forEach(d => {
                        html += `
                    <tr>
                        <td class="py-2">${d.nombre}</td>
                        <td class="text-right py-2 font-weight-bold">$${parseFloat(d.precio).toLocaleString('es-AR', {minimumFractionDigits: 2})}</td>
                    </tr>`;
                    });
                }

                html += `</tbody></table></div>`;

                // Sección de Reparto con estilo de tarjeta interna
                if (reparto?.length) {
                    html += `
                <div class="mt-4 p-3 bg-light rounded shadow-sm">
                    <h6 class="font-weight-bold small text-uppercase mb-3 text-info"><i class="fas fa-share-alt mr-2"></i>Distribución de fondos</h6>`;

                    reparto.forEach(r => {
                        html += `
                    <div class="d-flex justify-content-between small mb-1">
                        <span class="text-muted">${r.destino}:</span>
                        <span class="font-weight-bold text-dark">$${parseFloat(r.total).toLocaleString('es-AR', {minimumFractionDigits: 2})}</span>
                    </div>`;
                    });

                    html += `</div>`;
                }

                // Gran Total
                html += `
            <div class="mt-4 pt-3" style="border-top: 2px dashed #dee2e6">
                <div class="d-flex justify-content-between align-items-center">
                    <span class="h5 font-weight-bold text-dark mb-0">TOTAL</span>
                    <span class="h3 font-weight-bold text-primary mb-0">$${parseFloat(cobro.total).toLocaleString('es-AR', {minimumFractionDigits: 2})}</span>
                </div>
            </div>
        `;

                $("#detalleCobroContenido").html(html);

            }, 'json').fail(function() {
                $("#detalleCobroContenido").html(`
            <div class="alert alert-danger">
                <i class="fas fa-wifi-slash mr-2"></i> Error de conexión con el servidor.
            </div>
        `);
            });
        });
    });
</script>