<?php
require_once 'inc/db.php';

date_default_timezone_set('America/Argentina/Buenos_Aires');

/* =============================
   📅 FILTROS
=============================*/
$desde = $_GET['desde'] ?? date('Y-m-d');
$hasta = $_GET['hasta'] ?? date('Y-m-d');

$inicioHoy = $desde . ' 00:00:00';
$finHoy = $hasta . ' 23:59:59';

$caja_id = $_GET['caja_id'] ?? '';
$turno = $_GET['turno'] ?? '';
$usuario_id = $_GET['usuario_id'] ?? '';

$usuarioSesion = $_SESSION['user_id'] ?? null;

if (!$usuarioSesion) {
    die("Error: usuario no autenticado");
}

/* =============================
   🔐 ROL DESDE SESIÓN
=============================*/
$esAdmin = ($_SESSION['es_admin'] ?? 0) == 1;

/* =============================
   🧾 MOVIMIENTOS (DINÁMICO)
=============================*/
$where = "WHERE m.fecha BETWEEN ? AND ?";
$params = [$inicioHoy, $finHoy];

if (!empty($caja_id)) {
    $where .= " AND cs.caja_id = ?";
    $params[] = $caja_id;
}

if (!empty($turno)) {
    $where .= " AND cs.turno = ?";
    $params[] = $turno;
}

// 🔐 SEGURIDAD
if (!$esAdmin) {
    $where .= " AND cs.usuario_id = ?";
    $params[] = $usuarioSesion;
} else {
    if (!empty($usuario_id)) {
        $where .= " AND cs.usuario_id = ?";
        $params[] = $usuario_id;
    }
}

