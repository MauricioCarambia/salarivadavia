<?php
session_name("turnos");
session_start();
header('Content-Type: application/json');
require_once "../inc/db.php";

$usuarioId = $_SESSION['user_id'] ?? null;
$data = json_decode(file_get_contents('php://input'), true);
$cajaSesionId = (int) ($data['caja_id'] ?? 0);
$montoReal = floatval($data['monto_real'] ?? 0);

if (!$usuarioId) {
    echo json_encode(['success' => false, 'message' => 'Usuario no autenticado']);
    exit;
}
if (!$cajaSesionId) {
    echo json_encode(['success' => false, 'message' => 'Caja inválida']);
    exit;
}
if ($montoReal < 0) {
    echo json_encode(['success' => false, 'message' => 'Monto real inválido']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Obtener la sesión de caja
    $stmt = $pdo->prepare("SELECT * FROM caja_sesion WHERE id = ? AND estado = 'abierta' LIMIT 1");
    $stmt->execute([$cajaSesionId]);
    $cajaSesion = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$cajaSesion) throw new Exception("No hay caja abierta");

    $cajaId = $cajaSesion['caja_id'];

    // Calcular total según sistema
  $stmt = $pdo->prepare("
    SELECT 
        COALESCE(SUM(CASE WHEN tipo='INGRESO' THEN monto ELSE 0 END),0) AS ingresos,
        COALESCE(SUM(CASE WHEN tipo='EGRESO' THEN monto ELSE 0 END),0) AS egresos
    FROM caja_movimientos
    WHERE caja_sesion_id = ?
");
$stmt->execute([$cajaSesionId]);
    $totales = $stmt->fetch(PDO::FETCH_ASSOC);

    $totalSistema = (float) $totales['ingresos'] - (float) $totales['egresos'];
    $diferencia = $montoReal - $totalSistema;

    // Guardar arqueo
    $stmt = $pdo->prepare("
    INSERT INTO arqueos_caja 
    (caja_id, caja_sesion_id, usuario_id, total_sistema, total_real, diferencia, fecha)
    VALUES (?, ?, ?, ?, ?, ?, NOW())
");

$stmt->execute([
    $cajaId,
    $cajaSesionId, // 🔥 CLAVE
    $usuarioId,
    $totalSistema,
    $montoReal,
    $diferencia
]);
    $arqueoId = $pdo->lastInsertId();

    // Cerrar sesión de caja
    $stmt = $pdo->prepare("
        UPDATE caja_sesion
        SET estado='cerrada', fecha_cierre=NOW(), monto_cierre=?
        WHERE id=?
    ");
    $stmt->execute([$montoReal, $cajaSesionId]);

    // Liberar caja
    $stmt = $pdo->prepare("UPDATE cajas SET activo=0 WHERE id=?");
    $stmt->execute([$cajaId]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'diferencia' => $diferencia,
        'cierre_id' => $arqueoId
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
