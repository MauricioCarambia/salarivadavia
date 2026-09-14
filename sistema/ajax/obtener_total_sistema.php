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
require_once "../inc/services/CajaService.php";

header('Content-Type: application/json');

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['caja_id'])) {
    echo json_encode(['success' => false, 'message' => 'Caja inválida']);
    exit;
}

try {

    $service = new CajaService($pdo);

    $calc = $service->calcularSesion((int)$data['caja_id']);

    echo json_encode([
        'success' => true,
        ...$calc
    ]);

} catch (Throwable $e) {

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}