<?php
session_name("turnos");
session_start();
header('Content-Type: application/json');
require_once "../inc/db.php";

$input = json_decode(file_get_contents("php://input"), true);
$cajaSesionId = (int) ($input['caja_id'] ?? 0);

if (!$cajaSesionId) {
    echo json_encode(['success' => false, 'message' => 'Caja inválida']);
    exit;
}

// 🔥 Traer sesión
$stmt = $pdo->prepare("SELECT monto_inicial FROM caja_sesion WHERE id = ?");
$stmt->execute([$cajaSesionId]);
$cajaSesion = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$cajaSesion) {
    echo json_encode(['success' => false, 'message' => 'Sesión no encontrada']);
    exit;
}

// 🔥 Movimientos
$stmt = $pdo->prepare("
    SELECT 
        COALESCE(SUM(CASE WHEN tipo='INGRESO' THEN monto ELSE 0 END),0) AS ingresos,
        COALESCE(SUM(CASE WHEN tipo='EGRESO' THEN monto ELSE 0 END),0) AS egresos
    FROM caja_movimientos
    WHERE caja_sesion_id = ?
");
$stmt->execute([$cajaSesionId]);
$totales = $stmt->fetch(PDO::FETCH_ASSOC);

// 🔥 TOTAL REAL DEL SISTEMA
$totalSistema = (float)$cajaSesion['monto_inicial']
               + (float)$totales['ingresos']
               - (float)$totales['egresos'];

echo json_encode([
    'success' => true,
    'total_sistema' => $totalSistema
]);