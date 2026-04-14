<?php
session_name("turnos");
session_start();
require_once "../inc/db.php";

$usuarioId = $_SESSION['user_id'];
$cajaId = $_SESSION['caja_id'];
$turno = $_SESSION['turno']; // importante que lo guardes al loguear

try {

    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        SELECT 
            SUM(CASE WHEN tipo='INGRESO' THEN monto ELSE 0 END) as ingresos,
            SUM(CASE WHEN tipo='EGRESO' THEN monto ELSE 0 END) as egresos
        FROM caja_movimientos
        WHERE caja_id = ?
          AND turno = ?
          AND usuario_id = ?
          AND DATE(fecha) = CURDATE()
    ");
    $stmt->execute([$cajaId, $turno, $usuarioId]);
    $totales = $stmt->fetch();

    $ingresos = $totales['ingresos'] ?? 0;
    $egresos = $totales['egresos'] ?? 0;
    $neto = $ingresos - $egresos;

    $stmt = $pdo->prepare("
        INSERT INTO cierre_turnos 
        (caja_id, turno, usuario_id, total_ingresos, total_egresos, total_neto)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$cajaId, $turno, $usuarioId, $ingresos, $egresos, $neto]);

    $id = $pdo->lastInsertId();

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'id' => $id
    ]);

} catch (Exception $e) {

    $pdo->rollBack();

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}