<?php
require_once __DIR__ . '/../inc/session.php';
require_once __DIR__ . '/../inc/csrf.php';
requerirCsrf();
require_once __DIR__ . '/../inc/db.php';

$usuarioId = $_SESSION['user_id'] ?? 0;
if (!$usuarioId) {
    echo '<p class="text-danger">Usuario no autenticado.</p>';
    exit;
}

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    echo '<p class="text-danger">ID de cobro no válido.</p>';
    exit;
}

/* ==============================
   📄 CABECERA
============================== */
$stmt = $pdo->prepare("
    SELECT
        c.*,
        CONCAT(COALESCE(p.apellido,''), ' ', COALESCE(p.nombre,''))  AS paciente,
        CONCAT(COALESCE(pr.apellido,''), ' ', COALESCE(pr.nombre,'')) AS profesional,
        u.nombre AS usuario_nombre
    FROM cobros c
    LEFT JOIN pacientes    p  ON p.Id   = c.paciente_id
    LEFT JOIN profesionales pr ON pr.Id  = c.profesional_id
    LEFT JOIN empleados     u  ON u.id   = c.usuario_id
    WHERE c.id = ?
    LIMIT 1
");
$stmt->execute([$id]);
$cobro = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$cobro) {
    echo '<p class="text-danger">No se encontró la información del cobro.</p>';
    exit;
}

/* ==============================
   💰 REPARTO AGRUPADO
============================== */
$stmt = $pdo->prepare("
    SELECT d.nombre, SUM(cr.monto) AS monto
    FROM cobros_reparto cr
    JOIN destinos_reparto d ON d.id = cr.destino_id
    WHERE cr.cobro_id = ?
    GROUP BY d.nombre
");
$stmt->execute([$id]);
$repartoData = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalReparto = 0;
foreach ($repartoData as $r) {
    $totalReparto += (float)$r['monto'];
}

/* ==============================
   🧾 FORMATEOS
============================== */
$fecha       = !empty($cobro['fecha']) ? date('d/m/Y H:i', strtotime($cobro['fecha'])) : '-';
$paciente    = trim($cobro['paciente'])    ?: '-';
$profesional = trim($cobro['profesional']) ?: '-';
$usuario     = htmlspecialchars($cobro['usuario_nombre'] ?? 'Sistema');
$total       = (float)$cobro['total'];
$estado      = $cobro['estado'] ?? 'activo';
?>

<div class="row">
    <div class="col-md-6">
        <p><strong>Fecha:</strong> <?= $fecha ?></p>
        <p><strong>Paciente:</strong> <?= htmlspecialchars($paciente) ?></p>
        <p><strong>Profesional:</strong> <?= htmlspecialchars($profesional) ?></p>
        <p><strong>Comprobante:</strong> <?= htmlspecialchars($cobro['numero_completo']) ?></p>
    </div>
    <div class="col-md-6 text-right">
        <h3 class="<?= $estado === 'anulado' ? 'text-danger' : 'text-primary' ?>">
            Total: $<?= number_format($total, 2) ?>
        </h3>
        <?php if ($estado === 'anulado'): ?>
            <span class="badge badge-danger">ANULADO</span>
        <?php endif; ?>
        <p><strong>Registrado por:</strong> <?= $usuario ?></p>
    </div>
</div>

<hr>

<h6><strong>📦 Distribución de Fondos:</strong></h6>

<table class="table table-sm">
    <thead class="bg-light">
        <tr>
            <th>Destino</th>
            <th class="text-right">Monto</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($repartoData)): ?>
            <tr>
                <td colspan="2" class="text-center text-muted">Sin distribución registrada</td>
            </tr>
        <?php endif; ?>
        <?php foreach ($repartoData as $r): ?>
            <tr>
                <td><?= htmlspecialchars($r['nombre']) ?></td>
                <td class="text-right">$<?= number_format((float)$r['monto'], 2) ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
    <tfoot>
        <tr class="font-weight-bold">
            <td class="text-right">Total repartido:</td>
            <td class="text-right">$<?= number_format($totalReparto, 2) ?></td>
        </tr>
        <?php $diferencia = round($total - $totalReparto, 2); ?>
        <?php if ($diferencia != 0): ?>
            <tr>
                <td colspan="2" class="text-danger text-center">
                    ⚠ Diferencia detectada: $<?= number_format($diferencia, 2) ?>
                </td>
            </tr>
        <?php endif; ?>
    </tfoot>
</table>

<?php if (!empty($cobro['observaciones'])): ?>
    <div class="mt-2 text-muted">
        <strong>Observaciones:</strong><br>
        <?= nl2br(htmlspecialchars($cobro['observaciones'])) ?>
    </div>
<?php endif; ?>