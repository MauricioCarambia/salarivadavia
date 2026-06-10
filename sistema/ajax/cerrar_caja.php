<?php

require_once __DIR__ . '/../inc/session.php';
require_once __DIR__ . "/../inc/db.php";
require_once __DIR__ . "/../inc/services/CajaService.php";

header('Content-Type: application/json');

if (empty($_SESSION['login']) || $_SESSION['login'] !== 'si') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Usuario no autenticado']);
    exit;
}

// ==============================
// 📥 INPUT
// ==============================
$cajaSesionId = isset($_POST['caja_id']) ? (int)$_POST['caja_id'] : 0;
$montoReal    = isset($_POST['monto_real']) ? (float)$_POST['monto_real'] : 0;

// ==============================
// 🚫 VALIDACIÓN BÁSICA
// ==============================
if ($cajaSesionId <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Caja inválida'
    ]);
    exit;
}

if ($montoReal < 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Monto inválido'
    ]);
    exit;
}

// ⚠️ SIN VALIDACIÓN DE SESIÓN
$usuarioId = $_SESSION['user_id'] ?? 0;

try {

    // 🔥 NO VALIDAMOS ROL
    $rol = null;

    $service = new CajaService($pdo);

    $resultado = $service->cerrarCaja(
        $cajaSesionId,
        $montoReal,
        (int)$usuarioId
    );

    echo json_encode($resultado);

} catch (Throwable $e) {

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}