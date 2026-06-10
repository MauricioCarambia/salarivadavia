<?php
require_once __DIR__ . '/../inc/session.php';

if (empty($_SESSION['login']) || $_SESSION['login'] !== 'si') {
    header('Content-Type: application/json');
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autenticado']);
    exit;
}
require_once '../inc/db.php';

header('Content-Type: application/json');

$id = $_GET['id'] ?? 0;

$stmt = $pdo->prepare("
    SELECT 
        c.*,
        p.nombre AS paciente_nombre,
        p.apellido AS paciente_apellido,
        pr.nombre AS profesional_nombre,
        pr.apellido AS profesional_apellido
    FROM cobros c
    LEFT JOIN pacientes p ON p.id = c.paciente_id
    LEFT JOIN profesionales pr ON pr.id = c.profesional_id
    WHERE c.id = ?
");
$stmt->execute([$id]);

$c = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$c) {
    echo json_encode([
        "success" => false,
        "message" => "Movimiento no encontrado"
    ]);
    exit;
}

/* =========================
   🧾 DETALLE (si existe)
========================= */
$stmt = $pdo->prepare("
    SELECT nombre, precio
    FROM cobros_detalle
    WHERE cobro_id = ?
");
$stmt->execute([$id]);

$detalle = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* =========================
   🧠 ARMAR RESPUESTA
========================= */
echo json_encode([
    "success" => true,
    "numero" => $c['numero_completo'],
    "fecha" => $c['fecha'],
    "concepto" => $c['concepto'],
    "paciente" => trim(($c['paciente_nombre'] ?? '') . ' ' . ($c['paciente_apellido'] ?? '')),
    "profesional" => trim(($c['profesional_nombre'] ?? '') . ' ' . ($c['profesional_apellido'] ?? '')),
    "medio_pago" => $c['medio_pago'],
    "total" => (float)$c['total'],
    "estado" => $c['estado'],
    "tipo" => $c['tipo'],
    "detalle" => $detalle ?: [
        [
            "nombre" => $c['concepto'],
            "precio" => (float)$c['total']
        ]
    ]
]);