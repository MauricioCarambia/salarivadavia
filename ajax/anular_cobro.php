<?php
require_once "../inc/db.php";
header('Content-Type: application/json');

$cobro_id = (int)($_POST['cobro_id'] ?? 0);

if (!$cobro_id) {
    echo json_encode(['success' => false, 'message' => 'ID inválido']);
    exit;
}

try {

    $pdo->beginTransaction();

    // 🔒 Validar estado
    $stmt = $pdo->prepare("SELECT estado, caja_id, caja_sesion_id, total, numero_completo FROM cobros WHERE id = ?");
    $stmt->execute([$cobro_id]);
    $cobro = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$cobro) {
        throw new Exception("Cobro no encontrado");
    }

    if ($cobro['estado'] === 'anulado') {
        throw new Exception("El cobro ya está anulado");
    }

    if (!$cobro['caja_sesion_id']) {
        throw new Exception("El cobro no tiene sesión de caja asociada");
    }

    // 🔴 Marcar como anulado
    $stmt = $pdo->prepare("
        UPDATE cobros 
        SET estado = 'anulado' 
        WHERE id = ?
    ");
    $stmt->execute([$cobro_id]);

    // 🔥 Movimiento negativo en caja (CORRECTO CON SESIÓN)
    $stmt = $pdo->prepare("
        INSERT INTO caja_movimientos 
        (caja_id, caja_sesion_id, tipo, concepto, monto, fecha, cobro_id, descripcion)
        VALUES (?,?,?,?,?,NOW(),?,?)
    ");

    $stmt->execute([
        $cobro['caja_id'],
        $cobro['caja_sesion_id'],
        'EGRESO',
        'Anulación ' . $cobro['numero_completo'],
        $cobro['total'],
        $cobro_id,
        'Anulación de cobro #' . $cobro_id
    ]);

    $pdo->commit();

    echo json_encode(['success' => true]);

} catch (Exception $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}