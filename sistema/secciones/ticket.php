<?php
require_once __DIR__ . '/../inc/db.php';

$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT c.*, 
           p.nombre AS paciente_nombre, p.apellido AS paciente_apellido,
           pr.nombre AS profesional_nombre, pr.apellido AS profesional_apellido
    FROM cobros c
    LEFT JOIN pacientes p ON p.Id = c.paciente_id
    LEFT JOIN profesionales pr ON pr.Id = c.profesional_id
    WHERE c.id = ?
");
$stmt->execute([$id]);
$cobro = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$cobro) {
    echo "Cobro no encontrado";
    exit;
}

$stmt = $pdo->prepare("
    SELECT * FROM cobros_detalle
    WHERE cobro_id = ?
");
$stmt->execute([$id]);
$detalle = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("
    SELECT destino, SUM(monto) as total
    FROM cobros_reparto
    WHERE cobro_id = ?
    GROUP BY destino
");
$stmt->execute([$id]);
$reparto = $stmt->fetchAll(PDO::FETCH_ASSOC);

function getTotal($arr, $key){
    foreach($arr as $r){
        if($r['destino'] == $key) return $r['total'];
    }
    return 0;
}
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Ticket</title>

<style>
body{
    font-family: monospace;
    width: 300px; /* 🔥 80mm aprox */
    margin: 0 auto;
    font-size: 13px;
}

.center{text-align:center;}
.right{text-align:right;}

hr{
    border: none;
    border-top: 1px dashed #000;
    margin: 6px 0;
}

/* 🔥 línea de producto tipo ticket */
.item{
    display:flex;
    justify-content:space-between;
}

.bold{
    font-weight:bold;
}
</style>
</head>

<body onload="window.print(); setTimeout(()=>window.close(), 500);">

<div class="center">
    <div class="bold">SALA RIVADAVIA</div>
    Av. Eva Perón 695<br>
    Temperley<br>
    <hr>
    <b>COMPROBANTE</b><br>
    <?= $cobro['numero_completo'] ?><br>
    <hr>
</div>

Fecha: <?= date('d/m/Y H:i', strtotime($cobro['fecha'])) ?><br>
Paciente: <?= $cobro['paciente_apellido'] . ' ' . $cobro['paciente_nombre'] ?><br>
Profesional: <?= $cobro['profesional_apellido'] . ' ' . $cobro['profesional_nombre'] ?><br>

<hr>

<?php foreach($detalle as $d): ?>
<div class="item">
    <span><?= substr($d['nombre'],0,22) ?></span>
    <span>$<?= number_format($d['precio'],2) ?></span>
</div>
<?php endforeach; ?>

<hr>

<div class="item bold">
    <span>TOTAL</span>
    <span>$<?= number_format($cobro['total'],2) ?></span>
</div>

<div class="center">
    Gracias por su visita<br>
    ----------------------<br>
    No válido como factura
</div>

</body>
</html>