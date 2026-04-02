<?php
require_once 'inc/db.php';

$id = (int) ($_GET['id'] ?? 0);
$rand = random_int(1000, 9999);

/* ===============================
   FECHAS
================================ */
$desde = $_POST['desde'] ?? date('Y-m-01');
$hasta = $_POST['hasta'] ?? date('Y-m-d');

/* ===============================
   PROFESIONAL + TOTALES
================================ */
$stmt = $pdo->prepare("
    SELECT 
        p.*,
        COALESCE(SUM(pp.monto),0) AS total_pagado,
        (
            SELECT COALESCE(SUM(t.pago),0)
            FROM turnos t
            WHERE t.profesional_id = p.Id
        ) AS total_generado
    FROM profesionales p
    LEFT JOIN pagos_profesionales pp ON pp.profesional_id = p.Id
    WHERE p.Id = :id
");
$stmt->execute([':id' => $id]);
$prof = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$prof) die('<div class="alert alert-danger">Profesional no encontrado</div>');

$nombre = $prof['apellido'] . ' ' . $prof['nombre'];
$saldo = ($prof['total_generado'] * $prof['porcentaje'] / 100) - $prof['total_pagado'];

/* ===============================
   TURNOS
================================ */
$stmtTurnos = $pdo->prepare("
    SELECT 
        t.fecha,
        t.pago,
        pa.apellido,
        pa.nombre
    FROM turnos t
    LEFT JOIN pacientes pa ON pa.Id = t.paciente_id
    WHERE t.profesional_id = :id
    AND t.fecha BETWEEN :desde AND :hasta
    AND t.pago > 0
    ORDER BY t.fecha DESC
");
$stmtTurnos->execute([
  ':id' => $id,
  ':desde' => $desde,
  ':hasta' => $hasta
]);

$turnos = $stmtTurnos->fetchAll(PDO::FETCH_ASSOC);
$total = array_sum(array_column($turnos, 'pago'));
?>

<div class="row mb-3">
  <div class="col-12">

    <!-- CARD PRINCIPAL -->
    <div class="card card-outline card-primary">

      <div class="card-header d-flex justify-content-between align-items-center">
        <h3><i class="fas fa-calendar-alt"></i> Historial de turnos</h3>
        <div>
          <a href="./?seccion=pagos_new&id=<?= $id ?>&nc=<?= $rand ?>" class="btn btn-success btn-sm">
            <i class="fas fa-plus"></i> Nuevo pago
          </a>
          <a href="./?seccion=pagos&nc=<?= $rand ?>" class="btn btn-secondary btn-sm">
            <i class="fas fa-arrow-left"></i> Volver
          </a>
        </div>
      </div>

      <div class="card-body">

        <!-- INFO DEL PROFESIONAL -->
        <div class="row mb-3 text-center">
          <div class="col-md-4">
            <div class="small-box <?= $saldo > 0 ? 'bg-danger' : 'bg-success' ?> text-white">
              <div class="inner">
                <h4>$<?= number_format($saldo, 2, ',', '.') ?></h4>
                <p>Saldo</p>
              </div>
            </div>
          </div>
          <div class="col-md-4">
            <div class="small-box bg-info text-white">
              <div class="inner">
                <h4><?= htmlspecialchars($prof['celular']) ?></h4>
                <p>Celular</p>
              </div>
            </div>
          </div>
          <div class="col-md-4">
            <div class="small-box bg-secondary text-white">
              <div class="inner">
                <h4><?= htmlspecialchars($prof['fijo']) ?></h4>
                <p>Teléfono</p>
              </div>
            </div>
          </div>
        </div>

        <!-- FILTRO DE FECHAS -->
        <form method="POST" id="formFiltro" class="row mb-3">
          <div class="col-md-3">
            <label>Desde</label>
            <input type="date" name="desde" class="form-control" value="<?= $desde ?>">
          </div>
          <div class="col-md-3">
            <label>Hasta</label>
            <input type="date" name="hasta" class="form-control" value="<?= $hasta ?>">
          </div>
          <div class="col-md-3 align-self-end">
            <button class="btn btn-primary btn-block">
              <i class="fas fa-search"></i> Filtrar
            </button>
          </div>
        </form>

        <!-- TABLA TURNOS -->
        <div class="table-responsive">
          <table id="tablaTurnos" class="table table-bordered table-striped table-hover">
            <thead class="thead-light text-center">
              <tr>
                <th>Fecha</th>
                <th>Monto</th>
                <th>Paciente</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($turnos as $t): ?>
                <tr class="text-center">
                  <td><?= date('d/m/Y', strtotime($t['fecha'])) ?></td>
                  <td>$<?= number_format($t['pago'], 2, ',', '.') ?></td>
                  <td><?= htmlspecialchars($t['apellido'] . ' ' . $t['nombre']) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <!-- TOTAL -->
        <div class="alert alert-info text-center mt-3">
          <strong>Total del período: $<?= number_format($total, 2, ',', '.') ?></strong>
        </div>

      </div>
    </div>

  </div>
</div>

<script>
$(function () {

  // Inicializar DataTable
  if (!$.fn.DataTable.isDataTable('#tablaTurnos')) {
    $('#tablaTurnos').DataTable({
      order: [[0, 'desc']],
      pageLength: 25,
      responsive: true,
      autoWidth: false,
      language: {
        url: "//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json"
      },
      dom: 'Bfrtip',
      buttons: ['copy', 'csv', 'excel', 'pdf', 'print']
    });
  }

  // SweetAlert2 en filtrado
  $('#formFiltro').on('submit', function () {
    Swal.fire({
      title: 'Filtrando...',
      text: 'Actualizando turnos',
      allowOutsideClick: false,
      didOpen: () => Swal.showLoading()
    });
  });

});
</script>