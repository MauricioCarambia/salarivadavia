<?php
session_name("turnos");
session_start();
require_once __DIR__ . '/../../inc/db.php';
require_once __DIR__ . '/../services/ErpService.php';

header('Content-Type: application/json');

try {
    $service = new ErpService($pdo);
    echo json_encode([
        'success' => true,
        'kpis'    => $service->getKpis()
    ]);
} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}