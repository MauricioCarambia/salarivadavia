<?php
session_name("turnos");
session_start();
header('Content-Type: application/json');

require_once "../inc/db.php";

$usuarioId = $_SESSION['user_id'] ?? null;

if (!$usuarioId) {
    echo json_encode(['success' => false, 'message' => 'Usuario no autenticado']);
    exit;
}

// 🔎 Buscar si hay caja abierta
$stmt = $pdo->prepare("SELECT * FROM caja_sesion WHERE estado = 'abierta' LIMIT 1");
$stmt->execute();
$caja = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$caja) {
    echo json_encode(['success' => false, 'message' => 'No hay caja abierta']);
    exit;
}

echo json_encode(['success' => true, 'caja_id' => $caja['caja_id']]);