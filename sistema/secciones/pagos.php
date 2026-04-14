<?php
require_once __DIR__ . '/../inc/db.php';
date_default_timezone_set('America/Argentina/Buenos_Aires');

/* ==============================
   📅 FILTROS
============================== */
$desde = $_GET['desde'] ?? date('Y-m-d');
$hasta = $_GET['hasta'] ?? date('Y-m-d');
$caja = $_GET['caja'] ?? '';
$turno = $_GET['turno'] ?? '';
$usuario = $_GET['usuario'] ?? '';

$desdeSQL = $desde . " 00:00:00";
$hastaSQL = $hasta . " 23:59:59";

/* ==============================
   🧠 FILTROS DINÁMICOS
============================== */
$filtros = "";
$paramsBase = [$desdeSQL, $hastaSQL];
$paramsFiltros = [];

if ($caja) {
    $filtros .= " AND cs.caja_id = ? ";
    $paramsFiltros[] = $caja;
}

if ($turno) {
    $filtros .= " AND cs.turno = ? ";
    $paramsFiltros[] = $turno;
}

if ($usuario) {
    $filtros .= " AND c.usuario_id = ? ";
    $paramsFiltros[] = $usuario;
}

/* ==============================
   📊 1. REPORTE PROFESIONALES
============================== */
$sql = "
SELECT 
    p.Id,
    p.nombre,
    p.apellido,
    COALESCE(c_sum.total_facturado, 0) AS Total_Facturado,
    COALESCE(r.total_profesional, 0) AS Total_Profesional,
    COALESCE(r.total_egresos, 0) AS Total_Egresos,
    (COALESCE(c_sum.total_facturado, 0) - COALESCE(r.total_egresos, 0)) AS Ganancia_Clinica

FROM profesionales p

LEFT JOIN (
    SELECT c.profesional_id, SUM(c.total) AS total_facturado
    FROM cobros c
    LEFT JOIN caja_sesion cs ON cs.id = c.caja_sesion_id
    WHERE c.estado = 'activo'
    AND c.fecha BETWEEN ? AND ?
    $filtros
    GROUP BY c.profesional_id
) c_sum ON c_sum.profesional_id = p.Id

LEFT JOIN (
    SELECT 
        c.profesional_id,
        SUM(CASE WHEN dr.categoria = 'profesional' THEN cr.monto ELSE 0 END) AS total_profesional,
        SUM(CASE WHEN dr.categoria IN ('profesional','fondo') THEN cr.monto ELSE 0 END) AS total_egresos
    FROM cobros c
    INNER JOIN cobros_reparto cr ON cr.cobro_id = c.id
    INNER JOIN destinos_reparto dr ON dr.id = cr.destino_id
    LEFT JOIN caja_sesion cs ON cs.id = c.caja_sesion_id
    WHERE c.estado = 'activo'
    AND c.fecha BETWEEN ? AND ?
    $filtros
    GROUP BY c.profesional_id
) r ON r.profesional_id = p.Id

WHERE COALESCE(c_sum.total_facturado, 0) > 0

ORDER BY p.apellido ASC, Total_Facturado DESC
";

/* 🔥 PARAMETROS CORRECTOS */
$paramsReporte = array_merge(
    $paramsBase,
    $paramsFiltros,
    $paramsBase,
    $paramsFiltros
);

$stmt = $pdo->prepare($sql);
$stmt->execute($paramsReporte);
$reporte = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ==============================
   📊 2. DESTINOS
============================== */
$sqlDestinos = "
SELECT 
    dr.nombre,
    dr.tipo,
    dr.categoria,
    SUM(cr.monto) AS total
FROM cobros_reparto cr
INNER JOIN destinos_reparto dr ON dr.id = cr.destino_id
INNER JOIN cobros c ON c.id = cr.cobro_id
LEFT JOIN caja_sesion cs ON cs.id = c.caja_sesion_id
WHERE c.estado = 'activo'
AND c.fecha BETWEEN ? AND ?
$filtros
GROUP BY dr.id, dr.nombre, dr.tipo
";

