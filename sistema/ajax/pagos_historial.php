<?php
require_once '../inc/db.php';

$profesionalId = (int)($_GET['id'] ?? 0);

if (!$profesionalId) {
    echo '<div class="alert alert-danger text-center">ID de profesional inválido.</div>';
    exit;
}

/* =========================
   CONSULTA HISTORIAL PAGOS
========================= */
$sql = "
SELECT 
    p.Id,
    p.fecha,
    p.monto,
    p.metodo_pago,
    u.usuario AS realizado_por
FROM pagos_profesionales p
LEFT JOIN usuarios u ON u.Id = p.usuario_id
WHERE p.profesional_id = :id
ORDER BY p.fecha DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute([':id' => $profesionalId]);
$pagos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="table-responsive">
  <table class="table table-bordered table-striped datatable-modal">
    <thead class="text-center">
      <tr>
        <th>Fecha</th>
        <th>Monto</th>
        <th>Método de pago</th>
        <th>Realizado por</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($pagos as $p): ?>
      <tr class="text-center">
        <td><?= date('d/m/Y H:i', strtotime($p['fecha'])) ?></td>
        <td>$ <?= number_format($p['monto'], 2, ',', '.') ?></td>
        <td><?= htmlspecialchars($p['metodo_pago']) ?></td>
        <td><?= htmlspecialchars($p['realizado_por']) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>