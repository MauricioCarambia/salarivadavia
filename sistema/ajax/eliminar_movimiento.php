<?php
require_once "../inc/db.php";
session_name("turnos");
session_start();

header('Content-Type: application/json');

try {

    $id = (int)($_POST['id'] ?? 0);
    $usuarioId = $_SESSION['user_id'] ?? 0;

    if ($id <= 0) {
        throw new Exception('ID inválido');
    }

    if (!$usuarioId) {
        throw new Exception('Sesión no válida');
    }

    $pdo->beginTransaction();

    // 🔍 Obtener movimiento
    $stmt = $pdo->prepare("
        SELECT id, cobro_id
        FROM caja_movimientos 
        WHERE id = ?
    ");
    $stmt->execute([$id]);
    $mov = $stmt->fetch(PDO::FETCH_ASSOC);

  
    // 💰 Si tiene cobro → anular TODO el circuito
    if (!empty($mov['cobro_id'])) {

        // Anular cobro
        $stmt = $pdo->prepare("
            UPDATE cobros 
            SET estado = 'anulado'
            WHERE id = ?
        ");
        $stmt->execute([$mov['cobro_id']]);

      
    }

    

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Movimiento anulado correctamente'
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