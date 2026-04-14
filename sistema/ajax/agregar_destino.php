<?php
require_once '../inc/db.php';

header('Content-Type: application/json');

$destino   = strtolower(trim($_POST['destino'] ?? ''));
$tipo      = strtolower(trim($_POST['tipo'] ?? 'egreso')); // ingreso | egreso
$categoria = strtolower(trim($_POST['categoria'] ?? 'normal')); // normal | profesional | fondo

/* =========================
   VALIDACIONES
========================= */
if (!$destino) {
    echo json_encode([
        'success' => false,
        'message' => 'El nombre del destino es obligatorio'
    ]);
    exit;
}

$tiposValidos = ['ingreso', 'egreso'];
if (!in_array($tipo, $tiposValidos)) {
    echo json_encode([
        'success' => false,
        'message' => 'Tipo inválido'
    ]);
    exit;
}

$categoriasValidas = ['normal', 'profesional', 'fondo'];
if (!in_array($categoria, $categoriasValidas)) {
    echo json_encode([
        'success' => false,
        'message' => 'Categoría inválida'
    ]);
    exit;
}

try {

    /* =========================
       VALIDAR EXISTENCIA
    ========================= */
    $stmt = $pdo->prepare("
        SELECT id 
        FROM destinos_reparto 
        WHERE LOWER(TRIM(nombre)) = ?
        LIMIT 1
    ");
    $stmt->execute([$destino]);

    if ($stmt->fetch()) {
        echo json_encode([
            'success' => false,
            'message' => 'El destino ya existe'
        ]);
        exit;
    }

    /* =========================
       INSERTAR
    ========================= */
    $stmt = $pdo->prepare("
        INSERT INTO destinos_reparto (nombre, tipo, categoria) 
        VALUES (?, ?, ?)
    ");
    $stmt->execute([$destino, $tipo, $categoria]);

    $id = $pdo->lastInsertId();

    echo json_encode([
        'success' => true,
        'id' => $id,
        'nombre' => ucfirst($destino),
        'tipo' => $tipo,
        'categoria' => $categoria
    ]);

} catch (PDOException $e) {

    echo json_encode([
        'success' => false,
        'message' => 'Error de base de datos'
    ]);
}