<?php
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/services/afiliados.php';
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
$empleado_id = $_GET['empleado_id'] ?? $usuario_id;

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
    c.total AS monto,
    c.fecha,
    c.concepto,
    c.numero_completo,
    c.estado,
    c.medio_pago,
    c.transferencia_tipo,
    c.empleado_destino_id,
    c.profesional_id,
    c.paciente_id,

    ed.nombre AS empleado_destino_nombre,

    CONCAT(
        COALESCE(pr.apellido,''), ' ',
        COALESCE(pr.nombre,'')
    ) AS profesional_destino_nombre,

    GROUP_CONCAT(DISTINCT d.nombre SEPARATOR ', ') AS destino_nombre,

    SUM(cr.monto) AS total_reparto

FROM cobros c

LEFT JOIN caja_sesion cs      ON cs.id        = c.caja_sesion_id
LEFT JOIN cobros_reparto cr   ON cr.cobro_id  = c.id
LEFT JOIN destinos_reparto d  ON d.id         = cr.destino_id
LEFT JOIN empleados ed        ON ed.id        = c.empleado_destino_id
LEFT JOIN profesionales pr    ON pr.Id        = c.profesional_id

$where

GROUP BY c.id
ORDER BY c.fecha DESC
");

$stmt->execute($params);
$movimientos = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* =========================
   🔎 DIFERENCIA REPARTO
   Compara el total cobrado contra la suma de
   cobros_reparto, para detectar repartos que
   no quedaron registrados correctamente.
========================= */
foreach ($movimientos as &$m) {

    $m['diferencia_reparto'] = null;
    $m['reparto_alerta'] = null;

    if ($m['total_reparto'] === null) {
        if ((float)$m['monto'] != 0) {
            $m['reparto_alerta'] = "Sin reparto registrado para este cobro";
        }
        continue;
    }

    $diff = round((float)$m['monto'] - (float)$m['total_reparto'], 2);

    if (abs($diff) >= 0.01) {
        $m['diferencia_reparto'] = $diff;
        $m['reparto_alerta'] = sprintf(
            "El reparto registrado ($%s en %s) no coincide con el total cobrado ($%s)",
            number_format((float)$m['total_reparto'], 2),
            $m['destino_nombre'] ?: 'sin destino',
            number_format((float)$m['monto'], 2)
        );
    }
}
unset($m);

/* =========================
   🔎 VALIDACIÓN: REPARTO CONFIGURADO POR PRÁCTICA

   El reparto registrado en cobros_reparto puede
   estar "compensado" (cobrar_turno.php ajusta
   centavos para que cierre exacto), por lo que el
   chequeo anterior no detecta reglas mal cargadas.
   Acá se revisa, para cada práctica del cobro, si
   la regla de reparto configurada actualmente
   suma el precio cobrado.
========================= */
$idsIngresos = array_column(
    array_filter($movimientos, fn($m) => $m['tipo'] === 'ingreso'),
    'id'
);

