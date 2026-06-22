<?php
require_once __DIR__ . '/../inc/session.php';

if (empty($_SESSION['login']) || $_SESSION['login'] !== 'si') {
    header('Content-Type: application/json');
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autenticado']);
    exit;
}

require_once __DIR__ . '/../inc/csrf.php';
requerirCsrf();
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/auditoria.php';
header('Content-Type: application/json');

try {

    $id = (int)($_POST['id'] ?? 0);
    $motivo = trim($_POST['motivo'] ?? '');

    if (!$id) {
        throw new Exception("ID inválido");
    }

    if ($motivo === '') {
        throw new Exception("Debe indicar el motivo de la anulación");
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

    // Revertir el impacto en el turno para que un cobro nuevo no herede
    // el monto del cobro anulado
    if (!empty($cobro['turno_id'])) {
        $pdo->prepare("UPDATE turnos SET pago = COALESCE(pago, 0) - ? WHERE Id = ?")
            ->execute([$cobro['total'], $cobro['turno_id']]);
    }

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

    registrarAuditoria($pdo, 'cobro_anulado', "Se anuló el comprobante N° {$cobro['numero_completo']} por \${$cobro['total']}", $motivo);

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