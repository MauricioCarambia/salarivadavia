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

header('Content-Type: application/json');

$data = json_decode(file_get_contents("php://input"), true);

if (!$data) {
    $data = $_POST;
}

$nombre    = strtolower(trim($data['nombre'] ?? ''));
$tipo      = strtolower(trim($data['tipo'] ?? 'egreso'));
$categoria = strtolower(trim($data['categoria'] ?? 'caja'));
$id        = $data['id'] ?? null;

if (!$nombre) {
    echo json_encode([
        'success' => false,
        'message' => 'El nombre del destino es obligatorio'
    ]);
    exit;
}

try {

    if ($id) {

        // ======================
        // UPDATE
        // ======================
        $stmt = $pdo->prepare("
            UPDATE destinos_reparto 
            SET nombre = ?, tipo = ?, categoria = ?
            WHERE id = ?
        ");
        $stmt->execute([$nombre, $tipo, $categoria, $id]);

    } else {

        // ======================
        // INSERT
        // ======================
        $stmt = $pdo->prepare("
            INSERT INTO destinos_reparto (nombre, tipo, categoria)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$nombre, $tipo, $categoria]);
    }

    echo json_encode([
        'success' => true
    ]);

} catch (PDOException $e) {

    echo json_encode([
        'success' => false,
        'message' => 'Error de base de datos'
    ]);
}