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
if (!is_numeric($montoReal)) {
    throw new Exception("Monto inválido");
}
try {
    $pdo->beginTransaction();

    // 🔹 Obtener sesión activa
    $stmt = $pdo->prepare("
        SELECT * 
        FROM caja_sesion 
        WHERE id = ? 
        AND estado = 'abierta'
        AND usuario_id = ?
        LIMIT 1
    ");
    $stmt->execute([$cajaSesionId, $usuarioId]);
    $cajaSesion = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$cajaSesion) {
        throw new Exception("No podés cerrar esta caja (no sos el usuario que la abrió o ya está cerrada)");
    }

    $cajaId = $cajaSesion['caja_id'];

    // 🔹 Totales SOLO del sistema (excluye anulados)
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

    $ingresos = (float)$totales['ingresos'];
    $egresos  = (float)$totales['egresos'];

    // 🔥 SOLO movimientos del sistema
    $totalSistema = $ingresos - $egresos;

    // 🔥 LO QUE DEBERÍA HABER EN CAJA
    $cajaEsperada = (float)$cajaSesion['monto_inicial'] + $totalSistema;

    // 🔥 DIFERENCIA REAL
    $diferencia = $montoReal - $cajaEsperada;

    // 🔹 Guardar arqueo
    $stmt = $pdo->prepare("
     INSERT INTO arqueos_caja 
(caja_id, caja_sesion_id, usuario_id, monto_inicial, total_sistema, total_real, diferencia, fecha)
VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
    ");
 $stmt->execute([
    $cajaId,
    $cajaSesionId,
    $usuarioId,
    $cajaSesion['monto_inicial'], // 🔥 clave histórica
    $totalSistema,
    $montoReal,
    $diferencia
]);

    $arqueoId = $pdo->lastInsertId();

    // 🔹 Cerrar sesión
    $stmt = $pdo->prepare("
        UPDATE caja_sesion
        SET estado='cerrada', fecha_cierre=NOW(), monto_cierre=?
        WHERE id=?
    ");
    $stmt->execute([$montoReal, $cajaSesionId]);

    // 🔹 Liberar caja
    $stmt = $pdo->prepare("UPDATE cajas SET activo=0 WHERE id=?");
    $stmt->execute([$cajaId]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'diferencia' => $diferencia,
        'total_sistema' => $totalSistema,
        'caja_esperada' => $cajaEsperada,
        'cierre_id' => $arqueoId
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}