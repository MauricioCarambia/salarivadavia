<?php
require_once __DIR__ . '/../inc/session.php';

if (empty($_SESSION['login']) || $_SESSION['login'] !== 'si') {
    header('Content-Type: application/json');
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autenticado']);
    exit;
}
require_once __DIR__ . '/../inc/db.php';

header('Content-Type: application/json');

$response = ['success' => false];

/* ===============================
   VALIDAR ID
================================ */
$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;

if ($id <= 0) {
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

    if ($stmt->rowCount() > 0) {
        $response['success'] = true;
    } else {
        $response['message'] = 'No se encontró el registro';
    }

} else {
    $response['message'] = 'Error al eliminar';
}

echo json_encode($response);