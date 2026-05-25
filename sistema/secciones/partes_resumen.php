<?php
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/services/CajaService.php';

date_default_timezone_set('America/Argentina/Buenos_Aires');

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$usuarioSesion = $_SESSION['user_id'];

/* ==============================
    🔎 OBTENER ROL
============================== */
$stmt = $pdo->prepare("SELECT rol_id FROM empleados WHERE id = ?");
$stmt->execute([$usuarioSesion]);
$rol_id = $stmt->fetchColumn();

/* ==============================
    🔐 VALIDAR ACCESO
============================== */
$stmt = $pdo->prepare("
    SELECT 1
    FROM roles_accesos ra
    INNER JOIN accesos a ON a.Id = ra.acceso_id
    WHERE ra.rol_id = ?
    AND a.nombre = 'arrastre'
    LIMIT 1
");
$stmt->execute([$rol_id]);

if (!$stmt->fetchColumn()) {
    echo "<div class='alert alert-danger text-center mt-5'>
            <h4>⛔ Acceso denegado</h4>
            <p>No tenés permisos para ver esta sección</p>
          </div>";
    exit;
}

/* ==============================
    📅 FILTROS
============================== */
$filtros = [
    'desde'      => $_GET['desde'] ?? date('Y-m-d'),
    'hasta'      => $_GET['hasta'] ?? date('Y-m-d'),
    'caja_id'    => $_GET['caja_id'] ?? '',
    'turno'      => $_GET['turno'] ?? '',
    'usuario_id' => $_GET['usuario_id'] ?? ''
];

/* ==============================
    🧠 SERVICE
============================== */
$cajaService = new CajaService($pdo);
$resumen = $cajaService->getResumen($filtros);

/* ==============================
    📊 DATA FINAL
============================== */

// 💰 caja / banco
$efectivoFinal = $resumen['caja']['balance'];
$transferenciaFinal = $resumen['banco']['balance'];

// 📦 destinos (ya vienen bien calculados)
$destinos = $resumen['destinos'];

// 👥 transferencias empleados
$transferenciasEmpleados = $resumen['transferencias'];

/* ==============================
    📊 TOTALES
============================== */
$totalIngresos = 0;
$totalFondos   = 0;
$totalEgresos  = 0;

foreach ($destinos as $d) {

    if ($d['total_general'] > 0 && $d['categoria'] !== 'fondo') {
        $totalIngresos += $d['total_general'];
    }

    if ($d['categoria'] === 'fondo') {
        $totalFondos += $d['total_general'];
    }

    if ($d['total_general'] < 0) {
        $totalEgresos += abs($d['total_general']);
    }
}

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
                <input type="date" name="desde" value="<?= $filtros['desde'] ?>" class="form-control">
            </div>
            <div class="col-md-2">
                <label>Hasta</label>
                <input type="date" name="hasta" value="<?= $filtros['hasta'] ?>" class="form-control">
            </div>
            <div class="col-md-2">
                <label>Caja</label>
                <select name="caja_id" class="form-control">
                    <option value="">Todas</option>
                    <?php foreach ($cajas as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $filtros['caja_id'] == $c['id'] ? 'selected' : '' ?>><?= $c['nombre'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label>Turno</label>
                <select name="turno" class="form-control">
                    <option value="">Todos</option>
                    <option value="Mañana" <?= $filtros['turno'] == 'Mañana' ? 'selected' : '' ?>>Mañana</option>
                    <option value="Tarde" <?= $filtros['turno'] == 'Tarde' ? 'selected' : '' ?>>Tarde</option>
                </select>
            </div>
            <div class="col-md-2">
                <label>Usuario</label>
                <select name="usuario_id" class="form-control">
                    <option value="">Todos</option>
                    <?php foreach ($usuarios as $u): ?>
                        <option value="<?= $u['id'] ?>" <?= $filtros['usuario_id'] == $u['id'] ? 'selected' : '' ?>><?= $u['nombre'] ?></option>
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
<div class="row mb-4">

    <div class="col-md-6">
        <div class="card shadow-sm border-left-dark h-100">
            <div class="card-body p-3 text-center">

                <h5 class="text-muted text-uppercase mb-2">
                    <i class="fas fa-cash-register mr-1"></i> Efectivo
                </h5>

                <div class="display-4 font-weight-bold text-dark">
                    $<?= number_format($efectivoFinal, 2, ',', '.') ?>
                </div>

                <small class="text-muted">
                    Ingresos: $<?= number_format($resumen['caja']['ingresos'], 2, ',', '.') ?> |
                    Egresos: $<?= number_format($resumen['caja']['egresos'], 2, ',', '.') ?>
                </small>

            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card shadow-sm border-left-info h-100">
            <div class="card-body p-3 text-center">

                <h5 class="text-muted text-uppercase mb-2">
                    <i class="fas fa-university mr-1"></i> Transferencias
                </h5>

                <div class="display-4 font-weight-bold text-info">
                    $<?= number_format($transferenciaFinal, 2, ',', '.') ?>
                </div>

                <small class="text-muted">
                    Ingresos: $<?= number_format($resumen['banco']['ingresos'], 2, ',', '.') ?> |
                    Egresos: $<?= number_format($resumen['banco']['egresos'], 2, ',', '.') ?>
                </small>

            </div>
        </div>
    </div>

</div>

<?php if (!empty($transferenciasEmpleados)): ?>
    <div class="row mb-4">
        <?php foreach ($transferenciasEmpleados as $emp): ?>
            <div class="col-md-3">
                <div class="card bg-gradient-info shadow-sm">
                    <div class="card-body text-center">

                        <h6 class="text-white text-uppercase">
                            <?= htmlspecialchars($emp['nombre']) ?>
                        </h6>

                        <h3 class="text-white font-weight-bold">
                            $<?= number_format($emp['total_transferencias'], 2, ',', '.') ?>
                        </h3>

                        <small class="text-white-50">
                            Transferencias recibidas
                        </small>

                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>


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

<!-- 🔵 TRANSFERENCIAS POR EMPLEADO -->
<?php foreach ($transferenciasEmpleados as $emp): ?>
    <tr>
        <td>Transferencia → <?= htmlspecialchars($emp['nombre']) ?></td>
        <td><span class="badge badge-info">Ingreso</span></td>
        <td><span class="badge badge-info">Empleado</span></td>
        <td class="text-right">
            $<?= number_format($emp['total_transferencias'], 2, ',', '.') ?>
        </td>
    </tr>
<?php endforeach; ?>


<!-- 🟢 INGRESOS -->
<?php foreach ($destinos as $d): ?>
    <?php if ($d['total_general'] > 0 && $d['categoria'] !== 'fondo'): ?>
        <tr>
            <td><?= htmlspecialchars($d['nombre']) ?></td>
            <td><span class="badge badge-success">Ingreso</span></td>
            <td>
                <?php if ($d['categoria'] === 'profesional'): ?>
                    <span class="badge badge-danger">Profesional</span>
                <?php else: ?>
                    <span class="badge badge-warning">Caja</span>
                <?php endif; ?>
            </td>
            <td class="text-right">
                <div>$<?= number_format($d['total_general'], 2, ',', '.') ?></div>
                <small class="text-success">
                    Efec: $<?= number_format($d['total_efectivo'], 2, ',', '.') ?>
                </small><br>
                <small class="text-info">
                    Trans: $<?= number_format($d['total_transferencia'], 2, ',', '.') ?>
                </small>
            </td>
        </tr>
    <?php endif; ?>
<?php endforeach; ?>

<tr class="table-success font-weight-bold">
    <td>TOTAL INGRESOS</td>
    <td></td>
    <td></td>
    <td class="text-right">$<?= number_format($totalIngresos, 2, ',', '.') ?></td>
</tr>


<!-- 🔵 FONDOS -->
<?php foreach ($destinos as $d): ?>
    <?php if ($d['categoria'] === 'fondo' && $d['total_general'] > 0): ?>
        <tr>
            <td><?= htmlspecialchars($d['nombre']) ?></td>
            <td><span class="badge badge-primary">Ingreso</span></td>
            <td><span class="badge badge-primary">Fondo</span></td>
            <td class="text-right">
                <div>$<?= number_format($d['total_general'], 2, ',', '.') ?></div>
                <small class="text-success">
                    Efec: $<?= number_format($d['total_efectivo'], 2, ',', '.') ?>
                </small><br>
                <small class="text-info">
                    Trans: $<?= number_format($d['total_transferencia'], 2, ',', '.') ?>
                </small>
            </td>
        </tr>
    <?php endif; ?>
<?php endforeach; ?>

<tr class="table-primary font-weight-bold">
    <td>TOTAL FONDOS</td>
    <td></td>
    <td></td>
    <td class="text-right">$<?= number_format($totalFondos, 2, ',', '.') ?></td>
</tr>


<!-- 🔴 EGRESOS -->
<?php foreach ($destinos as $d): ?>
    <?php if ($d['total_general'] < 0): ?>
        <tr>
            <td><?= htmlspecialchars($d['nombre']) ?></td>
            <td><span class="badge badge-danger">Egreso</span></td>
            <td>
                <?php if ($d['categoria'] === 'profesional'): ?>
                    <span class="badge badge-danger">Profesional</span>
                <?php elseif ($d['categoria'] === 'fondo'): ?>
                    <span class="badge badge-primary">Fondo</span>
                <?php else: ?>
                    <span class="badge badge-warning">Caja</span>
                <?php endif; ?>
            </td>
            <td class="text-right">
                <div>$<?= number_format(abs($d['total_general']), 2, ',', '.') ?></div>
                <small class="text-success">
                    Efec: $<?= number_format(abs($d['total_efectivo']), 2, ',', '.') ?>
                </small><br>
                <small class="text-info">
                    Trans: $<?= number_format(abs($d['total_transferencia']), 2, ',', '.') ?>
                </small>
            </td>
        </tr>
    <?php endif; ?>
<?php endforeach; ?>

<tr class="table-danger font-weight-bold">
    <td>TOTAL EGRESOS</td>
    <td></td>
    <td></td>
    <td class="text-right">$<?= number_format($totalEgresos, 2, ',', '.') ?></td>
</tr>

</tbody>
          
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