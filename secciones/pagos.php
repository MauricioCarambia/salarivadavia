<?php
require_once 'inc/db.php';

/* ==============================
    📊 CONSULTA DE RENDIMIENTO POR PROFESIONAL 
    - Total_Facturado: Suma de todo el dinero ingresado.
    - Total_A_Cobrar: Lo que le corresponde al profesional.
    - Ganancia_Clinica: La diferencia entre lo facturado y lo que se lleva el médico.
============================== */

$sql = "
SELECT 
p.Id,
    p.nombre AS Nombre,
    p.apellido as Apellido, 
    -- Total bruto generado
    SUM(cr_total.monto) AS Total_Facturado,
    -- Neto para el profesional
    SUM(CASE WHEN cr_total.destino = 'profesional' THEN cr_total.monto ELSE 0 END) AS Total_A_Cobrar,
    -- Diferencia (Lo que queda para la clínica/otros destinos)
    (SUM(cr_total.monto) - SUM(CASE WHEN cr_total.destino = 'profesional' THEN cr_total.monto ELSE 0 END)) AS Ganancia_Clinica
FROM profesionales p
JOIN cobros c ON p.Id = c.profesional_id
JOIN cobros_reparto cr_total ON c.id = cr_total.cobro_id
WHERE c.estado = 'activo'
GROUP BY p.Id, p.nombre
ORDER BY Ganancia_Clinica DESC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $reporte = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error en la consulta: " . $e->getMessage());
}
?>

<div class="row">
  <div class="col-12">
    <div class="card card-outline card-primary">
      <div class="card-header">
        <h3 class="card-title">
          <i class="fas fa-user-md"></i> Facturación y Pagos a Profesionales
        </h3>
      </div>

      <div class="card-body table-responsive">
        <table class="table table-striped datatable">
          <thead>
            <tr class="text-center">
              <th>Profesional</th>
              <th>Total facturado</th>
              <th>Pago profesional</th>
              <th>Ganancia</th>
              <th>Acciones</th>
            </tr>
          </thead>

          <tbody>
            <?php foreach ($reporte as $fila): ?>
              <tr>
                <td><?= htmlspecialchars($fila['Apellido'] . ' ' . $fila['Nombre']) ?></td>
                <td class="text-center">$ <?= number_format($fila['Total_Facturado'], 2, ',', '.') ?></td>
                <td class="text-center">$ <?= number_format($fila['Total_A_Cobrar'], 2, ',', '.') ?></td>
                <td class="text-center">$ <?= number_format($fila['Ganancia_Clinica'], 2, ',', '.') ?></td>                
                
                <td class="text-center">
                  <div class="btn-group">
                    <button class="btn btn-info btn-sm rounded-circle" title="Nuevo pago"
                      onclick="nuevoPago(<?= $fila['Id'] ?>)">
                      <i class="fas fa-plus"></i>
                    </button>
                    <button class="btn btn-success btn-sm rounded-circle" title="Historial pagos"
                      onclick="verPagos(<?= $fila['Id'] ?>)">
                      <i class="fas fa-eye"></i>
                    </button>
                    <button class="btn btn-warning btn-sm rounded-circle" title="Historial turnos"
                      onclick="verTurnos(<?= $fila['Id'] ?>)">
                      <i class="fas fa-calendar-alt"></i>
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

<script>
  $(document).ready(function () {
    $('.datatable').each(function () {
      initDataTable($(this));
    });
  });

  function nuevoPago(profesionalId) {
    Swal.fire({
      title: '¿Desea crear un nuevo pago?',
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: 'Sí, crear',
      cancelButtonText: 'Cancelar'
    }).then((result) => {
      if (result.isConfirmed) {
        window.location.href = `./?seccion=pagos_new&id=${profesionalId}&nc=<?= $rand ?>`;
      }
    });
  }

  function verPagos(profesionalId) {
    window.location.href = `./?seccion=pagos_view&id=${profesionalId}&nc=<?= $rand ?>`;
  }

  function verTurnos(profesionalId) {
    window.location.href = `./?seccion=pagos_fechas&id=${profesionalId}&nc=<?= $rand ?>`;
  }
</script>