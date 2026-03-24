<?php
require_once '../inc/db.php';

header('Content-Type: application/json');

$response = ['success' => false];

/* ===============================
   VALIDAR PERMISOS
================================ */
if (!isset($_SESSION['tipo']) || 
   !in_array($_SESSION['tipo'], ['admin','contable'])) {

    $response['message'] = 'No autorizado';
    echo json_encode($response);
    exit;
}

/* ===============================
   VALIDAR ID
================================ */
$id = $_POST['id'] ?? 0;

if (!$id) {
    $response['message'] = 'ID inválido';
    echo json_encode($response);
    exit;
}

/* ===============================
   ELIMINAR
================================ */
$stmt = $pdo->prepare("
    DELETE FROM pagos_afiliados
    WHERE Id = :id
");

if ($stmt->execute([':id' => $id])) {
    $response['success'] = true;
} else {
    $response['message'] = 'Error al eliminar';
}

echo json_encode($response);