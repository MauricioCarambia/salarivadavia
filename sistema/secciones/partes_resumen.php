<?php
require_once 'inc/db.php';
date_default_timezone_set('America/Argentina/Buenos_Aires');

/* ==============================
    📅 FILTROS
============================== */
$desde      = $_GET['desde'] ?? date('Y-m-d');
$hasta      = $_GET['hasta'] ?? date('Y-m-d');
$caja_id    = $_GET['caja_id'] ?? '';
$turno      = $_GET['turno'] ?? '';
$usuario_id = $_GET['usuario_id'] ?? '';

$desdeSQL = $desde . " 00:00:00";
$hastaSQL = $hasta . " 23:59:59";

/* ==============================
    🔍 WHERE COBROS
============================== */
$where = "c.fecha BETWEEN ? AND ?";
$params = [$desdeSQL, $hastaSQL];

if (!empty($caja_id)) {
    $where .= " AND c.caja_id = ?";
    $params[] = $caja_id;
}
if (!empty($turno)) {
    $where .= " AND cs.turno = ?";
    $params[] = $turno;
}
if (!empty($usuario_id)) {
    $where .= " AND c.usuario_id = ?";
    $params[] = $usuario_id;
}

/* ==============================
    💰 1. TOTAL FACTURADO (SIN DUPLICAR)
============================== */
$stmt = $pdo->prepare("
    SELECT SUM(cm.monto)
    FROM caja_movimientos cm
    LEFT JOIN cobros c ON c.id = cm.cobro_id
    LEFT JOIN caja_sesion cs ON cs.id = c.caja_sesion_id
    WHERE cm.tipo = 'ingreso'
    AND cm.fecha BETWEEN ? AND ?
");

$paramsTF = [$desdeSQL, $hastaSQL];

if (!empty($caja_id)) {
    $stmt = $pdo->prepare("
        SELECT SUM(cm.monto)
        FROM caja_movimientos cm
        LEFT JOIN cobros c ON c.id = cm.cobro_id
        LEFT JOIN caja_sesion cs ON cs.id = c.caja_sesion_id
        WHERE cm.tipo = 'ingreso'
        AND cm.fecha BETWEEN ? AND ?
        AND cm.caja_id = ?
    ");
    $paramsTF[] = $caja_id;
}

$stmt->execute($paramsTF);
$totalFacturado = (float)$stmt->fetchColumn();

/* ==============================
    📊 2. REPARTO (CLAVE)
============================== */
$stmt = $pdo->prepare("
    SELECT 
        dr.nombre,
        dr.tipo,
        dr.categoria,
        SUM(cr.monto) as total
    FROM cobros_reparto cr
    INNER JOIN destinos_reparto dr ON dr.id = cr.destino_id
    INNER JOIN cobros c ON c.id = cr.cobro_id
    LEFT JOIN caja_sesion cs ON cs.id = c.caja_sesion_id
    WHERE $where
    GROUP BY dr.id
");
$stmt->execute($params);
$destinos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$whereMov = "cm.fecha BETWEEN ? AND ?";
$paramsMov = [$desdeSQL, $hastaSQL];

if (!empty($caja_id)) {
    $whereMov .= " AND cm.caja_id = ?";
    $paramsMov[] = $caja_id;
}
if (!empty($usuario_id)) {
    $whereMov .= " AND c.usuario_id = ?";
    $paramsMov[] = $usuario_id;
}

$stmt = $pdo->prepare("
    SELECT concepto as nombre, SUM(monto) as total
    FROM caja_movimientos cm
    LEFT JOIN cobros c ON c.id = cm.cobro_id
    WHERE cm.tipo = 'egreso' AND $whereMov
    GROUP BY concepto
");
$stmt->execute($paramsMov);
$egresos = $stmt->fetchAll(PDO::FETCH_ASSOC);
/* ==============================
    📊 3. TOTALES CORRECTOS
============================== */
$totalIngresos = 0;
$totalFondos   = 0;
$totalEgresos  = 0;

// ingresos + fondos
foreach ($destinos as $d) {
    if ($d['tipo'] === 'ingreso' && $d['categoria'] === 'normal') {
        $totalIngresos += $d['total'];
    }

    if ($d['categoria'] === 'fondo') {
        $totalFondos += $d['total'];
    }
}

// egresos reales
foreach ($egresos as $e) {
    $totalEgresos += $e['total'];
}

$ganancia = $totalFacturado - $totalEgresos - $totalFondos;

/* ==============================
    📦 AUX
============================== */
$cajas = $pdo->query("SELECT id, nombre FROM cajas")->fetchAll(PDO::FETCH_ASSOC);
$usuarios = $pdo->query("SELECT id, nombre FROM empleados")->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="card mb-3">
    <div class="card-body">
        <form method="GET" action="./index.php" class="row">
            <input type="hidden" name="seccion" value="partes_resumen">
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
                    <?php foreach ($cajas as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $caja_id == $c['id'] ? 'selected' : '' ?>><?= $c['nombre'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label>Turno</label>
                <select name="turno" class="form-control">
                    <option value="">Todos</option>
                    <option value="Mañana" <?= $turno == 'Mañana' ? 'selected' : '' ?>>Mañana</option>
                    <option value="Tarde" <?= $turno == 'Tarde' ? 'selected' : '' ?>>Tarde</option>
                </select>
            </div>
            <div class="col-md-2">
                <label>Usuario</label>
                <select name="usuario_id" class="form-control">
                    <option value="">Todos</option>
                    <?php foreach ($usuarios as $u): ?>
                        <option value="<?= $u['id'] ?>" <?= $usuario_id == $u['id'] ? 'selected' : '' ?>><?= $u['nombre'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary mr-1 w-100">Filtrar</button>
                <a href="./index.php?seccion=partes_resumen" class="btn btn-secondary w-100">Limpiar</a>
            </div>
        </form>
    </div>
</div>

<div class="row mb-3">
    <div class="col-md-3">
        <div class="small-box bg-info text-center p-3">
            <h4>$<?= number_format($totalFacturado, 2, ',', '.') ?></h4>
            <p>Total Facturado</p>
        </div>
    </div>

    <div class="col-md-3">
        <div class="small-box bg-danger text-center p-3">
            <h4>$<?= number_format($totalEgresos, 2, ',', '.') ?></h4>
            <p>Egresos</p>
        </div>
    </div>
    <div class="col-md-3">
        <div class="small-box bg-primary text-center p-3">
            <h4>$<?= number_format($totalFondos, 2, ',', '.') ?></h4>
            <p>Fondos</p>
        </div>
    </div>
    <div class="col-md-3">
        <div class="small-box bg-success text-center p-3">
            <h4>$<?= number_format($totalIngresos, 2, ',', '.') ?></h4>
            <p>Ingresos (Normales)</p>
        </div>
    </div>
    <!-- <div class="col-md-3">
        <div class="small-box <?= $ganancia >= 0 ? 'bg-warning' : 'bg-warning' ?> text-center p-3">
            <h4>$<?= number_format($ganancia, 2, ',', '.') ?></h4>
            <p>Ganancia</p>
        </div>
    </div> -->
</div>

<div class="card">
    <div class="card-header">
        <h4><i class="fas fa-chart-bar"></i> Estado de Resultados</h4>
    </div>
    <div class="card-body table-responsive">
        <table id="tablaResumen" class="table table-hover datatable">
            <thead class="thead-dark">
                <tr>
                    <th>Concepto</th>
                    <th>Tipo</th>
                    <th>Categoria</th>
                    <th class="text-right">Monto</th>
                </tr>
            </thead>

            <tbody>

                <!-- =========================
🟢 INGRESOS NORMALES
========================= -->
                <?php foreach ($destinos as $d):
                    if ($d['tipo'] === 'ingreso' && $d['categoria'] === 'normal'): ?>
                        <tr>
                            <td><?= htmlspecialchars($d['nombre']) ?></td>
                            <td><span class="badge badge-success">Ingreso</span></td>
                            <td><span class="badge badge-secondary">Normal</span></td>
                            <td class="text-right"><?= number_format($d['total'], 2, ',', '.') ?></td>
                        </tr>
                <?php endif;
                endforeach; ?>

                <tr class="table-success font-weight-bold">
                    <td>TOTAL INGRESOS</td>
                    <td></td>
                    <td></td>
                    <td class="text-right"><?= number_format($totalIngresos, 2, ',', '.') ?></td>
                </tr>


                <!-- =========================
🔵 FONDOS (SOLO INGRESOS FONDO)
========================= -->
                <?php foreach ($destinos as $d):
                    if ($d['tipo'] === 'ingreso' && $d['categoria'] === 'fondo'): ?>
                        <tr>
                            <td><?= htmlspecialchars($d['nombre']) ?></td>
                            <td><span class="badge badge-primary">Ingreso</span></td>
                            <td><span class="badge badge-primary">Fondo</span></td>
                            <td class="text-right"><?= number_format($d['total'], 2, ',', '.') ?></td>
                        </tr>
                <?php endif;
                endforeach; ?>

                <tr class="table-primary font-weight-bold">
                    <td>TOTAL FONDOS</td>
                    <td></td>
                    <td></td>
                    <td class="text-right"><?= number_format($totalFondos, 2, ',', '.') ?></td>
                </tr>


                <!-- =========================
🔴 EGRESOS PROFESIONALES (REPARTO)
========================= -->
                <?php foreach ($destinos as $d):
                    if ($d['tipo'] === 'egreso'): ?>
                        <tr>
                            <td><?= htmlspecialchars($d['nombre']) ?></td>
                            <td><span class="badge badge-danger">Egreso</span></td>
                            <td>
                                <?php if ($d['categoria'] === 'profesional'): ?>
                                    <span class="badge badge-danger">Profesional</span>
                                <?php else: ?>
                                    <span class="badge badge-secondary">Normal</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-right"><?= number_format($d['total'], 2, ',', '.') ?></td>
                        </tr>
                <?php endif;
                endforeach; ?>


                <!-- =========================
🔴 EGRESOS CAJA (MOVIMIENTOS)
========================= -->
                <?php foreach ($egresos as $e): ?>
                    <tr>
                        <td><?= htmlspecialchars($e['nombre']) ?></td>
                        <td><span class="badge badge-danger">Egreso</span></td>
                        <td><span class="badge badge-warning">Caja</span></td>
                        <td class="text-right"><?= number_format($e['total'], 2, ',', '.') ?></td>
                    </tr>
                <?php endforeach; ?>

                <tr class="table-danger font-weight-bold">
                    <td>TOTAL EGRESOS</td>
                    <td></td>
                    <td></td>
                    <td class="text-right"><?= number_format($totalEgresos, 2, ',', '.') ?></td>
                </tr>

            </tbody>

            <tfoot>
                <tr class="bg-dark text-white font-weight-bold">
                    <td colspan="3" style="font-size: 1.1rem;">RESULTADO OPERATIVO</td>
                    <td class="text-right" style="font-size: 1.1rem;">
                        $<?= number_format($ganancia, 2, ',', '.') ?>
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        $('.datatable').each(function() {
            initDataTable($(this), {
                ordering: false
            });
        });
    });
</script>