$paramsDestinos = array_merge($paramsBase, $paramsFiltros);

$stmtDestinos = $pdo->prepare($sqlDestinos);
$stmtDestinos->execute($paramsDestinos);
$destinos = $stmtDestinos->fetchAll(PDO::FETCH_ASSOC);

/* ==============================
   📊 3. TOTALES
============================== */
$totalEgresos = 0;
$totalIngresosNormales = 0;
$totalFondos = 0;

foreach ($destinos as $d) {

    $monto = (float)$d['total'];

    // 🔴 EGRESOS (para control si querés usarlo después)
    if ($d['tipo'] === 'egreso') {
        $totalEgresos += $monto;
    }

    // 🟢 INGRESOS NORMALES (GANANCIA REAL)
    if ($d['tipo'] === 'ingreso' && $d['categoria'] === 'normal') {
        $totalIngresosNormales += $monto;
    }

    // 🔵 FONDO
    if ($d['categoria'] === 'fondo') {
        $totalFondos += $monto;
    }
}

/* TOTAL FACTURADO */
$sqlTotal = "
SELECT SUM(c.total) 
FROM cobros c
LEFT JOIN caja_sesion cs ON cs.id = c.caja_sesion_id
WHERE c.estado = 'activo'
AND c.fecha BETWEEN ? AND ?
$filtros
";

$paramsTotal = array_merge($paramsBase, $paramsFiltros);

$stmtTotal = $pdo->prepare($sqlTotal);
$stmtTotal->execute($paramsTotal);
$totalFacturado = (float)$stmtTotal->fetchColumn();

/* BALANCE */
$balance = $totalIngresosNormales;

/* ==============================
   📦 DATOS PARA FILTROS
============================== */
$cajas = $pdo->query("SELECT id, nombre FROM cajas")->fetchAll(PDO::FETCH_ASSOC);
$usuarios = $pdo->query("SELECT id, nombre FROM empleados")->fetchAll(PDO::FETCH_ASSOC);

$rand = rand(1000, 9999);
?>

