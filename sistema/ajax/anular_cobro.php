<?php
require_once __DIR__ . '/../inc/db.php';

header('Content-Type: application/json');

try {

    $cobro_id = (int)($_POST['cobro_id'] ?? 0);
    $usuarioId = $_SESSION['user_id'] ?? 0;

    if ($cobro_id <= 0) {
        throw new Exception('ID inválido');
    }

    

    $pdo->beginTransaction();

    /* =========================
       🔒 BLOQUEAR COBRO
    ========================= */
    $stmt = $pdo->prepare("
        SELECT estado, caja_sesion_id, total, numero_completo
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