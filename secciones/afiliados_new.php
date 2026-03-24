<?php

$id = $_GET['id'] ?? 0;
$mensaje = '';

/* =========================
   GUARDAR PAGO
========================= */

if (isset($_POST['monto'])) {

    $monto = $_POST['monto'];
    $fecha = $_POST['fecha'] . '-01';

    $stmt = $conexion->prepare("
        INSERT INTO pagos_afiliados
        (paciente_id, monto, fecha_pago, fecha_correspondiente)
        VALUES
        (:paciente_id, :monto, NOW(), :fecha)
    ");

    $stmt->execute([
        ':paciente_id' => $id,
        ':monto' => $monto,
        ':fecha' => $fecha
    ]);

    $mensaje = '<div class="alert alert-info">Pago cargado satisfactoriamente.</div>';
}


/* =========================
   ULTIMO MES PAGADO
========================= */

$stmt = $conexion->prepare("
    SELECT 
        MONTH(fecha_correspondiente) AS mes,
        YEAR(fecha_correspondiente) AS anio
    FROM pagos_afiliados
    WHERE paciente_id = :id
    ORDER BY fecha_correspondiente DESC
    LIMIT 1
");

$stmt->execute([':id' => $id]);

$ultimoPago = $stmt->fetch(PDO::FETCH_ASSOC);


/* =========================
   DATOS PACIENTE
========================= */

$stmtPaciente = $conexion->prepare("
    SELECT 
        obras_sociales.obra_social,
        pacientes.*
    FROM pacientes
    LEFT JOIN obras_sociales 
        ON obras_sociales.Id = pacientes.obra_social_id
    WHERE pacientes.Id = :id
");

$stmtPaciente->execute([':id' => $id]);

$paciente = $stmtPaciente->fetch(PDO::FETCH_ASSOC);

?>

<!-- ================= WRAPPER ================= -->

<div id="wrapper">

<div class="normalheader transition animated fadeIn small-header">
<div class="hpanel">
<div class="panel-body">
<h2>Nuevo pagos de afiliación</h2>
</div>
</div>
</div>

<?= $mensaje ?>

<div class="content animate-panel">
<div class="row">

<!-- ================= FORM PAGO ================= -->

<div class="col-md-12 col-lg-6">
<div class="hpanel">

<div class="panel-heading hbuilt">
<h3>
Último mes:
<?= $ultimoPago 
    ? $meses[$ultimoPago['mes']-1].' de '.$ultimoPago['anio']
    : 'Sin pagos registrados'; ?>
</h3>
</div>

<div class="panel-body">

<form action="./?seccion=afiliados_new&id=<?= $id ?>&nc=<?= $rand ?>"
      method="post"
      class="form-inline">

<label>Monto:</label>

<div class="input-group">
<span class="input-group-addon">$</span>
<input type="number"
       min="0"
       step="any"
       class="form-control"
       name="monto"
       required>
</div>

<br>

<div class="m-t">
<label>Correspondiente al mes:</label>

<div class="input-group">
<span class="input-group-addon">
<i class="fa fa-calendar"></i>
</span>

<input type="month"
       class="form-control"
       name="fecha"
       value="<?= date('Y-m') ?>"
       required>

</div>
</div>

<button type="submit"
        class="btn btn-success m-t pull-right">
Guardar
</button>

</form>

</div>
</div>
</div>


<!-- ================= DATOS PACIENTE ================= -->

<div class="col-lg-6">
<div class="hpanel">

<div class="panel-heading hbuilt">
Datos del paciente
</div>

<div class="panel-body">

<?php if($paciente): ?>

<label>Apellido:</label> <?= $paciente['apellido'] ?><br>
<label>Nombre:</label> <?= $paciente['nombre'] ?><br>
<label>Domicilio:</label> <?= $paciente['domicilio'] ?><br>
<label>Provincia:</label> <?= $paciente['provincia'] ?><br>
<label>Localidad:</label> <?= $paciente['localidad'] ?><br>
<label>Celular:</label> <?= $paciente['celular'] ?><br>
<label>Fijo:</label> <?= $paciente['fijo'] ?><br>
<label>Email:</label> <?= $paciente['email'] ?><br>
<label>Documento:</label> 
<?= $paciente['tipo_documento'] ?>: <?= $paciente['documento'] ?><br>

<label>Fecha nacimiento:</label>
<?= $paciente['nacimiento'] ?><br>

<label>N° socio:</label>
<?= $paciente['nro_afiliado'] ?><br>

<label>Obra social:</label>
<?= $paciente['obra_social'] ?><br>

<label>Plan:</label>
<?= $paciente['obra_social_plan'] ?><br>

<label>Número OS:</label>
<?= $paciente['obra_social_numero'] ?><br>

<label>Sexo:</label>
<?= $paciente['sexo'] ?>

<?php endif; ?>

</div>
</div>
</div>


<div class="col-md-12">
<div class="pull-right">
<a href="./?seccion=socios&nc=<?= $rand ?>"
   class="btn btn-info">
Volver
</a>
</div>
</div>

</div>
</div>
</div>