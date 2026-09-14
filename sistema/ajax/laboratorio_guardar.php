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

try {

    $id        = $_POST['id'] ?? null;
    $nombre    = trim($_POST['nombre'] ?? '');
    $direccion = trim($_POST['direccion'] ?? '');
    $telefono  = trim($_POST['telefono'] ?? '');

    if ($nombre === '') {
        throw new Exception('El nombre es obligatorio');
    }

    // validar duplicado (excluyendo el propio registro si es edición)
    $stmt = $pdo->prepare("
        SELECT id FROM laboratorios
        WHERE nombre = :nombre AND id <> :id
        LIMIT 1
    ");
    $stmt->execute([':nombre' => $nombre, ':id' => $id ?: 0]);

    if ($stmt->rowCount() > 0) {
        throw new Exception('Ya existe un laboratorio con ese nombre');
    }

    if ($id) {

        $upd = $pdo->prepare("
            UPDATE laboratorios
            SET nombre = :nombre, direccion = :direccion, telefono = :telefono
            WHERE id = :id
        ");
        $upd->execute([
            ':nombre' => $nombre,
            ':direccion' => $direccion ?: null,
            ':telefono' => $telefono ?: null,
            ':id' => $id
        ]);

    } else {

        $ins = $pdo->prepare("
            INSERT INTO laboratorios (nombre, direccion, telefono)
            VALUES (:nombre, :direccion, :telefono)
        ");
        $ins->execute([
            ':nombre' => $nombre,
            ':direccion' => $direccion ?: null,
            ':telefono' => $telefono ?: null
        ]);
    }

    echo json_encode(['success' => true]);

} catch (Throwable $e) {

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
