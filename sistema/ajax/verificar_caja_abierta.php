<?php
session_name("turnos");
session_start();
header('Content-Type: application/json');

require_once "../inc/db.php";

try {

    $usuarioId = $_SESSION['user_id'] ?? 0;

    if (!$usuarioId) {
        throw new Exception('Usuario no autenticado');
    }

    /* =========================
       🔎 CAJA ABIERTA DEL USUARIO
    ========================= */
    $stmt = $pdo->prepare("
        SELECT cs.*, c.id AS caja_id, c.nombre
        FROM caja_sesion cs
        INNER JOIN cajas c ON c.id = cs.caja_id
        WHERE cs.estado = 'abierta'
        AND cs.usuario_id = ?
        LIMIT 1
    ");

    $stmt->execute([$usuarioId]);
    $caja = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$caja) {
        throw new Exception('No hay caja abierta');
    }

    echo json_encode([
        'success' => true,
        'caja_id' => (int)$caja['caja_id'],
        'caja_sesion_id' => (int)$caja['id'],
        'caja_nombre' => $caja['nombre']
    ]);

} catch (Exception $e) {

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);

}