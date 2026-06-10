<?php
require_once __DIR__ . '/../inc/session.php';

if (empty($_SESSION['login']) || $_SESSION['login'] !== 'si') {
    header('Content-Type: application/json');
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autenticado']);
    exit;
}
require_once __DIR__ . '/../inc/db.php';

$id = $_POST['id'] ?? null;

$practica_id = $_POST['practica_id'] ?? null;
$precio_particular = $_POST['precio_particular'] ?? null;
$precio_socio = $_POST['precio_socio'] ?? null;

// 🔥 PARA EDITAR INDIVIDUAL
$tipo_paciente = $_POST['tipo_paciente'] ?? null;
$precio = $_POST['precio'] ?? null;
$activo = $_POST['activo'] ?? 1;

if (!$practica_id && !$id) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit;
}

try {

    $pdo->beginTransaction();

    // =========================
    // ✏️ EDITAR (1 SOLO REGISTRO)
    // =========================
    if ($id) {

        $stmt = $pdo->prepare("
            UPDATE practicas_precios
            SET practica_id = ?, tipo_paciente = ?, precio = ?, activo = ?
            WHERE id = ?
        ");

        $stmt->execute([
            $practica_id,
            $tipo_paciente,
            $precio,
            $activo,
            $id
        ]);

    } else {

        // =========================
        // ➕ CREAR (PUEDE CREAR 2)
        // =========================

        if ($precio_particular !== '' && $precio_particular !== null) {

            $stmt = $pdo->prepare("
                INSERT INTO practicas_precios (practica_id, tipo_paciente, precio, activo)
                VALUES (?, 'particular', ?, 1)
            ");
            $stmt->execute([$practica_id, $precio_particular]);
        }

        if ($precio_socio !== '' && $precio_socio !== null) {

            $stmt = $pdo->prepare("
                INSERT INTO practicas_precios (practica_id, tipo_paciente, precio, activo)
                VALUES (?, 'socio', ?, 1)
            ");
            $stmt->execute([$practica_id, $precio_socio]);
        }
    }

    $pdo->commit();

    echo json_encode(['success' => true]);

} catch (Exception $e) {

    $pdo->rollBack();

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}