<?php
require_once __DIR__ . '/../inc/db.php';

$id = $_GET['id'] ?? null;

if (!$id) {
    echo "ID de cobro no proporcionado.";
    exit;
}

// Consulta completa para el detalle
$stmt = $pdo->prepare("
    SELECT 
        c.*,
        p.nombre AS pac_nom, p.apellido AS pac_ape,
        pr.nombre AS prof_nom, pr.apellido AS prof_ape,
        u.nombre AS usuario_nombre
    FROM cobros c
    LEFT JOIN pacientes p ON p.Id = c.paciente_id
    LEFT JOIN profesionales pr ON pr.Id = c.profesional_id
    LEFT JOIN empleados u ON u.id = c.usuario_id
    WHERE c.id = ?
");
$stmt->execute([$id]);
$cobro = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$cobro) {
    echo "No se encontró la información del cobro.";
    exit;
}
?>

<div class="row">
    <div class="col-md-6">
        <p><strong>Fecha:</strong> <?= date('d/m/Y H:i', strtotime($cobro['fecha'])) ?></p>
        <p><strong>Paciente:</strong> <?= htmlspecialchars(($cobro['pac_ape'] ?? '') . ' ' . ($cobro['pac_nom'] ?? 'N/A')) ?></p>
        <p><strong>Profesional:</strong> <?= htmlspecialchars(($cobro['prof_ape'] ?? '') . ' ' . ($cobro['prof_nom'] ?? 'N/A')) ?></p>
    </div>
    <div class="col-md-6 text-right">
        <h3 class="text-primary">Total: $<?= number_format($cobro['total'], 2) ?></h3>
        <p><strong>Registrado por:</strong> <?= htmlspecialchars($cobro['usuario_nombre'] ?? 'Sistema') ?></p>
    </div>
</div>

<hr>

<h6><strong>Distribución de Fondos:</strong></h6>
<table class="table table-sm table-bordered">
    <thead class="bg-light">
        <tr>
            <th>Destino</th>
            <th class="text-right">Monto</th>
        </tr>
    </thead>
    <tbody>
        <?php
        $reparto = $pdo->prepare("
            SELECT cr.*, d.nombre 
            FROM cobros_reparto cr
            JOIN destinos_reparto d ON d.id = cr.destino_id
            WHERE cr.cobro_id = ?
        ");
        $reparto->execute([$id]);
        while ($r = $reparto->fetch(PDO::FETCH_ASSOC)): ?>
            <tr>
                <td><?= htmlspecialchars($r['nombre']) ?></td>
                <td class="text-right">$<?= number_format($r['monto'], 2) ?></td>
            </tr>
        <?php endwhile; ?>
    </tbody>
</table>

<?php if (!empty($cobro['observaciones'])): ?>
    <div class="mt-2 text-muted">
        <strong>Observaciones:</strong><br>
        <?= nl2br(htmlspecialchars($cobro['observaciones'])) ?>
    </div>
<?php endif; ?>