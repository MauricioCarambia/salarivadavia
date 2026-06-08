<?php
require_once __DIR__ . '/../../inc/db.php';
require_once __DIR__ . '/../services/ErpService.php';

header('Content-Type: application/json');

try {
    $fecha = $_GET['fecha'] ?? date('Y-m-d');

    $d = DateTime::createFromFormat('Y-m-d', $fecha);
    if (!$d || $d->format('Y-m-d') !== $fecha) {
        throw new Exception("Fecha inválida");
    }

    $service = new ErpService($pdo);
    echo json_encode([
        'success' => true,
        'data'    => $service->getMovimientos($fecha) ?? []
    ]);
} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'data'    => []
    ]);
}