<?php
require_once '../inc/db.php';
header('Content-Type: application/json');

$data = $_POST;

$id        = isset($data['id']) ? (int)$data['id'] : 0;
$destino   = strtolower(trim($data['destino'] ?? ''));
$tipo      = strtolower(trim($data['tipo'] ?? ''));
$categoria = strtolower(trim($data['categoria'] ?? ''));

if (!$id || $destino === '' || $tipo === '' || $categoria === '') {
    echo json_encode([
        'success' => false,
        'message' => 'Datos inválidos'
    ]);
    exit;
}

try {

    /* =========================
       VALIDAR DUPLICADOS
    ========================= */
    $stmt = $pdo->prepare("
        SELECT id 
        FROM destinos_reparto 
        WHERE LOWER(TRIM(nombre)) = ?
        AND id != ?
        LIMIT 1
    ");
    $stmt->execute([$destino, $id]);

    if ($stmt->fetch()) {
        echo json_encode([
            'success' => false,
            'message' => 'Ya existe un destino con ese nombre'
        ]);
        exit;
    }

    /* =========================
       UPDATE
    ========================= */
    $stmt = $pdo->prepare("
        UPDATE destinos_reparto 
        SET nombre = ?, tipo = ?, categoria = ?
        WHERE id = ?
    ");
    $stmt->execute([$destino, $tipo, $categoria, $id]);

    echo json_encode([
        'success' => true
    ]);

} catch (Exception $e) {

    echo json_encode([
        'success' => false,
        'message' => 'Error al actualizar'
    ]);
}