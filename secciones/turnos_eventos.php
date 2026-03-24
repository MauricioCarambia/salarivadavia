<?php
require_once "../inc/db.php";

date_default_timezone_set('America/Argentina/Buenos_Aires');

header('Content-Type: application/json');

$id = $_GET['profesional_id'] ?? 0;

if (!$id) {
    echo json_encode([]);
    exit;
}

/* =============================
   PROFESIONAL
=============================*/
$stmt = $conexion->prepare("
SELECT
duracion_turnos,
vacaciones_desde,
vacaciones_hasta
FROM profesionales
WHERE Id = :id
");

$stmt->execute([':id' => $id]);
$prof = $stmt->fetch(PDO::FETCH_ASSOC);

$dur = (int)($prof['duracion_turnos'] ?? 15);

/* =============================
   RANGO FECHAS
=============================*/
$start = $_GET['start'] ?? null;
$end   = $_GET['end'] ?? null;

if (!$start || !$end) {
    echo json_encode([]);
    exit;
}

$start = substr($start, 0, 19);
$end   = substr($end, 0, 19);

/* =============================
   EVENTOS
=============================*/
$eventos = [];

/* =============================
   TURNOS
=============================*/
$sql = "
SELECT
t.Id,
t.fecha,
t.sobreturno,
p.nombre,
p.apellido,
p.documento,
p.celular,
DATE_ADD(t.fecha, INTERVAL :dur MINUTE) AS fecha_fin
FROM turnos t
LEFT JOIN pacientes p ON p.Id = t.paciente_id
WHERE t.profesional_id = :id
AND t.fecha BETWEEN :start AND :end
";

$stmt = $conexion->prepare($sql);

$stmt->bindValue(':dur', $dur, PDO::PARAM_INT);
$stmt->bindValue(':id', $id, PDO::PARAM_INT);
$stmt->bindValue(':start', $start);
$stmt->bindValue(':end', $end);

$stmt->execute();

while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {

    $color = $r['sobreturno'] ? '#ffb606' : '#3a87ad';

    $eventos[] = [
        "id" => $r['Id'],

        // 🔥 título limpio (mejor para vista)
        "title" => trim($r['apellido'] . " " . $r['nombre']),

        "start" => date('Y-m-d H:i:s', strtotime($r['fecha'])),
        "end"   => date('Y-m-d H:i:s', strtotime($r['fecha_fin'])),

        "backgroundColor" => $color,
        "borderColor" => $color,

        // 🔥 DATOS PARA TOOLTIP
        "extendedProps" => [
            "documento" => $r['documento'],
            "celular" => $r['celular'],
            "sobreturno" => (int)$r['sobreturno'],
            "hora" => date('H:i', strtotime($r['fecha']))
        ]
    ];
}

echo json_encode($eventos);