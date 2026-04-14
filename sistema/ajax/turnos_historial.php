<?php
require_once '../inc/db.php';

$profesionalId = (int)($_GET['id'] ?? 0);

if (!$profesionalId) {
    echo '<div class="alert alert-danger text-center">ID de profesional inválido.</div>';
    exit;
}

/* =========================
   CONSULTA HISTORIAL TURNOS
========================= */
$sql = "
SELECT 
    t.Id,
    t.fecha,
    t.hora,
    CONCAT(p.apellido, ', ', p.nombre) AS paciente,
    t.pago
FROM turnos t
LEFT JOIN pacientes p ON p.Id = t.paciente_id
WHERE t.profesional_id = :id
ORDER BY t.fecha DESC, t.hora DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute([':id' => $profesionalId]);
$turnos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="table-responsive">
  <table class="table table-bordered table-striped datatable-modal">
    <thead class="text-center">
      <tr>
        <th>Fecha</th>
        <th>Hora</th>
        <th>Paciente</th>
        <th>Monto</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($turnos as $t): ?>
      <tr class="text-center">
        <td><?= date('d/m/Y', strtotime($t['fecha'])) ?></td>
        <td><?= date('H:i', strtotime($t['hora'])) ?></td>
        <td><?= htmlspecialchars($t['paciente']) ?></td>
        <td>$ <?= number_format($t['pago'], 2, ',', '.') ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>