$stmt = $pdo->prepare("
    SELECT 
        m.*, 
        c.numero_completo, 
        c.id as cobro_id,
        cs.usuario_id,
        cs.turno
    FROM caja_movimientos m
    LEFT JOIN cobros c ON c.id = m.cobro_id
    INNER JOIN caja_sesion cs ON cs.id = m.caja_sesion_id
    $where
    ORDER BY m.fecha DESC
");

$stmt->execute($params);
$movimientos = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* =============================
   💰 TOTALES
=============================*/
$totalIngresos = 0;
$totalEgresos = 0;

foreach ($movimientos as $m) {
    if ($m['tipo'] == 'INGRESO')
        $totalIngresos += $m['monto'];
    else
        $totalEgresos += $m['monto'];
}

$totalNeto = $totalIngresos - $totalEgresos;


/* =============================
   🧮 ARQUEO
=============================*/
$stmt = $pdo->query("SELECT * FROM arqueos_caja ORDER BY id DESC LIMIT 1");
$arqueo = $stmt->fetch(PDO::FETCH_ASSOC);

$estadoArqueo = 'secondary';
$textoArqueo = 'Sin control';

if ($arqueo) {
    if (abs($arqueo['diferencia']) < 1) {
        $estadoArqueo = 'success';
        $textoArqueo = 'Caja Perfecta';
    } else {
        $estadoArqueo = 'danger';
        $textoArqueo = 'Diferencia detectada';
    }
}
$usuarios = $pdo->query("SELECT id, nombre FROM empleados")->fetchAll(PDO::FETCH_KEY_PAIR);
?>

<div class="card card-info card-outline ">

    <h1 class="ml-3">Control de Caja</h1>

    <!-- =============================
         FILTROS
    ============================= -->
    <form method="get" class="m-3">
        <input type="hidden" name="seccion" value="caja">

        <div class="row">

            <div class="col-md-2">
                <label>Desde</label>
                <input type="date" name="desde" class="form-control" value="<?= $desde ?>">
            </div>

            <div class="col-md-2">
                <label>Hasta</label>
                <input type="date" name="hasta" class="form-control" value="<?= $hasta ?>">
            </div>

            <div class="col-md-2">
                <label>Caja</label>
                <select name="caja_id" class="form-control">
                    <option value="">Todas</option>
                    <?php
                    $cajas = $pdo->query("SELECT id, nombre FROM cajas")->fetchAll();
                    foreach ($cajas as $c) {
                        $sel = ($caja_id == $c['id']) ? 'selected' : '';
                        echo "<option value='{$c['id']}' $sel>{$c['nombre']}</option>";
                    }
                    ?>
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

            <?php if ($esAdmin): ?>
                <div class="col-md-2">
                    <label>Usuario</label>
                    <select name="usuario_id" class="form-control">
                        <option value="">Todos</option>
                        <?php
                        $emps = $pdo->query("SELECT id, nombre FROM empleados")->fetchAll();
                        foreach ($emps as $e) {
                            $sel = ($usuario_id == $e['id']) ? 'selected' : '';
                            echo "<option value='{$e['id']}' $sel>{$e['nombre']}</option>";
                        }
                        ?>
                    </select>
                </div>
            <?php endif; ?>

            <div class="col-md-2 d-flex align-items-end">
                <button class="btn btn-primary w-100">Filtrar</button>
            </div>

        </div>
    </form>

    <!-- =============================
         RESUMEN
    ============================= -->
    <div class="row">
        <div class="col-md-4">
            <div class="small-box bg-success p-2">
                <h4>$<?= number_format($totalIngresos, 2) ?></h4>
                <p>Ingresos</p>
            </div>
        </div>

        <div class="col-md-4">
            <div class="small-box bg-danger p-2">
                <h4>$<?= number_format($totalEgresos, 2) ?></h4>
                <p>Egresos</p>
            </div>
        </div>

        <div class="col-md-4">
            <div class="small-box bg-info p-2">
                <h4>$<?= number_format($totalNeto, 2) ?></h4>
                <p>Balance</p>
            </div>
        </div>
    </div>

    <!-- =============================
         TABLA
    ============================= -->
    <table class="table table-striped tabla mt-3">
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Turno</th>
                <th>Usuario</th>
                <th>Tipo</th>
                <th>Comprobante</th>
                <th>Concepto</th>
                <th>Monto</th>
                <th>Acción</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($movimientos as $m): ?>
                <tr>
                    <td><?= $m['fecha'] ?></td>
                    <td>
                        <?= ucfirst($m['turno'] ?? '-') ?>
                    </td>
                    <td><?= $usuarios[$m['usuario_id']] ?? '-' ?></td>
                    <td>
                        <?php
                        $color = match ($m['tipo']) {
                            'INGRESO' => 'success',
                            'EGRESO' => 'danger',
                            default => 'secondary'
                        };
                        ?>
                        <span class="badge badge-<?= $color ?>">
                            <?= ucfirst(strtolower($m['tipo'])) ?>
                        </span>
                    </td>
                    <td><?= $m['numero_completo'] ?? '-' ?></td>
                    <td><?= htmlspecialchars($m['concepto']) ?></td>
                    <td class="<?= $m['tipo'] == 'EGRESO' ? 'text-danger' : 'text-success' ?>">
                        <?= $m['tipo'] == 'EGRESO' ? '-' : '' ?>$
                        <?= number_format($m['monto'], 2) ?>
                    </td>
                    <td>
                        <?php if (!empty($m['cobro_id'])): ?>
                            <button class="btn btn-info btn-sm ver-cobro rounded-circle" data-id="<?= $m['cobro_id'] ?>">
                                <i class="fas fa-eye"></i>
                            </button>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

</div>
<div class="modal fade" id="verCobroModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">

            <div class="modal-header bg-info text-white">
                <h5 class="modal-title">
                    <i class="fas fa-file-invoice"></i> Detalle de Cobro
                </h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>

            <div class="modal-body" id="detalleCobroContenido">
                <div class="text-center">Cargando...</div>
            </div>

            <div class="modal-footer">
                <button class="btn btn-secondary" data-dismiss="modal">
                    Cerrar
                </button>
            </div>

        </div>
    </div>
</div>


<script>
    $(document).ready(function () {

        $('.tabla').each(function () {
            initDataTable($(this));
        });

        /* =============================
           👁 VER COBRO
        ============================= */
        $(document).on("click", ".ver-cobro", function () {

            const cobroId = $(this).data("id");

            $("#detalleCobroContenido").html("Cargando...");
            $("#verCobroModal").modal("show");

            $.ajax({
                url: "ajax/get_cobro.php",
                type: "POST",
                contentType: "application/json",
                dataType: "json",
                data: JSON.stringify({ cobro_id: cobroId }),

                success: function (data) {

                    if (!data.success) {
                        $("#detalleCobroContenido").html(
                            `<div class="alert alert-danger">${data.message}</div>`
                        );
                        return;
                    }

                    let html = `
                    <div class="mb-3">
                        <p><strong>Comprobante:</strong> ${data.cobro.numero ?? '-'}</p>
                        <p><strong>Paciente:</strong> ${data.cobro.paciente ?? '-'}</p>
                        <p><strong>Fecha:</strong> ${data.cobro.fecha ?? '-'}</p>
                    </div>

                    <hr>

                    <h5>Detalle</h5>
                    <table class="table table-sm ">
                        <thead>
                            <tr>
                                <th>Descripción</th>
                                <th class="text-right">Monto</th>
                            </tr>
                        </thead>
                        <tbody>
                `;

                    if (data.detalle?.length) {
                        data.detalle.forEach(d => {
                            html += `
                            <tr>
                                <td>${d.nombre}</td>
                                <td class="text-right">$${parseFloat(d.precio).toFixed(2)}</td>
                            </tr>
                        `;
                        });
                    } else {
                        html += `<tr><td colspan="2">Sin detalles</td></tr>`;
                    }

                    html += `</tbody></table>`;

                    // REPARTO
                    if (data.reparto?.length) {
                        html += `
                        <h5 class="mt-3">Reparto</h5>
                        <table class="table table-sm ">
                            <thead>
                                <tr>
                                    <th>Destino</th>
                                    <th class="text-right">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                    `;

                        data.reparto.forEach(r => {
                            html += `
                            <tr>
                                <td>${r.destino}</td>
                                <td class="text-right">$${parseFloat(r.total).toFixed(2)}</td>
                            </tr>
                        `;
                        });

                        html += `</tbody></table>`;
                    }

                    html += `
                    <hr>
                    <h4 class="text-right">
                        Total: $${parseFloat(data.cobro.total).toFixed(2)}
                    </h4>
                `;

                    $("#detalleCobroContenido").html(html);
                },

                error: function (xhr) {
                    console.error(xhr.responseText); // 🔥 DEBUG
                    $("#detalleCobroContenido").html(
                        `<div class="alert alert-danger">Error al cargar el cobro</div>`
                    );
                }
            });

        });

    });
</script>