<?php
require_once "../inc/db.php";

$id     = $_GET['profesional'] ?? 0;
$fecha  = $_GET['fecha'] ?? date('Y-m-d');

/* ===============================
   PROFESIONAL
================================*/
$stmt = $conexion->prepare("
SELECT p.apellido,
       p.nombre,
       e.especialidad
FROM profesionales p
LEFT JOIN especialidades e
ON e.Id = p.especialidad_id
WHERE p.Id=:id
");

$stmt->execute([':id'=>$id]);
$prof = $stmt->fetch(PDO::FETCH_ASSOC);


/* ===============================
   TURNOS DEL DIA
================================*/
$stmt = $conexion->prepare("
SELECT
t.fecha,
pa.nombre,
pa.apellido,
pa.documento
FROM turnos t
LEFT JOIN pacientes pa ON pa.Id=t.paciente_id
WHERE t.profesional_id=:id
AND DATE(t.fecha)=:fecha
ORDER BY t.fecha ASC
");

$stmt->execute([
    ':id'=>$id,
    ':fecha'=>$fecha
]);

$turnos = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">

<title>Agenda <?= $fecha ?></title>

<style>

body{
    font-family: Arial, Helvetica, sans-serif;
    margin:40px;
    color:#000;
}

/* ================= HEADER ================= */

.header{
    display:flex;
    align-items:center;
    border-bottom:2px solid #000;
    padding-bottom:15px;
    margin-bottom:25px;
}

.logo{
    width:120px;
}

.titulo{
    flex:1;
    text-align:center;
}

.titulo h1{
    margin:0;
    font-size:26px;
}

.titulo h2{
    margin:5px 0;
    font-weight:normal;
}

/* ================= TABLA ================= */

table{
    width:100%;
    border-collapse:collapse;
}

th{
    background:#34495e;
    color:#fff;
    padding:10px;
    font-size:14px;
}

td{
    border:1px solid #ccc;
    padding:8px;
    font-size:14px;
}

tr:nth-child(even){
    background:#f5f5f5;
}

/* ================= FOOTER ================= */

.footer{
    margin-top:40px;
}

.obs{
    margin-top:40px;
    border-top:1px solid #000;
    padding-top:10px;
    height:120px;
}

/* ================= PRINT ================= */

@media print{

    body{
        margin:15px;
    }

}

</style>
</head>

<body>

<!-- ================= HEADER ================= -->

<div class="header">

    <img src="../images/logo_blanco.png" class="logo">

    <div class="titulo">
        <h1>AGENDA DIARIA</h1>

        <h2>
            Dr/a.
            <?= htmlspecialchars($prof['apellido']." ".$prof['nombre']) ?>
        </h2>

        <div>
            <?= htmlspecialchars($prof['especialidad']) ?>
        </div>

        <strong>
            Fecha:
            <?= date('d/m/Y',strtotime($fecha)) ?>
        </strong>
    </div>

</div>


<!-- ================= TABLA ================= -->

<table>

<tr>
    <th width="120">Hora</th>
    <th>Paciente</th>
    <th>documento</th>
</tr>

<?php if(count($turnos)==0): ?>

<tr>
<td colspan="3" style="text-align:center">
Sin turnos asignados
</td>
</tr>

<?php endif; ?>


<?php foreach($turnos as $t): ?>

<tr>
<td><?= date("H:i",strtotime($t['fecha'])) ?></td>
<td><?= htmlspecialchars($t['apellido']." ".$t['nombre']) ?></td>
<td><?= htmlspecialchars($t['documento']) ?></td>
</tr>

<?php endforeach; ?>

</table>


<!-- ================= OBS ================= -->

<div class="obs">
<strong>Observaciones:</strong>
</div>


<script>
window.onload = () => window.print();
</script>

</body>
</html>