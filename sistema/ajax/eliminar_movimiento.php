<?php
require_once __DIR__ . '/../inc/session.php';
require_once __DIR__ . '/../inc/csrf.php';
requerirCsrf();
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/auditoria.php';
header('Content-Type: application/json');

try {
    $id        = (int)($_POST['id'] ?? 0);
    $motivo    = trim($_POST['motivo'] ?? '');
    $usuarioId = $_SESSION['user_id'] ?? 0;

    if (!$usuarioId) throw new Exception('Usuario no autenticado');
    if ($id <= 0)    throw new Exception('ID inválido');
    if ($motivo === '') throw new Exception('Debe indicar el motivo de la anulación');

    $pdo->beginTransaction();

    /* ==============================
       🔍 OBTENER COBRO
    ============================== */
    $stmt = $pdo->prepare("
        SELECT id, estado, total, numero_completo, concepto
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

    registrarAuditoria($pdo, 'cobro_anulado', "Se anuló el comprobante N° {$cobro['numero_completo']} por \${$cobro['total']}", $motivo);

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