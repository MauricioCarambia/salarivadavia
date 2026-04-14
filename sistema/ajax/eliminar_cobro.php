<?php
session_name("turnos");
session_start();
require_once "../inc/db.php";
header('Content-Type: application/json');

try {

    $id = (int)($_POST['id'] ?? 0);

    if (!$id) {
        throw new Exception("ID inválido");
    }

    $pdo->beginTransaction();

    // 🔴 Verificar que exista
    $stmt = $pdo->prepare("SELECT * FROM cobros WHERE id = ?");
    $stmt->execute([$id]);
    $cobro = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$cobro) {
        throw new Exception("Cobro no encontrado");
    }

    if ($cobro['estado'] === 'anulado') {
        throw new Exception("El cobro ya está anulado");
    }

    // 🔴 Anular cobro (NO borrar físicamente)
    $stmt = $pdo->prepare("
        UPDATE cobros 
        SET estado = 'anulado'
        WHERE id = ?
    ");
    $stmt->execute([$id]);

    // 🔴 Registrar movimiento inverso en caja
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
        $id,
        'Anulación de cobro'
    ]);

    $pdo->commit();

    echo json_encode([
        'success' => true
    ]);

} catch (Exception $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}