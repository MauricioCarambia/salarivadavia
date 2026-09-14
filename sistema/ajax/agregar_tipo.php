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

$tipo = strtolower(trim($_POST['tipo'] ?? ''));

if (!$tipo) {
    echo json_encode([
        'success' => false,
        'message' => 'El nombre del tipo es obligatorio'
    ]);
    exit;
}

try {

    /* =========================
       VALIDAR EXISTENCIA
    ========================= */
    $stmt = $pdo->prepare("
        SELECT id 
        FROM tipos_reparto 
        WHERE LOWER(TRIM(nombre)) = ?
        LIMIT 1
    ");
    $stmt->execute([$tipo]);

    if ($stmt->fetch()) {
        echo json_encode([
            'success' => false,
            'message' => 'El tipo ya existe'
        ]);
        exit;
    }

    /* =========================
       INSERTAR
    ========================= */
    $stmt = $pdo->prepare("
        INSERT INTO tipos_reparto (nombre) 
        VALUES (?)
    ");
    $stmt->execute([$tipo]);

    $id = $pdo->lastInsertId();

    echo json_encode([
        'success' => true,
        'id' => $id,
        'nombre' => $tipo
    ]);

} catch (PDOException $e) {

    echo json_encode([
        'success' => false,
        'message' => 'Error de base de datos'
    ]);
}