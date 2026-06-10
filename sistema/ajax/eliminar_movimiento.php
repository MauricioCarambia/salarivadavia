<?php
require_once __DIR__ . '/../inc/session.php';
require_once __DIR__ . '/../inc/db.php';
header('Content-Type: application/json');

try {
    $id        = (int)($_POST['id'] ?? 0);
    $usuarioId = $_SESSION['user_id'] ?? 0;

    if (!$usuarioId) throw new Exception('Usuario no autenticado');
    if ($id <= 0)    throw new Exception('ID inválido');

    $pdo->beginTransaction();

    /* ==============================
       🔍 OBTENER COBRO
    ============================== */
    $stmt = $pdo->prepare("
        SELECT id, estado
        FROM cobros
        WHERE id = ?
        LIMIT 1
        FOR UPDATE
    ");
    $stmt->execute([$id]);
    $cobro = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$cobro) throw new Exception('Cobro no encontrado');

    if ($cobro['estado'] === 'anulado') {
        throw new Exception('El cobro ya está anulado');
    }

    /* ==============================
       💰 ANULAR COBRO
    ============================== */
    $stmt = $pdo->prepare("UPDATE cobros SET estado = 'anulado' WHERE id = ?");
    $stmt->execute([$id]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Cobro anulado correctamente'
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}