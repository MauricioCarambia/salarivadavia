<?php
require_once '../inc/db.php';

header('Content-Type: application/json');

$response = ['success' => false];

/* =========================
   VALIDAR ID
========================= */
$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;

if ($id <= 0) {
    $response['message'] = 'ID inválido';
    echo json_encode($response);
    exit;
}

/* =========================
   ELIMINAR
========================= */
$stmt = $conexion->prepare("
    DELETE FROM lista_espera
    WHERE Id = :id
");

if ($stmt->execute([':id' => $id])) {

    if ($stmt->rowCount() > 0) {
        $response['success'] = true;
    } else {
        $response['message'] = 'No se encontró el registro';
    }

} else {
    $response['message'] = 'Error al eliminar';
}

echo json_encode($response);