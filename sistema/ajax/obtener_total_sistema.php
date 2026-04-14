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

// 🔥 Movimientos (EXCLUYENDO cobros anulados correctamente)
$stmt = $pdo->prepare("
    SELECT 
        COALESCE(SUM(CASE WHEN cm.tipo = 'INGRESO' THEN cm.monto ELSE 0 END), 0) AS ingresos,
        COALESCE(SUM(CASE WHEN cm.tipo = 'EGRESO' THEN cm.monto ELSE 0 END), 0) AS egresos
    FROM caja_movimientos cm
    LEFT JOIN cobros c ON c.id = cm.cobro_id
    WHERE cm.caja_sesion_id = ?
    AND c.estado = 'activo'
");
$stmt->execute([$cajaSesionId]);
$totales = $stmt->fetch(PDO::FETCH_ASSOC);

// 🔥 Calcular
$ingresos = (float)$totales['ingresos'];
$egresos  = (float)$totales['egresos'];

$totalSistema = $ingresos - $egresos;

$cajaEsperada = (float)$cajaSesion['monto_inicial'] + $totalSistema;

echo json_encode([
    'success' => true,
    'total_sistema' => $totalSistema,
    'monto_inicial' => (float)$cajaSesion['monto_inicial'],
    'caja_esperada' => $cajaEsperada
]);
