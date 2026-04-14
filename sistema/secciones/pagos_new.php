<?php
require_once 'inc/db.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$rand = random_int(1000, 9999);
$mensaje = '';

/* =============================
   GUARDAR PAGO
=============================*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['monto'])) {

    $monto = floatval($_POST['monto'] ?? 0);
    $fecha = $_POST['fecha'] ?? date('Y-m-d');

    if ($monto <= 0) {
        echo json_encode(['success' => false, 'message' => 'El monto debe ser mayor a 0']);
        exit;
    }

    $stmt = $pdo->prepare("
        INSERT INTO pagos_profesionales (profesional_id, monto, fecha)
        VALUES (:id, :monto, :fecha)
    ");

    $ok = $stmt->execute([
        ':id' => $id,
        ':monto' => $monto,
        ':fecha' => $fecha
    ]);

    echo json_encode($ok ? ['success' => true] : ['success' => false, 'message' => 'Error al guardar el pago']);
    exit;
}

/* =============================
   PROFESIONAL + TOTALES
=============================*/
$stmt = $pdo->prepare("
SELECT 
    p.*,
    CONCAT(p.apellido,' ',p.nombre) AS nombre,
    COALESCE(pp.total_pagado,0) AS total_pagado,
    COALESCE(t.total_turnos,0) * p.porcentaje / 100 AS total_generado
FROM profesionales p
LEFT JOIN (
    SELECT profesional_id, SUM(monto) total_pagado
    FROM pagos_profesionales
    GROUP BY profesional_id
) pp ON pp.profesional_id = p.Id
LEFT JOIN (
    SELECT profesional_id, SUM(pago) total_turnos
    FROM turnos
    GROUP BY profesional_id
) t ON t.profesional_id = p.Id
WHERE p.Id = :id
");

$stmt->execute([':id' => $id]);
$prof = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$prof) die('<div class="alert alert-danger">Profesional no encontrado</div>');

$saldo = $prof['total_generado'] - $prof['total_pagado'];
?>

<div class="card card-outline card-info">

  <div class="card-header">
    <h3 class="card-title"><i class="fas fa-hand-holding-usd"></i> Nuevo Pago - <?= htmlspecialchars($prof['nombre']) ?></h3>
  </div>

  <div class="card-body">

    <!-- INFO PROFESIONAL -->
    <div class="row mb-3 text-center">
      <div class="col-md-3">
        <div class="small-box <?= $saldo > 0 ? 'bg-danger' : 'bg-success' ?> text-white">
          <div class="inner">
            <h4>$<?= number_format($saldo,2,',','.') ?></h4>
            <p>Saldo</p>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="small-box bg-info text-white">
          <div class="inner">
            <h4><?= htmlspecialchars($prof['celular']) ?></h4>
            <p>Celular</p>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="small-box bg-secondary text-white">
          <div class="inner">
            <h4><?= htmlspecialchars($prof['fijo']) ?></h4>
            <p>Teléfono</p>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="small-box bg-warning text-dark">
          <div class="inner">
            <h4><?= $prof['porcentaje'] ?>%</h4>
            <p>Porcentaje</p>
          </div>
        </div>
      </div>
    </div>

    <!-- FORMULARIO PAGO -->
    <form id="formPago">
      <input type="hidden" name="id" value="<?= $id ?>">

      <div class="row">
        <div class="col-md-4">
          <label>Monto</label>
          <div class="input-group">
            <span class="input-group-text"><i class="fas fa-dollar-sign"></i></span>
            <input type="number" step="0.01" name="monto" class="form-control" placeholder="0.00" required>
          </div>
        </div>

        <div class="col-md-4">
          <label>Fecha</label>
          <input type="date" name="fecha" class="form-control" value="<?= date('Y-m-d') ?>" required>
        </div>

        <div class="col-md-4 d-flex align-items-end">
          <button type="submit" class="btn btn-success w-100" id="btnGuardar">
            <i class="fas fa-save"></i> Guardar
          </button>
        </div>
      </div>
    </form>

  </div>
</div>

<a href="./?seccion=pagos&nc=<?= $rand ?>" class="btn btn-secondary float-right mt-2">
  <i class="fas fa-arrow-left"></i> Volver
</a>

<script>
$(function(){

  $('#formPago').submit(function(e){
    e.preventDefault();

    let $btn = $('#btnGuardar');
    $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Guardando...');

    $.post(window.location.href, $(this).serialize(), function(resp){
        if(resp.success){
            Swal.fire({
                icon:'success',
                title:'Pago registrado',
                timer:1500,
                showConfirmButton:false
            }).then(()=> location.reload());
        } else {
            Swal.fire('Error', resp.message || 'Error desconocido', 'error');
            $btn.prop('disabled', false).html('<i class="fas fa-save"></i> Guardar');
        }
    }, 'json').fail(function(){
        Swal.fire('Error', 'No se pudo conectar con el servidor', 'error');
        $btn.prop('disabled', false).html('<i class="fas fa-save"></i> Guardar');
    });

  });

});
</script>