if ($idsIngresos) {

    $in = implode(',', array_fill(0, count($idsIngresos), '?'));

    $stmtDet = $pdo->prepare("
        SELECT cobro_id, practica_id, nombre AS practica_nombre, precio
        FROM cobros_detalle
        WHERE cobro_id IN ($in)
    ");
    $stmtDet->execute($idsIngresos);
    $detalles = $stmtDet->fetchAll(PDO::FETCH_ASSOC);

    $profesionalPorCobro = [];
    $tipoPacientePorCobro = [];
    foreach ($movimientos as $m) {
        $profesionalPorCobro[$m['id']] = $m['profesional_id'];

        if (!array_key_exists($m['id'], $tipoPacientePorCobro)) {
            $tipoPacientePorCobro[$m['id']] = $m['paciente_id']
                ? obtenerEstadoAfiliado($pdo, (int)$m['paciente_id'])['cobra_como']
                : null;
        }
    }

    $stmtRepartoId = $pdo->prepare("
        SELECT id FROM practicas_reparto
        WHERE practica_id = ?
          AND (profesional_id = ? OR profesional_id IS NULL)
          AND tipo_paciente = ?
        ORDER BY profesional_id DESC
        LIMIT 1
    ");

    $stmtReglas = $pdo->prepare("
        SELECT d.valor, t.nombre AS tipo
        FROM practicas_reparto_detalle d
        INNER JOIN tipos_reparto t ON t.id = d.tipo_id
        WHERE d.reparto_id = ?
    ");

    $alertasConfigPorCobro = [];

    foreach ($detalles as $d) {

        $precio = (float)$d['precio'];

        $tipoPaciente = $tipoPacientePorCobro[$d['cobro_id']] ?? null;

        if ($tipoPaciente === null) {
            continue;
        }

        $profesionalId = $profesionalPorCobro[$d['cobro_id']] ?? null;
        $stmtRepartoId->execute([$d['practica_id'], $profesionalId, $tipoPaciente]);
        $repId = $stmtRepartoId->fetchColumn();

        if (!$repId) {
            $alertasConfigPorCobro[$d['cobro_id']][] = sprintf(
                "%s: no hay reparto configurado para tipo de paciente '%s'",
                $d['practica_nombre'],
                $tipoPaciente
            );
            continue;
        }

        $stmtReglas->execute([$repId]);
        $reglas = $stmtReglas->fetchAll(PDO::FETCH_ASSOC);

        $totalFijos = 0;
        $sumPorcentajes = 0;

        foreach ($reglas as $r) {
            if ($r['tipo'] === 'monto fijo') {
                $totalFijos += (float)$r['valor'];
            } else {
                $sumPorcentajes += (float)$r['valor'];
            }
        }

        $base = $precio - $totalFijos;
        $totalReparto = $totalFijos + ($base * $sumPorcentajes / 100);
        $diff = round($precio - $totalReparto, 2);

        if (abs($diff) >= 0.01) {
            $alertasConfigPorCobro[$d['cobro_id']][] = sprintf(
                "%s: precio $%s, reparto configurado suma $%s (diferencia $%s)",
                $d['practica_nombre'],
                number_format($precio, 2),
                number_format($totalReparto, 2),
                number_format($diff, 2)
            );
        }
    }

    foreach ($movimientos as &$m) {
        if (!empty($alertasConfigPorCobro[$m['id']])) {
            $m['config_alerta'] = implode(' | ', $alertasConfigPorCobro[$m['id']]);
        } else {
            $m['config_alerta'] = null;
        }
    }
    unset($m);
} else {
    foreach ($movimientos as &$m) {
        $m['config_alerta'] = null;
    }
    unset($m);
}

/* =========================
   🎯 DESTINOS
========================= */
$stmt = $pdo->query("
    SELECT id, nombre, tipo, categoria
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
        <div class="row mb-3">
            <div class="col-12">
                <div class="alert alert-warning py-2 mb-0">
                    <small>
                        <strong>Importante:</strong>
                        Para ingresos utilizar únicamente
                        <strong>Ingresos Sala</strong> /
                        <strong>Cardiologia del sur - En profesional->Cardiologia del sur->Llenar practica y paciente</strong>,
                       
                    </small>
                </div>
            </div>
        </div>
        <div class="row">

            <!-- TIPO -->
            <div class="col-md-2 mb-3">
                <label>Tipo</label>
                <select name="tipo" class="form-control" required>
                    <option value="" selected disabled>Seleccione...</option>
                    <option value="ingreso">Ingreso (+)</option>
                    <option value="egreso">Egreso (-)</option>
                </select>
            </div>

            <!-- DESTINO -->
            <div class="col-md-4 mb-3">
                <label>Destino</label>
                <select name="destino_id" id="destino" class="form-control">
                    <option value="">-- Seleccionar destino --</option>

                    <?php foreach ($destinos as $d): ?>
                        <option
                            value="<?= $d['id'] ?>"
                            data-tipo="<?= $d['tipo'] ?>"
                            data-categoria="<?= strtolower($d['categoria'] ?? '') ?>">
                            <?= htmlspecialchars($d['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- CONCEPTO -->
            <div class="col-md-4 mb-3">
                <label>Concepto</label>
                <input type="text" name="concepto" class="form-control" required>
            </div>

            <!-- MONTO -->
            <div class="col-md-2 mb-3">
                <label>Monto</label>
                <input type="number" step="0.01" name="monto" id="monto" class="form-control">
            </div>

        </div>



        <div class="row">

            <!-- PACIENTE -->
            <div class="col-md-4 mb-3">
                <label>Paciente</label>
                <select name="paciente_id" id="buscadorPacientes" class="form-control"></select>
            </div>

            <!-- PROFESIONAL -->
            <div class="col-md-4 mb-3">
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
            <div class="col-md-4 mb-3">
                <label>Práctica</label>
                <select name="practica_id" id="practicas" class="form-control">
                    <option value="">-- Opcional --</option>
                </select>
            </div>

        </div>

        <div class="row">

            <!-- MEDIO DE PAGO -->
            <div class="col-md-3 mb-3">
                <label>Medio de Pago</label>
                <select name="medio_pago" id="medio_pago" class="form-control">
                    <option value="efectivo">Efectivo</option>
                    <option value="transferencia">Transferencia</option>
                </select>
            </div>

            <!-- TIPO TRANSFERENCIA -->
            <div class="col-md-3 mb-3" id="boxTransferenciaTipo" style="display:none;">
                <label>Tipo Transferencia</label>
                <select name="transferencia_tipo" id="transferencia_tipo" class="form-control">
                    <option value="">Seleccione...</option>
                    <option value="clinica">Transferencia Clínica</option>
                    <option value="profesional">Cobrado por Profesional</option>
                </select>
            </div>

            <!-- EMPLEADO DESTINO -->
            <div class="col-md-4 mb-3" id="boxEmpleadoDestino" style="display:none;">
                <label>Empleado Destino</label>
                <select name="empleado_destino_id" class="form-control">
                    <option value="">Seleccione...</option>
                    <?php foreach ($pdo->query("SELECT id, nombre FROM empleados")->fetchAll() as $e): ?>
                        <option value="<?= $e['id'] ?>">
                            <?= htmlspecialchars($e['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- BOTON -->
            <div class="col-md-2 mb-3 d-flex align-items-end">
                <button type="submit" class="btn btn-success btn-block">
                    <i class="fas fa-save mr-1"></i>
                    Guardar
                </button>
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

                                        <?php elseif ($m['medio_pago'] === 'transferencia'): ?>

                                            <span class="badge badge-primary">Transferencia</span><br>

                                            <?php if ($m['transferencia_tipo'] === 'clinica'): ?>

                                                <small class="badge badge-info d-block text-wrap text-left" style="max-width:100%;">
                                                    <i class="fas fa-user mr-1"></i>
                                                    <?= htmlspecialchars($m['empleado_destino_nombre'] ?: '-') ?>
                                                </small>

                                            <?php elseif ($m['transferencia_tipo'] === 'profesional'): ?>

                                                <small class="badge badge-info text-dark d-block text-wrap text-left" style="max-width:100%;">
                                                    <i class="fas fa-user-md mr-1"></i>
                                                    Cobrado por <?= htmlspecialchars(trim($m['profesional_destino_nombre']) ?: '-') ?>
                                                </small>

                                            <?php endif; ?>

                                        <?php endif; ?>

                                    </td>
                                    <td class="align-middle font-weight-bold 
    <?= ($m['estado'] === 'anulado') ? 'text-danger' : 'text-success' ?>">

                                        <?= ($m['estado'] === 'anulado') ? '-' : '' ?>
                                        $<?= number_format($m['monto'], 2) ?>

                                        <?php if ($m['estado'] === 'anulado'): ?>
                                            <span class="badge badge-danger ml-2">ANULADO</span>
                                        <?php endif; ?>

                                        <?php if ($m['reparto_alerta'] !== null): ?>
                                            <span class="badge badge-danger d-block text-wrap text-left mt-1" style="max-width:100%;" title="<?= htmlspecialchars($m['reparto_alerta']) ?>">
                                                <i class="fas fa-exclamation-triangle"></i>
                                                <?php if ($m['diferencia_reparto'] !== null): ?>
                                                    Diff $<?= number_format($m['diferencia_reparto'], 2) ?>
                                                <?php else: ?>
                                                    Sin reparto
                                                <?php endif; ?>
                                            </span>
                                        <?php endif; ?>

                                        <?php if ($m['config_alerta'] !== null): ?>
                                            <span class="badge badge-danger text-dark d-block text-wrap text-left mt-1" style="max-width:100%;" title="<?= htmlspecialchars($m['config_alerta']) ?>">
                                                <i class="fas fa-exclamation-triangle"></i>
                                                Práctica mal config.
                                            </span>
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

                                        <?php elseif ($m['medio_pago'] === 'transferencia'): ?>

                                            <span class="badge badge-primary">Transferencia</span><br>

                                            <?php if ($m['transferencia_tipo'] === 'clinica'): ?>

                                                <small class="badge badge-info d-block text-wrap text-left" style="max-width:100%;">
                                                    <i class="fas fa-user mr-1"></i>
                                                    <?= htmlspecialchars($m['empleado_destino_nombre'] ?: '-') ?>
                                                </small>

                                            <?php elseif ($m['transferencia_tipo'] === 'profesional'): ?>

                                                <small class="badge badge-info text-dark d-block text-wrap text-left" style="max-width:100%;">
                                                    <i class="fas fa-user-md mr-1"></i>
                                                    Cobrado por <?= htmlspecialchars(trim($m['profesional_destino_nombre']) ?: '-') ?>
                                                </small>


                                            <?php endif; ?>

                                        <?php endif; ?>

                                    </td>
                                    <td class="align-middle font-weight-bold text-danger">

                                        -$<?= number_format($m['monto'], 2) ?>

                                        <?php if ($m['estado'] === 'anulado'): ?>
                                            <span class="badge badge-danger ml-2">ANULADO</span>
                                        <?php endif; ?>

                                        <?php if ($m['reparto_alerta'] !== null): ?>
                                            <span class="badge badge-danger d-block text-wrap text-left mt-1" style="max-width:100%;" title="<?= htmlspecialchars($m['reparto_alerta']) ?>">
                                                <i class="fas fa-exclamation-triangle"></i>
                                                <?php if ($m['diferencia_reparto'] !== null): ?>
                                                    Diff $<?= number_format($m['diferencia_reparto'], 2) ?>
                                                <?php else: ?>
                                                    Sin reparto
                                                <?php endif; ?>
                                            </span>
                                        <?php endif; ?>

                                        <?php if ($m['config_alerta'] !== null): ?>
                                            <span class="badge badge-danger text-dark d-block text-wrap text-left mt-1" style="max-width:100%;" title="<?= htmlspecialchars($m['config_alerta']) ?>">
                                                <i class="fas fa-exclamation-triangle"></i>
                                                Práctica mal config.
                                            </span>
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
    const esAdmin = <?= $esAdmin ? 'true' : 'false' ?>;
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
        let _guardandoMovimiento = false;
        $('#formMovimientoManual').on('submit', function(e) {
            e.preventDefault();

            if (_guardandoMovimiento) return;
            _guardandoMovimiento = true;

            let medio = $('#medio_pago').val();

            if (medio === 'transferencia') {
                let tipoTransferencia = $('#transferencia_tipo').val();

                if (!tipoTransferencia) {
                    Swal.fire('Atención', 'Seleccione el tipo de transferencia', 'warning');
                    return;
                }

                if (tipoTransferencia === 'clinica' && !$('select[name="empleado_destino_id"]').val()) {
                    _guardandoMovimiento = false;
                    Swal.fire('Atención', 'Debe seleccionar un empleado destino', 'warning');
                    return;
                }
            }

            $.post('ajax/guardar_movimiento.php', $(this).serialize(), async function(res) {

                if (res.success) {
                    const r = await Swal.fire({
                        title: 'Movimiento guardado',
                        text: '¿Desea imprimir el comprobante?',
                        icon: 'success',
                        showCancelButton: true,
                        confirmButtonText: 'Imprimir',
                        cancelButtonText: 'No imprimir'
                    });

                    if (r.isConfirmed) {
                        Swal.fire({
                            title: 'Imprimiendo...',
                            allowOutsideClick: false,
                            didOpen: () => Swal.showLoading()
                        });

                        try {
                            await imprimirMovimiento(res.cobro_id);
                        } catch (err) {
                            console.error(err);
                            await Swal.fire('Atención', 'El movimiento se guardó pero no se pudo imprimir.', 'warning');
                        }
                    }

                    location.reload();

                } else {
                    _guardandoMovimiento = false;
                    Swal.fire('Error', res.message, 'error');
                }

            }, 'json').fail(function() {
                _guardandoMovimiento = false;
            });
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
                title: '¿Anular cobro?',
                text: "Se generará un egreso en caja automáticamente",
                icon: 'warning',
                input: 'text',
                inputLabel: 'Motivo de la anulación',
                inputPlaceholder: 'Ingresá el motivo...',
                inputValidator: (value) => {
                    if (!value || !value.trim()) {
                        return 'Debes indicar un motivo';
                    }
                },
                showCancelButton: true,
                confirmButtonColor: '#d33',
                confirmButtonText: 'Sí, anular',
                cancelButtonText: 'Cancelar'
            }).then(r => {

                if (!r.isConfirmed) return;

                Swal.close();
                $.post('ajax/eliminar_movimiento.php', {
                    id,
                    motivo: r.value.trim()
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
                $('#monto').prop('readonly', false);
                return;
            }

            $.get('ajax/get_precio.php', {
                practica_id,
                paciente_id
            }, function(res) {

                if (!res.success) {
                    $('#monto')
                        .val('')
                        .prop('readonly', false);

                    return;
                }

                $('#monto')
                    .val(parseFloat(res.precio).toFixed(2))
                    .prop('readonly', true);

                // Solo nombre de práctica
                if (res.nombre) {
                    $('input[name="concepto"]').val(res.nombre);
                }

                // Mostrar estado afiliado (opcional)
                if ($('#estadoAfiliado').length && res.afiliado) {

                    let html = '';

                    switch (res.afiliado.tipo) {

                        case 'vitalicio':
                            html = '<span class="badge badge-primary">Vitalicio</span>';
                            break;

                        case 'nuevo':
                            html = '<span class="badge badge-info">En gracia</span>';
                            break;

                        case 'socio':
                            html = '<span class="badge badge-success">Socio al día</span>';
                            break;

                        case 'moroso':
                            html =
                                '<span class="badge badge-danger">' +
                                'Moroso (' +
                                res.afiliado.meses_adeudados +
                                ' meses)' +
                                '</span>';
                            break;

                        default:
                            html = '<span class="badge badge-secondary">Particular</span>';
                    }

                    $('#estadoAfiliado').html(html);
                }

            }, 'json');

        });

        /* =========================
           🔁 DESTINO / PRACTICA
        ========================= */
        function toggleDestino() {

            let practica = $('#practicas').val();

            if (practica) {

                // Forzar ingreso
                $('select[name="tipo"]').val('ingreso').trigger('change');

                // Bloquear destino
                $('#destino').prop('disabled', true);

            } else {

                // Habilitar destino nuevamente
                $('#destino').prop('disabled', false);

            }
        }

        function filtrarDestinos() {
            let tipo = $('select[name="tipo"]').val();

            $('#destino option').each(function() {
                let t = $(this).data('tipo');
                let categoria = $(this).data('categoria');

                if (!t) return;

                // Ocultar profesional siempre
                if (categoria === 'profesional') {
                    $(this).hide();
                    return;
                }

                // Ocultar fondo si no es admin
                if (categoria === 'fondo' && !esAdmin) {
                    $(this).hide();
                    return;
                }

                // Filtrado por ingreso/egreso
                if (!tipo) {
                    $(this).show();
                } else {
                    $(this).toggle(t === tipo);
                }
            });

            $('#destino').val('');
        }
        $('#destino option').each(function() {
            if ($(this).data('categoria') === 'fondo' && esAdmin) {
                $(this).css({
                    'font-weight': 'bold',
                    'background': '#40c4f8'
                });
            }
        });

        $('#practicas').on('change', toggleDestino);
        $('select[name="tipo"]').on('change', filtrarDestinos);

        /* =========================
           💳 MEDIO DE PAGO
        ========================= */
        function actualizarTransferencia() {
            let medio = $('#medio_pago').val();
            let tipo = $('#transferencia_tipo').val();

            $('#boxTransferenciaTipo').hide();
            $('#boxEmpleadoDestino').hide();

            if (medio === 'transferencia') {
                $('#boxTransferenciaTipo').show();
                if (tipo === 'clinica') {
                    $('#boxEmpleadoDestino').show();
                }
            }
        }

        $('#medio_pago').on('change', function() {
            // Resetear tipo cuando cambia el medio
            $('#transferencia_tipo').val('');
            actualizarTransferencia();
        });

        $('#transferencia_tipo').on('change', actualizarTransferencia);

        actualizarTransferencia();

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

        let cobroId = $(this).data('id');

        try {

            await imprimirMovimiento(cobroId);

        } catch (err) {

            console.error(err);

            Swal.fire(
                'Error',
                err.message || 'No se pudo imprimir',
                'error'
            );

        }

    });
    async function imprimirMovimiento(cobroId) {

        const data = await $.getJSON(
            'ajax/obtener_comprobante_movimiento.php', {
                id: cobroId
            }
        );

        try {

            if (!qz.websocket.isActive()) {
                await qz.websocket.connect();
            }

            const config = qz.configs.create("POS-80C", {
                encoding: "CP437"
            });

            const contenido = [];

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

            function linea(nombre, valor) {

                let izquierda = nombre.substring(0, 70);
                let derecha = "$" + parseFloat(valor).toLocaleString(
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
            let medio = data.medio_pago;

            if (
                data.medio_pago === 'transferencia' &&
                data.transferencia_tipo === 'profesional'
            ) {
                medio = 'COBRADO POR PROFESIONAL';
            } else if (
                data.medio_pago === 'transferencia' &&
                data.transferencia_tipo === 'clinica'
            ) {
                medio = 'TRANSFERENCIA SALA';
            } else {
                medio = 'EFECTIVO';
            }
            const ahora = new Date();

            const fecha =
                ahora.toLocaleDateString("es-AR");

            const hora =
                ahora.toLocaleTimeString("es-AR");

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
                (data.numero_completo || "0001-00000001")
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
               DATOS
            ===================================== */


            contenido.push("\n");

            if (data.paciente) {

                contenido.push(left("PACIENTE:"));
                contenido.push(left(data.paciente));

                contenido.push("\n");
            }

            if (data.profesional) {

                contenido.push(left("PROFESIONAL:"));
                contenido.push(left(data.profesional));

                contenido.push("\n");
            }

            contenido.push(separator());

            /* =====================================
               DETALLE
            ===================================== */

            contenido.push(left("DESCRIPCION"));

            contenido.push("\n");

            contenido.push(
                left(
                    linea(
                        data.concepto || "Movimiento",
                        data.total
                    )
                )
            );

            contenido.push("\n");

            contenido.push(separator());

            /* =====================================
   TOTAL
===================================== */

            contenido.push("\x1B\x45\x01"); // negrita

            contenido.push(center("TOTAL"));

            contenido.push("\x1D\x21\x11");

            contenido.push(
                center(
                    "$ " +
                    parseFloat(data.total).toLocaleString(
                        "es-AR", {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2
                        }
                    )
                )
            );

            contenido.push("\x1D\x21\x00"); // tamaño normal
            contenido.push("\x1B\x45\x00"); // quitar negrita

            contenido.push(separator());

            /* =====================================
               ESTADO
            ===================================== */

            if (data.estado === "anulado") {

                contenido.push(center("***** ANULADO *****"));

                contenido.push(separator());
            }

            /* =====================================
               MEDIO PAGO
            ===================================== */

            contenido.push(center("METODO DE PAGO"));

            contenido.push(center(medio));

            contenido.push(separator());

            /* =====================================
               PIE
            ===================================== */

            contenido.push("\n");

            contenido.push(
                center("¡GRACIAS POR ELEGIRNOS!")
            );

            contenido.push("\n");

            contenido.push(separator());
            contenido.push(separator());

            contenido.push(
                center(
                    "DOCUMENTO NO VALIDO COMO FACTURA"
                )
            );

            contenido.push("\n\n\n\n");

            /* =====================================
               CORTE
            ===================================== */

            contenido.push("\x1D\x56\x00");

            await qz.print(config, contenido);

        } catch (err) {

            console.error(err);

            Swal.fire({
                icon: "error",
                title: "Error imprimiendo",
                text: err.toString()
            });
        }
    }
</script>