<?php
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