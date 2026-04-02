<?php
session_name("turnos");
session_start();
header('Content-Type: application/json');

require_once "../inc/db.php";

$usuarioId = $_SESSION['user_id'] ?? null;
$cajaId = (int)($_POST['caja_id'] ?? 0);
$montoCierre = floatval($_POST['monto_cierre'] ?? 0);

if (!$usuarioId) {
    echo json_encode(['success' => false, 'message' => 'Usuario no autenticado']);
    exit;
}

if (!$cajaId) {
    echo json_encode(['success' => false, 'message' => 'Caja inválida']);
    exit;
}

try {

    $pdo->beginTransaction();

    /* =========================
       🔎 CAJA ABIERTA
    ========================= */
    $stmt = $pdo->prepare("
    SELECT *
    FROM caja_sesion
    WHERE caja_id = :caja_id
    AND estado = 'abierta'
    LIMIT 1
");
$stmt->execute([
    ':caja_id' => $cajaId
]);

    $caja = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$caja) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'No hay caja abierta']);
        exit;
    }

    /* =========================
       💰 TOTAL SISTEMA
    ========================= */
    $stmt = $pdo->prepare("
        SELECT 
            SUM(CASE WHEN tipo = 'INGRESO' THEN monto ELSE 0 END) as ingresos,
            SUM(CASE WHEN tipo = 'EGRESO' THEN monto ELSE 0 END) as egresos
        FROM caja_movimientos
        WHERE caja_id = ?
        AND fecha BETWEEN ? AND NOW()
    ");
    $stmt->execute([
        $cajaId,
        $caja['fecha_apertura']
    ]);

    $totales = $stmt->fetch(PDO::FETCH_ASSOC);

    $ingresos = (float)$totales['ingresos'];
    $egresos = (float)$totales['egresos'];
    $montoSistema = $ingresos - $egresos;

    /* =========================
       🧮 DIFERENCIA
    ========================= */
    $diferencia = $montoCierre - $montoSistema;

    /* =========================
       📝 GUARDAR ARQUEO
    ========================= */
    $stmt = $pdo->prepare("
        INSERT INTO arqueos_caja 
        (caja_id, usuario_id, total_sistema, total_real, diferencia, fecha)
        VALUES (?,?,?,?,?,NOW())
    ");
    $stmt->execute([
        $cajaId,
        $usuarioId,
        $montoSistema,
        $montoCierre,
        $diferencia
    ]);

    $arqueoId = $pdo->lastInsertId();

    /* =========================
       🔴 CERRAR SESION
    ========================= */
    $stmt = $pdo->prepare("
        UPDATE caja_sesion 
        SET estado = 'cerrada',
            fecha_cierre = NOW(),
            monto_cierre = :monto
        WHERE id = :id
    ");
    $stmt->execute([
        ':monto' => $montoCierre,
        ':id' => $caja['id']
    ]);

    /* =========================
       🔓 LIBERAR CAJA
    ========================= */
    $stmt = $pdo->prepare("UPDATE cajas SET activo = 0 WHERE id = ?");
    $stmt->execute([$cajaId]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'total_sistema' => $montoSistema,
        'diferencia' => $diferencia,
        'arqueo_id' => $arqueoId
    ]);

} catch (Exception $e) {

    $pdo->rollBack();

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}