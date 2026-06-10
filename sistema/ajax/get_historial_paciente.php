<?php
require_once __DIR__ . '/../inc/session.php';

if (empty($_SESSION['login']) || $_SESSION['login'] !== 'si') {
    header('Content-Type: application/json');
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autenticado']);
    exit;
}

require_once __DIR__ . '/../inc/csrf.php';
requerirCsrf();
require_once __DIR__ . '/../inc/db.php';
header('Content-Type: application/json');

$paciente_id = (int)($_GET['paciente_id'] ?? 0);
$turno_id = (int)($_GET['turno_id'] ?? 0);

if (!$paciente_id || !$turno_id) {
    echo json_encode(['success' => false]);
    exit;
}

$stmt = $pdo->prepare("
    SELECT 
        c.id,
        c.fecha,
        c.total,
        c.medio_pago,
        c.estado, -- 🔥 AGREGAR ESTO
        e.nombre AS emp_nombre,
        GROUP_CONCAT(DISTINCT p.nombre SEPARATOR ', ') as detalle
    FROM cobros c
    LEFT JOIN cobros_detalle cd ON cd.cobro_id = c.id
    LEFT JOIN practicas p ON p.id = cd.practica_id
    LEFT JOIN empleados e ON e.id = c.empleado_destino_id
    WHERE c.paciente_id = ?
      AND c.turno_id = ?
    GROUP BY c.id, c.fecha, c.total, c.medio_pago, c.estado, e.nombre
    ORDER BY c.fecha DESC
");

$stmt->execute([$paciente_id, $turno_id]);

$data = [];

foreach ($stmt as $r) {

    // 🔹 Formato medio de pago
    $medio = ucfirst($r['medio_pago']);

    if ($r['medio_pago'] === 'transferencia' && $r['emp_nombre']) {
    $medio .= " → " . $r['emp_nombre'];
}

    $data[] = [
        'id' => (int)$r['id'],
        'fecha' => date('d/m/Y H:i', strtotime($r['fecha'])),
        'detalle' => $r['detalle'] ?: '-',
        'total' => (float)$r['total'],
        'medio_pago' => $medio,
        'estado' => $r['estado'] // 🔥 IMPORTANTE
    ];
}

echo json_encode([
    'success' => true,
    'data' => $data
]);