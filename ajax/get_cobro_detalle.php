<?php
require_once "../inc/db.php";
header('Content-Type: application/json');

$cobro_id = (int)($_GET['cobro_id'] ?? 0);

if (!$cobro_id) {
    echo json_encode(['success' => false]);
    exit;
}

/* =========================
   CABECERA
========================= */
$stmt = $pdo->prepare("
    SELECT c.*, 
           CONCAT(p.apellido,' ',p.nombre) as paciente,
           CONCAT(pr.apellido,' ',pr.nombre) as profesional
    FROM cobros c
    LEFT JOIN pacientes p ON p.Id = c.paciente_id
    LEFT JOIN profesionales pr ON pr.Id = c.profesional_id
    WHERE c.id = ?
");
$stmt->execute([$cobro_id]);
$c = $stmt->fetch(PDO::FETCH_ASSOC);

/* =========================
   DETALLE
========================= */
$stmt = $pdo->prepare("
    SELECT nombre, precio
    FROM cobros_detalle
    WHERE cobro_id = ?
");
$stmt->execute([$cobro_id]);
$detalle = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* =========================
   🔥 REPARTO (LO QUE FALTABA)
========================= */
$stmt = $pdo->prepare("
    SELECT destino, monto
    FROM cobros_reparto
    WHERE cobro_id = ?
");
$stmt->execute([$cobro_id]);

$reparto = [
    'profesional' => 0,
    'clinica' => 0,
    'farmacia' => 0,
    'patologia' => 0
];

foreach ($stmt as $r) {
    $reparto[$r['destino']] = (float)$r['monto'];
}

/* =========================
   RESPUESTA
========================= */
echo json_encode([
    'success' => true,
    'data' => [
        'paciente' => $c['paciente'],
        'profesional' => $c['profesional'],
        'numero' => $c['numero_completo'],
        'total' => (float)$c['total'],
        'detalle' => $detalle,
        'reparto' => $reparto // 🔥 CLAVE
    ]
]);