<!-- FILTROS -->
<div class="card card-outline card-info shadow-sm">
    <div class="card-body">
        <form method="GET">
            <input type="hidden" name="seccion" value="<?= $_GET['seccion'] ?? 'pagos' ?>">
            
            <div class="row align-items-end">
                <div class="col-md-2 col-sm-6 mb-2">
                    <label class="small font-weight-bold">Desde</label>
                    <input type="date" name="desde" value="<?= $desde ?>" class="form-control form-control-sm">
                </div>
                
                <div class="col-md-2 col-sm-6 mb-2">
                    <label class="small font-weight-bold">Hasta</label>
                    <input type="date" name="hasta" value="<?= $hasta ?>" class="form-control form-control-sm">
                </div>

                <div class="col-md-2 col-sm-4 mb-2">
                    <label class="small font-weight-bold">Caja</label>
                    <select name="caja" class="form-control form-control-sm">
                        <option value="">Todas</option>
                        <?php foreach ($cajas as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= $caja == $c['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-2 col-sm-4 mb-2">
                    <label class="small font-weight-bold">Turno</label>
                    <select name="turno" class="form-control form-control-sm">
                        <option value="">Todos</option>
                        <option value="mañana" <?= $turno == 'mañana' ? 'selected' : '' ?>>Mañana</option>
                        <option value="tarde" <?= $turno == 'tarde' ? 'selected' : '' ?>>Tarde</option>
                    </select>
                </div>

                <div class="col-md-2 col-sm-4 mb-2">
                    <label class="small font-weight-bold">Usuario</label>
                    <select name="usuario" class="form-control form-control-sm">
                        <option value="">Todos</option>
                        <?php foreach ($usuarios as $u): ?>
                            <option value="<?= $u['id'] ?>" <?= $usuario == $u['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($u['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-2 mb-2">
                    <div class="btn-group">
                        <button type="submit" class="btn btn-primary btn-sm mr-1">Filtrar
                        </button>
                        <a href="?seccion=<?= $_GET['seccion'] ?? 'pagos' ?>" class="btn btn-secondary btn-sm" title="Limpiar Filtros">Limpiar
                        </a>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>


<!-- KPIs -->
<div class="row mb-3">
    <div class="col-md-3">
        <div class="small-box bg-info p-2 text-center">
            <h4>$<?= number_format($totalFacturado, 2, ',', '.') ?></h4>
            <p>Total Facturado</p>
        </div>
    </div>
    <div class="col-md-3">
        <div class="small-box bg-danger p-2 text-center">
            <h4>$<?= number_format($totalEgresos, 2, ',', '.') ?></h4>
            <p>Total Egresos</p>
        </div>
    </div>
    <div class="col-md-3">
        <div class="small-box bg-success p-2 text-center">
            <h4>$<?= number_format($balance, 2, ',', '.') ?></h4>
            <p>Ganancia Clínica</p>
        </div>
    </div>
    <div class="col-md-3">
    <div class="small-box bg-primary p-2 text-center">
        <h4>$<?= number_format($totalFondos, 2, ',', '.') ?></h4>
        <p>Fondo Acumulado</p>
    </div>
</div>
</div>

<!-- DESTINOS -->
<div class="row mb-3 d-flex justify-content-center">
    <?php foreach ($destinos as $d): ?>
        <?php if ($d['categoria'] == 'profesional') {
            $color = 'danger';
        } elseif ($d['categoria'] == 'fondo') {
            $color = 'primary';
        } else {
            $color = 'success';
        } ?>
        <div class="col-md-2">
            <div class="card p-2 text-center border-<?= $color ?>">
                <h6>$<?= number_format($d['total'], 2, ',', '.') ?></h6>
                <small>
                    <?= ucfirst($d['nombre']) ?>
                    <span class="badge badge-secondary"><?= $d['categoria'] ?></span>
                </small>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- TABLA -->
<div class="card card-outline card-primary">
    <div class="card-header">
        <h3><i class="fas fa-user-md"></i> Pago de Profesionales</h3>
    </div>

    <div class="card-body table-responsive">
        <table class="table table-striped datatable">
            <thead>
                <tr class="text-center">
                    <th>Profesional</th>
                    <th>Total Facturado</th>
                    <th>Pago Profesional</th>
                    <th>Ganancia Clínica</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($reporte as $fila): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($fila['apellido'] . ' ' . $fila['nombre']) ?></strong></td>
                        <td class="text-info text-center">$<?= number_format($fila['Total_Facturado'], 2, ',', '.') ?></td>
                        <td class="text-danger text-center">$<?= number_format($fila['Total_Profesional'], 2, ',', '.') ?></td>
                        <td class="text-success text-center font-weight-bold">$<?= number_format($fila['Ganancia_Clinica'], 2, ',', '.') ?></td>
                        <td class="text-center">
                            <div class="btn-group">
                                <!-- <button class="btn btn-info btn-sm rounded-circle" onclick="nuevoPago(<?= $fila['Id'] ?>)">
                                    <i class="fas fa-plus"></i>
                                </button> -->
                                <button class="btn btn-primary btn-sm rounded-circle" onclick="verPagos(<?= $fila['Id'] ?>)">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <!-- <button class="btn btn-warning btn-sm rounded-circle" onclick="verTurnos(<?= $fila['Id'] ?>)">
                                    <i class="fas fa-calendar"></i>
                                </button> -->
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    function nuevoPago(id) {
        window.location.href = `./?seccion=pagos_new&id=${id}&nc=<?= $rand ?>`;
    }

    function verPagos(id) {
        window.location.href = `./?seccion=pagos_view&id=${id}&nc=<?= $rand ?>`;
    }

    function verTurnos(id) {
        window.location.href = `./?seccion=pagos_fechas&id=${id}&nc=<?= $rand ?>`;
    }
</script>