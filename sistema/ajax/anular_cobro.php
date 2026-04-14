<?php
require_once "../inc/db.php";
session_name("turnos");
session_start();

header('Content-Type: application/json');

try {

    $cobro_id = (int)($_POST['cobro_id'] ?? 0);
    $usuarioId = $_SESSION['user_id'] ?? 0;

    if ($cobro_id <= 0) {
        throw new Exception('ID inválido');
    }

    if (!$usuarioId) {
        throw new Exception('Usuario no autenticado');
    }

    $pdo->beginTransaction();

    /* =========================
       🔒 BLOQUEAR COBRO
    ========================= */
    $stmt = $pdo->prepare("
        SELECT estado, caja_id, caja_sesion_id, total, numero_completo
        FROM cobros
        WHERE id = ?
        FOR UPDATE
    ");
    $stmt->execute([$cobro_id]);
    $cobro = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$cobro) {
        throw new Exception("Cobro no encontrado");
    }

    if (strtolower($cobro['estado']) === 'anulado') {
        throw new Exception("El cobro ya está anulado");
    }

    if (!$cobro['caja_sesion_id']) {
        throw new Exception("El cobro no tiene sesión de caja asociada");
    }

    /* =========================
       🔒 VALIDAR CAJA ABIERTA
    ========================= */
    $stmt = $pdo->prepare("
        SELECT estado 
        FROM caja_sesion
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->execute([$cobro['caja_sesion_id']]);
    $caja = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$caja || $caja['estado'] !== 'abierta') {
        throw new Exception("La caja está cerrada, no se puede anular");
    }

    /* =========================
       🔴 ANULAR COBRO
    ========================= */
    $stmt = $pdo->prepare("
        UPDATE cobros 
        SET estado = 'anulado'
        WHERE id = ?
    ");
    $stmt->execute([$cobro_id]);

    /* =========================
       💸 MOVIMIENTO CAJA (REVERSO)
    ========================= */
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
        (float)$cobro['total'],
        $cobro_id,
        'Anulación de cobro #' . $cobro_id
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